<?php

declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query\Result;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\Query\SearchRequestBuilder;

/**
 * Wrapper for all search results
 *
 * @implements \IteratorAggregate<SearchResultDocument>
 */
#[Flow\Proxy(false)]
class SearchResult implements \IteratorAggregate, ProtectedContextAwareInterface, \Countable
{
    /**
     * DO NOT CALL THIS DIRECTLY; only to be called from {@see SearchRequestBuilder::execute()}
     *
     * @param array<mixed> $response
     * @internal
     */
    public static function fromElasticsearchJsonResponse(array $response, ?Node $contextNode, ContentRepositoryRegistry $contentRepositoryRegistry): self
    {
        return new self($response, false, $contextNode, $contentRepositoryRegistry);
    }

    /**
     * DO NOT CALL THIS DIRECTLY; only to be called from {@see SearchRequestBuilder::execute()}
     *
     * @internal
     */
    public static function error(): self
    {
        return new self([], true);
    }

    /**
     * @param array<mixed> $response
     */
    private function __construct(
        private readonly array $response,
        private readonly bool $isError,
        private readonly Node|null $contextNode = null,
        private readonly ContentRepositoryRegistry|null $contentRepositoryRegistry = null,
    ) {
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function getIterator(): \Generator
    {
        if (isset($this->response['hits']['hits'])) {
            foreach ($this->response['hits']['hits'] as $hit) {
                yield SearchResultDocument::fromElasticsearchJsonResponse($hit, $this->contextNode, $this->contentRepositoryRegistry);
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

    public function count(): int
    {
        if (isset($this->response['hits']['hits'])) {
            return count($this->response['hits']['hits']);
        }
        return 0;
    }

    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }
}
