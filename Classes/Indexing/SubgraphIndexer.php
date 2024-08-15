<?php

namespace Sandstorm\LightweightElasticsearch\Indexing;

use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\NodeType\NodeTypeCriteria;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\Workspace\Workspace;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;
use Sandstorm\LightweightElasticsearch\Elasticsearch;
use Sandstorm\LightweightElasticsearch\Settings\ElasticsearchSettings;
use Sandstorm\LightweightElasticsearch\Settings\NodeTypeSearchSettings;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexDiscriminator;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexName;
use Sandstorm\LightweightElasticsearch\SharedModel\MappingDefinition;

/**
 * Main class to index a Subgraph
 */
#[Flow\Proxy(false)]
readonly class SubgraphIndexer
{
    public function __construct(
        private BulkRequestSenderFactory $bulkRequestSenderFactory,
        private IndexingEelEvaluator $indexingEelEvaluator,
        private ElasticsearchSettings $settings,
        private ContentRepositoryRegistry $contentRepositoryRegistry,
    ) {
    }

    public function indexSubgraph(ContentSubgraphInterface $subgraph, WorkspaceName $workspaceName, IndexName $indexName, Elasticsearch $elasticsearch): void
    {
        $bulkRequestSender = $this->bulkRequestSenderFactory->withIndexName($indexName);
        // TODO: single site??
        $node = $subgraph->findRootNodeByType(NodeTypeName::fromString('Neos.Neos:Sites')); // TODO
        if (!$node) {
            return;
        }
        $this->indexDocumentNodesRecursively($node->aggregateId, $subgraph, $workspaceName, $bulkRequestSender, $elasticsearch);
        $bulkRequestSender->close();
    }

    private function indexDocumentNodesRecursively(NodeAggregateId $parentNodeAggregateId, ContentSubgraphInterface $subgraph, WorkspaceName $workspaceName, BulkRequestSender $bulkRequestSender, Elasticsearch $elasticsearch): void
    {
        $documentNodes = $subgraph->findChildNodes(
            $parentNodeAggregateId,
            FindChildNodesFilter::create(
                nodeTypes: NodeTypeCriteria::fromFilterString('Neos.Neos:Document')
            )
        );

        foreach ($documentNodes as $childNode) {
            $this->indexDocumentNodeAndContent($childNode, $subgraph, $workspaceName, $bulkRequestSender, $elasticsearch);

            // recursion
            $this->indexDocumentNodesRecursively($childNode->aggregateId, $subgraph, $workspaceName, $bulkRequestSender, $elasticsearch);
        }
    }

    /**
     * Index this node, and add it to the current bulk request.
     */
    private function indexDocumentNodeAndContent(Node $node, ContentSubgraphInterface $subgraph, WorkspaceName $workspaceName, BulkRequestSender $bulkRequestSender, Elasticsearch $elasticsearch): void
    {
        $nodeType = $this->contentRepositoryRegistry->get($node->contentRepositoryId)
            ->getNodeTypeManager()->getNodeType($node->nodeTypeName);
        if (!$nodeType) {
            return;
        }
        $nodeTypeSearchSettings = NodeTypeSearchSettings::fromNodeType($nodeType, $this->settings->defaultConfigurationPerType);
        if (!$nodeTypeSearchSettings->isIndexed) {
            $elasticsearch->logger->debug(sprintf('Node "%s" (%s) skipped, Node Type is not allowed in the index (search.isIndexed not set).', $node->aggregateId->value, $node->nodeTypeName->value), LogEnvironment::fromMethodName(__METHOD__));
            return;
        }

        $elasticsearchDocument = $this->extractNodePropertiesForIndexing($nodeTypeSearchSettings, $node, $elasticsearch);
        if ($nodeTypeSearchSettings->isFulltextRoot) {
            $elasticsearch->logger->info(sprintf('Node "%s" (%s) indexed with fulltext.', $node->aggregateId->value, $node->nodeTypeName->value), LogEnvironment::fromMethodName(__METHOD__));

            $fulltextData = [];
            $this->extractFulltextRecursivelyForContent($node, $subgraph, $fulltextData, $elasticsearch);
            $elasticsearchDocument[MappingDefinition::NEOS_FULLTEXT_FIELD] = $fulltextData;
        } else {
            $elasticsearch->logger->info(sprintf('Node "%s" (%s) indexed (without fulltext).', $node->aggregateId->value, $node->nodeTypeName->value), LogEnvironment::fromMethodName(__METHOD__));
        }

        $elasticsearchDocument['neos_workspace'] = $workspaceName->value;
        $elasticsearchDocument[MappingDefinition::NEOS_TYPE_FIELD] = $node->nodeTypeName->value;
        $elasticsearchDocument[IndexDiscriminator::KEY] = IndexDiscriminator::NEOS_NODES;

        $elasticsearchDocumentId = $node->aggregateId->value;
        $bulkRequestSender->indexDocument($elasticsearchDocument, $elasticsearchDocumentId);
    }

    private function extractNodePropertiesForIndexing(NodeTypeSearchSettings $nodeTypeSearchSettings, Node $node, Elasticsearch $elasticsearch): mixed
    {
        $elasticsearchDocument = [];
        foreach ($nodeTypeSearchSettings->properties as $propertySearchSettings) {
            if ($propertySearchSettings->isIndexingEnabled()) {
                $valueToStore = $this->indexingEelEvaluator->runPropertyIndexingExpression($propertySearchSettings, $node, $elasticsearch);
                $elasticsearchDocument[$propertySearchSettings->propertyName] = $valueToStore;
            }
        }

        return $elasticsearchDocument;
    }

    /**
     * @param array<mixed> $fulltextData
     */
    private function extractFulltextRecursivelyForContent(Node $node, ContentSubgraphInterface $subgraph, array &$fulltextData, Elasticsearch $elasticsearch): void
    {
        $nodeType = $this->contentRepositoryRegistry->get($node->contentRepositoryId)
            ->getNodeTypeManager()->getNodeType($node->nodeTypeName);
        if (!$nodeType) {
            return;
        }
        $nodeTypeSearchSettings = NodeTypeSearchSettings::fromNodeType($nodeType, $this->settings->defaultConfigurationPerType);

        foreach ($nodeTypeSearchSettings->properties as $propertySearchSettings) {
            if ($nodeTypeSearchSettings->isFulltextEnabled && $propertySearchSettings->hasFulltextExtractor()) {
                $this->indexingEelEvaluator->runFulltextExpression($propertySearchSettings, $node, $fulltextData, $elasticsearch);

                $elasticsearch->logger->debug(sprintf('  Node "%s" - Property "%s" indexed with fulltext.', $node->aggregateId->value, $propertySearchSettings->propertyName), LogEnvironment::fromMethodName(__METHOD__));
            }
        }

        $contentNodes = $subgraph->findChildNodes(
            $node->aggregateId,
            FindChildNodesFilter::create(
                nodeTypes: NodeTypeCriteria::fromFilterString('Neos.Neos:Content,Neos.Neos:ContentCollection')
            )
        );
        foreach ($contentNodes as $contentNode) {
            $this->extractFulltextRecursivelyForContent($contentNode, $subgraph, $fulltextData, $elasticsearch);
        }
    }
}
