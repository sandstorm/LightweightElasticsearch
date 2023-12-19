<?php

namespace Sandstorm\LightweightElasticsearch;

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\IndexNameService;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\Indexing\AliasManager;
use Sandstorm\LightweightElasticsearch\Indexing\CustomIndexer;
use Sandstorm\LightweightElasticsearch\Indexing\CustomIndexerFactory;
use Sandstorm\LightweightElasticsearch\Indexing\SubgraphIndexer;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ElasticsearchApiClient;
use Sandstorm\LightweightElasticsearch\NodeTypeMapping\NodeTypeMappingBuilder;
use Sandstorm\LightweightElasticsearch\Settings\ElasticsearchSettings;
use Sandstorm\LightweightElasticsearch\SharedModel\AliasName;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexDiscriminator;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexName;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexGeneration;
use Sandstorm\LightweightElasticsearch\Factory\ElasticsearchFactory;

/**
 * This is the main API to interact with Elasticsearch.
 *
 * It contains the core logic:
 * - common models
 * - schema management and indexing
 *
 * ## Architecture
 *
 * ┌─────────────────────────────┬────────────────────────┐
 * │          **Query**          │      **Command**       │
 * │                             │                        │
 * │    doing search requests    │   CLI Commands (e.g.   │
 * │                             │       indexing)        │
 * ├─────────────────────────────┴────────────────────────┤     ┌──────────────────────────────────────────────────────┐
 * │               **Elasticsearch Class**                │     │               **ElasticsearchFactory**               │
 * │        >>>>>>>>>>>  THIS CLASS HERE   <<<<<<<<<<<    │     │                                                      │
 * │                   main entry point                   │◀────│  Wiring for Elasticsearch class and inner objects.   │
 * ├──────────────────────────────────────────────────────┤     └──────────────────────────────────────────────────────┘
 * │                     **Indexing**                     │
 * │                                                      │
 * │       Node/Subgraph Indexing + Custom Indexing       │
 * │                  + Asset Extraction                  │
 * ├───────────────────────┬──────────────────────────────┤
 * │  **NodeTypeMapping**  │  **ElasticsearchApiClient**  │
 * │                       │                              │
 * │     Node Types =>     │API towards Elasticsearch (can│
 * │ Elasticsearch Mapping │  support multiple versions)  │
 * ├───────────────────────┴──────────────────────────────┤
 * │                     **Settings**                     │
 * │                                                      │
 * │    Type-safe access to relevant Settings.yaml and    │
 * │                NodeTypes.yaml values                 │
 * ├──────────────────────────────────────────────────────┤
 * │                   **SharedModel**                    │
 * │                                                      │
 * │                 common Value Objects                 │
 * └──────────────────────────────────────────────────────┘
 *
 */
#[Flow\Proxy(false)]
class Elasticsearch
{
    /**
     * @internal do not call yourself; but instead use the {@see ElasticsearchFactory::build()} for creating.
     */
    public function __construct(
        public readonly ElasticsearchSettings $settings,
        private readonly ContentRepository $contentRepository,
        public readonly ElasticsearchApiClient $apiClient,
        private readonly NodeTypeMappingBuilder $nodeTypeMappingBuilder,
        private readonly SubgraphIndexer $documentIndexer,
        private readonly AliasManager $aliasManager,
        public readonly LoggerInterface $logger,
        private readonly CustomIndexerFactory $customIndexerFactory
    ) {
    }

