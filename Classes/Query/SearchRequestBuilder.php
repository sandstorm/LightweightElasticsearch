<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query;

use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\Query\Highlight\HighlightBuilderInterface;
use Sandstorm\LightweightElasticsearch\Query\Query\SearchQueryBuilderInterface;
use Sandstorm\LightweightElasticsearch\Query\Result\SearchResult;


class SearchRequestBuilder extends AbstractSearchRequestBuilder
{
    #[Flow\InjectConfiguration(path: 'handleElasticsearchExceptions')]
    protected string $handleElasticsearchExceptions;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    /**
     * Cached search result
     *
     * @var SearchResult|null
     */
    private ?SearchResult $searchResult = null;

    /**
     * @var array<string,mixed>
     */
    protected array $request = [];

    public function query(SearchQueryBuilderInterface $query): self
    {
        if ($this->searchResult !== null) {
            // we need to reset the search result cache when the builder is mutated
            $this->searchResult = null;
        }

        $this->request['query'] = $query->buildQuery();
        return $this;
    }

    public function from(int $offset): self
    {
        if ($this->searchResult !== null) {
            // we need to reset the search result cache when the builder is mutated
            $this->searchResult = null;
        }

        $this->request['from'] = $offset;
        return $this;
    }

    public function size(int $size): self
    {
        if ($this->searchResult !== null) {
            // we need to reset the search result cache when the builder is mutated
            $this->searchResult = null;
        }

        $this->request['size'] = $size;
        return $this;
    }

    public function minScore(float $minScore): self
    {
        if ($this->searchResult !== null) {
            // we need to reset the search result cache when the builder is mutated
            $this->searchResult = null;
        }

        $this->request['min_score'] = $minScore;
        return $this;
    }

    public function highlight(HighlightBuilderInterface $highlightBuilder): self
    {
        if ($this->searchResult !== null) {
            // we need to reset the search result cache when the builder is mutated
            $this->searchResult = null;
        }

        $this->request['highlight'] = $highlightBuilder->buildHighlightRequestPart();
        return $this;
    }

    /**
     * Execute the query and return the SearchResult object as result.
     *
     * You can call this method multiple times; and the request is only executed at the first time; and cached
     * for later use.
     */
    public function execute(): SearchResult
    {
        if ($this->searchResult === null) {
            try {
                $jsonResponse = $this->executeInternal($this->request);
                $this->searchResult = SearchResult::fromElasticsearchJsonResponse($jsonResponse, $this->contextNode, $this->contentRepositoryRegistry);
            } catch (\RuntimeException $exception) {
                if ($this->handleElasticsearchExceptions === 'throw') {
                    throw $exception;
                }

                $this->searchResult = SearchResult::error();
            }
        }
        return $this->searchResult;
    }

    /**
     * DO NOT USE THIS METHOD DIRECTLY; it is implemented to ensure Flowpack.Listable plays well with these objects here.
     *
     * @return int
     * @internal
     */
    public function count(): int
    {
        return $this->execute()->total();
    }

    /**
     * Returns the full request as it is sent to Elasticsearch; useful for debugging purposes.
     *
     * @return array<string,mixed>
     */
    public function requestForDebugging(): array
    {
        return $this->request;
    }

    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }
}
