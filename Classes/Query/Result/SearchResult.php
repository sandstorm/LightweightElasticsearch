<?php


namespace Sandstorm\LightweightElasticsearch\Query\Result;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\Query\SearchRequestBuilder;

/**
 * Wrapper for all search results
 *
 * @Flow\Proxy(false)
 */
class SearchResult implements \IteratorAggregate, ProtectedContextAwareInterface, \Countable
{

    private array $response;
    private bool $isError;
    private ?NodeInterface $contextNode;

    /**
     * DO NOT CALL THIS DIRECTLY; only to be called from {@see SearchRequestBuilder::execute()}
     *
     * @param array $response
     * @param NodeInterface $contextNode
     * @return static
     * @internal
     */
    public static function fromElasticsearchJsonResponse(array $response, NodeInterface $contextNode): self
    {
        return new SearchResult($response, false, $contextNode);
    }

    /**
     * DO NOT CALL THIS DIRECTLY; only to be called from {@see SearchRequestBuilder::execute()}
     *
     * @return static
     * @internal
     */
    public static function error(): self
    {
        return new SearchResult([], true);
    }

    private function __construct(array $response, bool $isError, NodeInterface $contextNode = null)
    {
        $this->response = $response;
        $this->isError = $isError;
        $this->contextNode = $contextNode;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function getIterator(): \Generator
    {
        if (isset($this->response['hits']['hits'])) {
            foreach ($this->response['hits']['hits'] as $hit) {
                yield SearchResultDocument::fromElasticsearchJsonResponse($hit, $this->contextNode);
            }
        }
    }

    public function total(): int
    {
        if (!isset($this->response['hits']['total']['value'])) {
            return 0;
        }
        return $this->response['hits']['total']['value'];

    }

    public function count()
    {
        if (isset($this->response['hits']['hits'])) {
            return count($this->response['hits']['hits']);
        }
        return 0;
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
