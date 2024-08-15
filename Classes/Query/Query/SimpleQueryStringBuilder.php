<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query\Query;

use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
class SimpleQueryStringBuilder implements SearchQueryBuilderInterface
{
    /**
     * @var array<mixed>
     */
    private array $query = [
        'simple_query_string' => []
    ];

    public static function create(string $queryString): self
    {
        return new self($queryString);
    }

    private function __construct(string $queryString)
    {
        $this->query['simple_query_string']['query'] = $queryString;
    }

    /**
     * @param array<mixed> $fields
     */
    public function fields(array $fields): self
    {
        $this->query['simple_query_string']['fields'] = $fields;
        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function buildQuery(): array
    {
        return $this->query;
    }
}
