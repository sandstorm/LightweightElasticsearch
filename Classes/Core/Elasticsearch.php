<?php

namespace Sandstorm\LightweightElasticsearch\Core;

use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\Core\DocumentIndexing\DocumentIndexer;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ElasticsearchApiClient;
use Sandstorm\LightweightElasticsearch\Core\NodeTypeMapping\NodeTypeMappingBuilder;
use Sandstorm\LightweightElasticsearch\Core\Settings\ElasticsearchSettings;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\IndexName;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\IndexPostfix;

#[Flow\Proxy(false)]
class Elasticsearch
{
    public function __construct(
        private readonly ElasticsearchSettings $settings,
        private readonly ContentRepository $contentRepository,
        private readonly ElasticsearchApiClient $apiClient,
        private readonly NodeTypeMappingBuilder $nodeTypeMappingBuilder,
        private readonly DocumentIndexer $documentIndexer,
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
            // 1) Create Index & Mapping
            $indexName = IndexName::createForWorkspaceAndDimensionSpacePoint($this->settings->indexNamePrefix, $workspaceName, $dimensionSpacePoint, IndexPostfix::createFromCurrentTime());
            $logger->info('Dimension space point: ' . $dimensionSpacePoint->toJson() . ' -> Creating Index: ' . $indexName->value);
            if ($this->apiClient->hasIndex($indexName)) {
                $logger->info('  Index exists, removing and recreating');
                $this->apiClient->removeIndex($indexName);
            }
            $this->apiClient->createIndex($indexName, $this->settings->createIndexParameters($indexName));

            $mappingDefinition = $this->nodeTypeMappingBuilder->build($this->contentRepository->getNodeTypeManager(), $logger);
            $this->apiClient->updateMapping($indexName, $mappingDefinition);

            // 2) Index nodes
            $subgraph = $this->contentRepository->getContentGraph()->getSubgraph($workspace->currentContentStreamId, $dimensionSpacePoint, VisibilityConstraints::frontend());
            $this->documentIndexer->indexSubgraph($subgraph, $workspace, $indexName, $logger);

        }
    }
}
