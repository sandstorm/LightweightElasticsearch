<?php


namespace Sandstorm\LightweightElasticsearch\Query\Result;


use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\ProtectedContextAwareInterface;

class SearchResultDocument implements ProtectedContextAwareInterface
{
    protected $hit;
    private ?NodeInterface $contextNode;

    protected function __construct(array $hit, NodeInterface $contextNode = null)
    {
        $this->hit = $hit;
        $this->contextNode = $contextNode;
    }

    public static function fromElasticsearchJsonResponse(array $hit, NodeInterface $contextNode = null): self
    {
        return new static($hit, $contextNode);
    }

    public function loadNode(): ?NodeInterface
    {
        $nodePath = $this->hit['_source']['neos_path'];

        if (is_array($nodePath)) {
            $nodePath = current($nodePath);
        }

        return $this->contextNode->getNode($nodePath);
    }

    public function getFullSearchHit()
    {
        return $this->hit;
    }

    public function property(string $key)
    {
        return $this->hit['_source'][$key] ?? null;
    }

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

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
