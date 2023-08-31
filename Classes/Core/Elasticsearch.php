<?php

namespace Sandstorm\LightweightElasticsearch\Core;

use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\Core\DocumentIndexing\AliasManager;
use Sandstorm\LightweightElasticsearch\Core\DocumentIndexing\DocumentIndexer;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ElasticsearchApiClient;
use Sandstorm\LightweightElasticsearch\Core\NodeTypeMapping\NodeTypeMappingBuilder;
use Sandstorm\LightweightElasticsearch\Core\Settings\ElasticsearchSettings;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\AliasName;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\IndexName;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\IndexGeneration;

#[Flow\Proxy(false)]
class Elasticsearch
{
    public function __construct(
        public readonly ElasticsearchSettings $settings,
        private readonly ContentRepository $contentRepository,
        public readonly ElasticsearchApiClient $apiClient,
        private readonly NodeTypeMappingBuilder $nodeTypeMappingBuilder,
        private readonly DocumentIndexer $documentIndexer,
        private readonly AliasManager $aliasManager,
    ) {
    }

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

            // 3) Create alias
            $logger->info('Dimension space point: ' . $dimensionSpacePoint->toJson() . ' -> updating aliases');
            $this->aliasManager->updateIndexAlias($aliasName, $indexName);
        }
    }
}
