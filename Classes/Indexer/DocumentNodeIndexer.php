<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Indexer;

/*
 * This file is part of the Sandstorm.LightweightElasticsearch package.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Sandstorm\LightweightElasticsearch\Driver\DocumentIndexerDriver;

/**
 * The DocumentNodeIndexer indexes **only nodes marked as fulltext root** (i.e. only document nodes
 * in the common case).
 *
 * The fulltext of these nodes is also collected from their descendant nodes - this is done in here
 * (i.e. in PHP) instead of in Elasticsearch (as it is done in Flowpack.Elasticsearch.ContentRepositoryAdaptor).
 *
 * The overridden {@see DocumentIndexerDriver} takes care of sending only a single Elasticsearch request for
 * each document.
 */
class DocumentNodeIndexer extends NodeIndexer
{

    /**
     * @return void
     */
    public function indexNode(NodeInterface $node, $targetWorkspaceName = null): void
    {
        // it would be more efficient (at index time) to skip Document nodes directly
        // in the WorkspaceIndexer, but that one is final and not meant for replacement.
        if (!self::isFulltextRoot($node)) {
            return;
        }
        parent::indexNode($node, $targetWorkspaceName);
    }

    protected function extractPropertiesAndFulltext(NodeInterface $node, array &$fulltextData, \Closure $nonIndexedPropertyErrorHandler = null): array
    {
        $result = parent::extractPropertiesAndFulltext($node, $fulltextData, $nonIndexedPropertyErrorHandler);

        foreach ($node->getChildNodes('Neos.Neos:Content,Neos.Neos:ContentCollection') as $childNode) {
            $this->enrichWithFulltextForContentNodes($childNode, $fulltextData);
        }

        return $result;
    }

    protected function enrichWithFulltextForContentNodes(NodeInterface $node, array &$fulltextData): void
    {
        $nodeType = $node->getNodeType();
        $fulltextIndexingEnabledForNode = $this->isFulltextEnabled($node);

        foreach ($nodeType->getProperties() as $propertyName => $propertyConfiguration) {
            if ($fulltextIndexingEnabledForNode === true && isset($propertyConfiguration['search']['fulltextExtractor'])) {
                $this->extractFulltext($node, $propertyName, $propertyConfiguration['search']['fulltextExtractor'], $fulltextData);
            }
        }

        foreach ($node->getChildNodes('Neos.Neos:Content,Neos.Neos:ContentCollection') as $childNode) {
            $this->enrichWithFulltextForContentNodes($childNode, $fulltextData);
        }
    }

    /**
     * Whether the node is configured as fulltext root. Copied from AbstractIndexerDriver::isFulltextRoot().
     *
     * @param NodeInterface $node
     * @return bool
     */
    protected static function isFulltextRoot(NodeInterface $node): bool
    {
        if ($node->getNodeType()->hasConfiguration('search')) {
            $elasticSearchSettingsForNode = $node->getNodeType()->getConfiguration('search');
            if (isset($elasticSearchSettingsForNode['fulltext']['isRoot']) && $elasticSearchSettingsForNode['fulltext']['isRoot'] === true) {
                return true;
            }
        }

        return false;
    }
}
