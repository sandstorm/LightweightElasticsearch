<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query;

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;

/**
 * See https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-bool-query.html for a reference on the fields
 * @Flow\Proxy(false)
 */
class BooleanQueryBuilder implements ProtectedContextAwareInterface, SearchQueryBuilderInterface
{
    private function __construct()
    {
    }

    public static function create(): self
    {
        return new self();
    }

    private array $query = [
        'bool' => []
    ];

    /**
     * Add a query to the "must" part of the Bool query. This query must ALWAYS match for a document to be included in the results.
     *
     * @param SearchQueryBuilderInterface $query
     * @return $this
     */
    public function must(SearchQueryBuilderInterface $query): self
    {
        $this->query['bool']['must'][] = $query->buildQuery();
        return $this;
    }

    /**
     * Add a query to the "should" part of the Bool query.
     *
     * The "minimum_should_match" property defines the number or percentage of should clauses returned documents must match.
     * If the bool query includes at least one should clause and no must or filter clauses, the default value is 1. Otherwise, the default value is 0.
     *
     * @param SearchQueryBuilderInterface $query
     * @return $this
     */
    public function should(SearchQueryBuilderInterface $query): self
    {
        $this->query['bool']['should'][] = $query->buildQuery();
        return $this;
    }

    /**
     * Add a query to the "must_not" part of the Bool query. This query must NEVER match for a document to be included in the results.
     *
     * @param SearchQueryBuilderInterface $query
     * @return $this
     */
    public function mustNot(SearchQueryBuilderInterface $query): self
    {
        $this->query['bool']['must_not'][] = $query->buildQuery();
        return $this;
    }

    /**
     * Add a query to the "filter" part of the Bool query. This query must ALWAYS match for a document to be included in the results; and ranking information is discarded.
     *
     * @param SearchQueryBuilderInterface $query
     * @return $this
     */
    public function filter(SearchQueryBuilderInterface $query): self
    {
        $this->query['bool']['filter'][] = $query->buildQuery();
        return $this;
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }

    public function buildQuery(): array
    {
        return $this->query;
    }
}
