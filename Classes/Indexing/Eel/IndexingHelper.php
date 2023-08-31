<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Indexing\Eel;

/*
 * This file is part of the Neos.ContentRepository.Search package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Media\Domain\Model\AssetInterface;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\Indexing\AssetExtraction\AssetExtractorInterface;

/**
 * IndexingHelper
 */
class IndexingHelper implements ProtectedContextAwareInterface
{
    // WORKAROUND: set by {@see IndexingEelEvaluator} to ensure the current Elasticsearch entry point instance exists here.
    public static \Sandstorm\LightweightElasticsearch\Elasticsearch $elasticsearch;

    /**
     * @Flow\Inject
     * @var AssetExtractorInterface
     */
    protected $assetExtractor;

    /**
     * @Flow\Inject
     * @var ContentRepositoryRegistry
     */
    protected $contentRepositoryRegistry;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    public function workspaceNameForNode(Node $node): string
    {
        $contentRepository = $this->contentRepositoryRegistry->get($node->subgraphIdentity->contentRepositoryId);
        $workspace = $contentRepository->getWorkspaceFinder()->findOneByCurrentContentStreamId($node->subgraphIdentity->contentStreamId);

        return $workspace->workspaceName->value;

    }

    /**
     * Returns an array of node type names including the passed $nodeType and all its supertypes, recursively
     *
     * @param \Neos\ContentRepository\Core\NodeType\NodeType $nodeType
     * @return array<String>
     */
    public function extractNodeTypeNamesAndSupertypes(\Neos\ContentRepository\Core\NodeType\NodeType $nodeType): array
    {
        $nodeTypeNames = [];
        $this->extractNodeTypeNamesAndSupertypesInternal($nodeType, $nodeTypeNames);
        return array_values($nodeTypeNames);
    }

    /**
     * Recursive function for fetching all node type names
     *
     * @param \Neos\ContentRepository\Core\NodeType\NodeType $nodeType
     * @param array $nodeTypeNames
     * @return void
     */
    protected function extractNodeTypeNamesAndSupertypesInternal(\Neos\ContentRepository\Core\NodeType\NodeType $nodeType, array &$nodeTypeNames): void
    {
        $nodeTypeNames[$nodeType->getName()] = $nodeType->getName();
        foreach ($nodeType->getDeclaredSuperTypes() as $superType) {
            $this->extractNodeTypeNamesAndSupertypesInternal($superType, $nodeTypeNames);
        }
    }

    /**
     * Convert an array of nodes to an array of node identifiers
     *
     * @param array<Node> $nodes
     * @return array
     */
    public function convertArrayOfNodesToArrayOfNodeIdentifiers($nodes): array
    {
        if (!is_array($nodes) && !$nodes instanceof \Traversable) {
            return [];
        }
        $nodeIdentifiers = [];
        foreach ($nodes as $node) {
            assert($node instanceof  Node);
            $nodeIdentifiers[] = $node->nodeAggregateId->value;
        }

        return $nodeIdentifiers;
    }

    /**
     * Convert an array of nodes to an array of node property
     *
     * @param array<NodeInterface> $nodes
     * @param string $propertyName
     * @return array
     */
    public function convertArrayOfNodesToArrayOfNodeProperty($nodes, string $propertyName): array
    {
        if (!is_array($nodes) && !$nodes instanceof \Traversable) {
            return [];
        }
        $nodeProperties = [];
        foreach ($nodes as $node) {
            $nodeProperties[] = $node->getProperty($propertyName);
        }

        return $nodeProperties;
    }

    /**
     *
     * @param $string
     * @return array
     */
    public function extractHtmlTags($string): array
    {
        if (!$string || trim($string) === "") {
            return [];
        }

        // prevents concatenated words when stripping tags afterwards
        $string = str_replace(['<', '>'], [' <', '> '], $string);
        // strip all tags except h1-6
        $string = strip_tags($string, '<h1><h2><h3><h4><h5><h6>');

        $parts = [
            'text' => ''
        ];
        while ($string !== '') {
            $matches = [];
            if (preg_match('/<(h1|h2|h3|h4|h5|h6)[^>]*>.*?<\/\1>/ui', $string, $matches, PREG_OFFSET_CAPTURE)) {
                $fullMatch = $matches[0][0];
                $startOfMatch = $matches[0][1];
                $tagName = $matches[1][0];

                if ($startOfMatch > 0) {
                    $parts['text'] .= substr($string, 0, $startOfMatch);
                    $string = substr($string, $startOfMatch);
                }
                if (!isset($parts[$tagName])) {
                    $parts[$tagName] = '';
                }

                $parts[$tagName] .= ' ' . $fullMatch;
                $string = substr($string, strlen($fullMatch));
            } else {
                // no h* found anymore in the remaining string
                $parts['text'] .= $string;
                break;
            }
        }

        foreach ($parts as &$part) {
            $part = preg_replace('/\s+/u', ' ', strip_tags($part));
        }

        return $parts;
    }

    /**
     * @param string $bucketName
     * @param string $string
     * @return array
     */
    public function extractInto(string $bucketName, $string): array
    {
        return [
            $bucketName => (string)$string
        ];
    }

    /**
     * Extract the asset content and meta data
     *
     * @param AssetInterface|AssetInterface[]|null $value
     * @param string $field
     */
    public function extractAssetContent(mixed $value, string $field = 'content'): array|null|string
    {
        if (empty($value)) {
            return null;
        } elseif (is_array($value)) {
            $result = [];
            foreach ($value as $element) {
                $result[] = $this->extractAssetContent($element, $field);
            }
            return $result;
        } elseif ($value instanceof AssetInterface) {
            $assetContent = $this->assetExtractor->extract($value, self::$elasticsearch);
            return $assetContent->$field;
        } else {
            $this->logger->error('Value of type ' . gettype($value) . ' - ' . get_class($value) . ' could not be extracted.', LogEnvironment::fromMethodName(__METHOD__));
        }
    }

    /**
     * All methods are considered safe
     *
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
