<?php


namespace Sandstorm\LightweightElasticsearch\Query\Result;


class SearchResult implements \IteratorAggregate
{
    public static function fromElasticsearchJsonResponse(array $response): self
    {
        return new SearchResult($response, false);
    }

    public static function error(): self
    {
        return new SearchResult([], true);
    }

    private array $response;
    private bool $isError;

    private function __construct(array $response, bool $isError)
    {
        $this->response = $response;
        $this->isError = $isError;
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function getIterator(): \Generator
    {
        foreach ($this->response['hits']['hits'] as $hit) {
            yield SearchResultDocument::fromElasticsearchJsonResponse($hit);
        }
    }
}