    /**
     * Index a workspace into Elasticsearch, by indexing all DimensionSpacePoints separately.
     */
    public function indexWorkspace(WorkspaceName $workspaceName): void
    {
        $workspace = $this->contentRepository->getWorkspaceFinder()->findOneByName($workspaceName);
        if ($workspace === null) {
            throw new \InvalidArgumentException(
                'Workspace "' . $workspaceName->value . '" not found.', 1693391297
            );
        }
        $dimensionSpacePoints = $this->contentRepository->getVariationGraph()->getDimensionSpacePoints();

        foreach ($dimensionSpacePoints as $dimensionSpacePoint) {
            $aliasName = AliasName::createForWorkspaceAndDimensionSpacePoint($this->settings->nodeIndexNamePrefix, $this->contentRepository->id, $workspaceName, $dimensionSpacePoint);
            $indexName = IndexName::createForAlias($aliasName, IndexGeneration::createFromCurrentTime());

            // 1) Create Index & Mapping
            $this->logger->info('Dimension space point: ' . $dimensionSpacePoint->toJson() . ' -> Creating Index: ' . $indexName->value);
            if ($this->apiClient->hasIndex($indexName)) {
                $this->logger->info('  Index exists, removing and recreating');
                $this->apiClient->removeIndex($indexName);
            }
            $this->apiClient->createIndex($indexName, $this->settings->createIndexParameters($indexName));

            $mappingDefinition = $this->nodeTypeMappingBuilder->build($this->contentRepository->getNodeTypeManager());
            $this->apiClient->updateMapping($indexName, $mappingDefinition);

            // 2) Index nodes
            $this->logger->info('Dimension space point: ' . $dimensionSpacePoint->toJson() . ' -> Indexing nodes into: ' . $indexName->value);
            $subgraph = $this->contentRepository->getContentGraph()->getSubgraph($workspace->currentContentStreamId, $dimensionSpacePoint, VisibilityConstraints::frontend());
            $this->documentIndexer->indexSubgraph($subgraph, $workspace, $indexName, $this);

            // 3) Create/switch alias
            $this->logger->info('Dimension space point: ' . $dimensionSpacePoint->toJson() . ' -> updating alias ' . $aliasName->value);
            $this->aliasManager->updateIndexAlias($aliasName, $indexName);
        }
    }

    /**
     * Remove all non-used indices
     */
    public function removeObsoleteIndices(WorkspaceName $workspaceName)
    {
        $dimensionSpacePoints = $this->contentRepository->getVariationGraph()->getDimensionSpacePoints();
        $allIndices = $this->apiClient->getAllIndexNames();

        $indicesToBeRemoved = [];
        foreach ($dimensionSpacePoints as $dimensionSpacePoint) {
            assert($dimensionSpacePoint instanceof DimensionSpacePoint);
            $this->logger->debug('Dimension Space Point: ' . $dimensionSpacePoint->toJson() . ' - hash: ' . $dimensionSpacePoint->hash);
            $aliasName = AliasName::createForWorkspaceAndDimensionSpacePoint($this->settings->nodeIndexNamePrefix, $this->contentRepository->id, $workspaceName, $dimensionSpacePoint);
            $currentlyLiveIndices = array_map(fn(IndexName $indexName) => $indexName->value, $this->aliasManager->loadIndicesForAlias($aliasName));

            foreach ($allIndices as $indexName) {
                if (str_starts_with($indexName->value, $aliasName->value) !== true) {
                    // filter out all indices not starting with the alias-name, as they are unrelated to our application
                    continue;
                }

                if (in_array($indexName->value, $currentlyLiveIndices, true)) {
                    $this->logger->debug('  Skipping index ' . $indexName->value . ', because it is currently live');
                    // skip the currently live index names from deletion
                    continue;
                }

                $indicesToBeRemoved[] = $indexName;
                $this->logger->info('  Will remove index ' . $indexName->value . '.');
            }
        }

        foreach ($indicesToBeRemoved as $indexName) {
            $this->apiClient->removeIndex($indexName);
        }
        $this->logger->info('Removed ' . count($indicesToBeRemoved) . ' indices.');

    }

    /**
     * Create a custom indexer with the given $aliasName as index alias (i.e. what you specify in the 2nd argument
     * of `Elasticsearch.createRequest(site, ['myAlias'])` in Eel).
     *
     * The given $discriminatorValue is used as value of the `index_discriminator` key in every indexed document;
     * and can be used to distinguish different document types inside a query.
     *
     * @param AliasName $aliasName name of the Elasticsearch alias to create/update when indexing is completed
     * @param IndexDiscriminator $discriminator value of the index_discriminator field of all documents indexed by this indexer
     * @return CustomIndexer
     */
    public function customIndexer(AliasName $aliasName, IndexDiscriminator $discriminator): CustomIndexer
    {
        $indexName = IndexName::createForAlias($aliasName, IndexGeneration::createFromCurrentTime());

        return $this->customIndexerFactory->build($indexName, $aliasName, $discriminator);
    }
}
