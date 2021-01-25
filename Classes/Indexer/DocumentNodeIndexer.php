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

class DocumentNodeIndexer extends NodeIndexer
{

    /**
     * @return void
     */
    public function indexNode(NodeInterface $node, $targetWorkspaceName = null): void
    {
        // it would be more efficient (at index time) to skip Document nodes directly
        // in the WorkspaceIndexer, but that one is final and not meant for replacement.
        if (!$node->getNodeType()->isOfType('Neos.Neos:Document')) {
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
    }
}
