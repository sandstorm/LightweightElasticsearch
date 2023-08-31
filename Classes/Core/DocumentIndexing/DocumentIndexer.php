<?php

namespace Sandstorm\LightweightElasticsearch\Core\DocumentIndexing;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindChildNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeTypeConstraints;
use Neos\ContentRepository\Core\Projection\Workspace\Workspace;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\Core\NodeTypeMapping\NodeTypeMappingBuilder;
use Sandstorm\LightweightElasticsearch\Core\Settings\ElasticsearchSettings;
use Sandstorm\LightweightElasticsearch\Core\Settings\NodeTypeSearchSettings;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\IndexName;

#[Flow\Proxy(false)]
class DocumentIndexer
{

    public function __construct(
        private readonly BulkRequestSenderFactory $bulkRequestSenderFactory,
        private readonly IndexingEelEvaluator $indexingEelEvaluator,
        private readonly ElasticsearchSettings $settings,
    ) {
    }

    public function indexSubgraph(ContentSubgraphInterface $subgraph, Workspace $workspace, IndexName $indexName, LoggerInterface $logger): void
    {
        $bulkRequestSender = $this->bulkRequestSenderFactory->withIndexName($indexName);
        // TODO: single site??
        $node = $subgraph->findRootNodeByType(NodeTypeName::fromString('Neos.Neos:Sites')); // TODO
        $this->indexDocumentNodesRecursively($node->nodeAggregateId, $subgraph, $workspace, $bulkRequestSender, $logger);
        $bulkRequestSender->close();
    }

    private function indexDocumentNodesRecursively(NodeAggregateId $parentNodeAggregateId, ContentSubgraphInterface $subgraph, Workspace $workspace, BulkRequestSender $bulkRequestSender, LoggerInterface $logger)
    {
        $documentNodes = $subgraph->findChildNodes(
            $parentNodeAggregateId,
            FindChildNodesFilter::create(
                nodeTypeConstraints: NodeTypeConstraints::fromFilterString('Neos.Neos:Document')
            )
        );

        foreach ($documentNodes as $childNode) {
            $this->indexDocumentNodeAndContent($childNode, $subgraph, $workspace, $bulkRequestSender, $logger);

            $this->indexDocumentNodesRecursively($childNode->nodeAggregateId, $subgraph, $workspace, $bulkRequestSender, $logger);
        }
    }

    /**
     * Index this node, and add it to the current bulk request.
     */
    private function indexDocumentNodeAndContent(Node $node, ContentSubgraphInterface $subgraph, Workspace $workspace, BulkRequestSender $bulkRequestSender, LoggerInterface $logger): void
    {

        $nodeTypeSearchSettings = NodeTypeSearchSettings::fromNodeType($node->nodeType, $this->settings->defaultConfigurationPerType);
        if (!$nodeTypeSearchSettings->isIndexed) {
            $logger->debug(sprintf('Node "%s" (%s) skipped, Node Type is not allowed in the index (search.isIndexed not set).', $node->nodeAggregateId->value, $node->nodeTypeName->value), LogEnvironment::fromMethodName(__METHOD__));
            return;
        }

        $elasticsearchDocumentId = $node->nodeAggregateId->value;

        $elasticsearchDocument = $this->extractNodePropertiesForIndexing($nodeTypeSearchSettings, $node, $logger);
        if ($nodeTypeSearchSettings->isFulltextRoot) {
            $logger->info(sprintf('Node "%s" (%s) indexed with fulltext.', $node->nodeAggregateId->value, $node->nodeTypeName->value), LogEnvironment::fromMethodName(__METHOD__));

            $fulltextData = [];
            $this->extractFulltextRecursivelyForContent($node, $subgraph, $fulltextData, $logger);
            $elasticsearchDocument[NodeTypeMappingBuilder::NEOS_FULLTEXT_FIELD] = $fulltextData;
        } else {
            $logger->info(sprintf('Node "%s" (%s) indexed (without fulltext).', $node->nodeAggregateId->value, $node->nodeTypeName->value), LogEnvironment::fromMethodName(__METHOD__));
        }

        $elasticsearchDocument['neos_workspace'] = $workspace->workspaceName->value;
        $elasticsearchDocument[NodeTypeMappingBuilder::NEOS_TYPE_FIELD] = $node->nodeTypeName->value;
        $bulkRequestSender->indexDocument($elasticsearchDocumentId, $elasticsearchDocument);
    }

    private function extractNodePropertiesForIndexing(NodeTypeSearchSettings $nodeTypeSearchSettings, Node $node, LoggerInterface $logger): mixed
    {
        $elasticsearchDocument = [];
        foreach ($nodeTypeSearchSettings->properties as $propertySearchSettings) {
            if ($propertySearchSettings->isIndexingEnabled()) {
                $valueToStore = $this->indexingEelEvaluator->runPropertyIndexingExpression($propertySearchSettings, $node);
                $elasticsearchDocument[$propertySearchSettings->propertyName] = $valueToStore;
            }
        }

        return $elasticsearchDocument;
    }

    private function extractFulltextRecursivelyForContent(Node $node, ContentSubgraphInterface $subgraph, array &$fulltextData, LoggerInterface $logger): void
    {
        $nodeTypeSearchSettings = NodeTypeSearchSettings::fromNodeType($node->nodeType, $this->settings->defaultConfigurationPerType);

        foreach ($nodeTypeSearchSettings->properties as $propertySearchSettings) {
            if ($nodeTypeSearchSettings->isFulltextEnabled && $propertySearchSettings->hasFulltextExtractor()) {
                $this->indexingEelEvaluator->runFulltextExpression($propertySearchSettings, $node, $fulltextData);

                $logger->debug(sprintf('  Node "%s" - Property "%s" indexed with fulltext.', $node->nodeAggregateId->value, $propertySearchSettings->propertyName), LogEnvironment::fromMethodName(__METHOD__));
            }
        }

        $contentNodes = $subgraph->findChildNodes(
            $node->nodeAggregateId,
            FindChildNodesFilter::create(
                nodeTypeConstraints: NodeTypeConstraints::fromFilterString('Neos.Neos:Content,Neos.Neos:ContentCollection')
            )
        );
        foreach ($contentNodes as $contentNode) {
            $this->extractFulltextRecursivelyForContent($contentNode, $subgraph, $fulltextData, $logger);
        }
    }
}
