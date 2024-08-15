<?php

declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query\Result;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\ProtectedContextAwareInterface;

readonly class SearchResultDocument implements ProtectedContextAwareInterface
{
    /**
     * @param array<mixed> $hit
     */
    protected function __construct(
        private array $hit,
        private Node|null $contextNode = null,
        private ContentRepositoryRegistry|null $contentRepositoryRegistry = null
    ) {
    }

    public static function fromElasticsearchJsonResponse(array $hit, Node $contextNode = null, ContentRepositoryRegistry $contentRepositoryRegistry = null): self
    {
        return new self($hit, $contextNode, $contentRepositoryRegistry);
    }

    public function loadNode(): ?Node
    {
        $nodeAggregateId = NodeAggregateId::fromString($this->hit['_source']['neos_nodeaggregateid']);

        $subgraph = $this->contentRepositoryRegistry?->subgraphForNode($this->contextNode);
        return $subgraph?->findNodeById($nodeAggregateId);
    }

    /**
     * @return array<mixed>
     */
    public function getFullSearchHit(): array
    {
        return $this->hit;
    }

    public function property(string $key): mixed
    {
        return $this->hit['_source'][$key] ?? null;
    }

    /**
     * @return array<mixed>
     */
    public function getProperties(): array
    {
        return $this->hit['_source'] ?? [];
    }

    /**
     * all highlights as a flat array of strings, no matter which field they were found in
     * @return string[]
     */
    public function getProcessedHighlights(): array
    {
        $highlights = $this->hit['highlight'] ?? [];
        $processedHighlights = [];
        foreach ($highlights as $field => $highlightArray) {
            if (is_string($highlightArray)) {
                $highlightArray = [$highlightArray];
            }
            foreach ($highlightArray as $highlight) {
                $processedHighlights[] = $highlight;
            }
        }

        return $processedHighlights;
    }

    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }
}
