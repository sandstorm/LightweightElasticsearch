<?php

namespace Sandstorm\LightweightElasticsearch;

use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\Indexing\AliasManager;
use Sandstorm\LightweightElasticsearch\Indexing\SubgraphIndexer;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ElasticsearchApiClient;
use Sandstorm\LightweightElasticsearch\NodeTypeMapping\NodeTypeMappingBuilder;
use Sandstorm\LightweightElasticsearch\Settings\ElasticsearchSettings;
use Sandstorm\LightweightElasticsearch\SharedModel\AliasName;
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
    ) {
    }

    /**
     * Index a workspace into Elasticsearch, by indexing all DimensionSpacePoints separately.
     */
    public function indexWorkspace(WorkspaceName $workspaceName, LoggerInterface $logger): void
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
            $logger->info('Dimension space point: ' . $dimensionSpacePoint->toJson() . ' -> Creating Index: ' . $indexName->value);
            if ($this->apiClient->hasIndex($indexName)) {
                $logger->info('  Index exists, removing and recreating');
                $this->apiClient->removeIndex($indexName);
            }
            $this->apiClient->createIndex($indexName, $this->settings->createIndexParameters($indexName));

            $mappingDefinition = $this->nodeTypeMappingBuilder->build($this->contentRepository->getNodeTypeManager(), $logger);
            $this->apiClient->updateMapping($indexName, $mappingDefinition);

            // 2) Index nodes
            $logger->info('Dimension space point: ' . $dimensionSpacePoint->toJson() . ' -> Indexing nodes into: ' . $indexName->value);
            $subgraph = $this->contentRepository->getContentGraph()->getSubgraph($workspace->currentContentStreamId, $dimensionSpacePoint, VisibilityConstraints::frontend());
            $this->documentIndexer->indexSubgraph($subgraph, $workspace, $indexName, $logger);

            // 3) Create/switch alias
            $logger->info('Dimension space point: ' . $dimensionSpacePoint->toJson() . ' -> updating aliases');
            $this->aliasManager->updateIndexAlias($aliasName, $indexName);
        }
    }
}