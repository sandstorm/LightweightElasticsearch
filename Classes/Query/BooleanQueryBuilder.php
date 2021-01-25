<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query;


use Neos\Eel\ProtectedContextAwareInterface;

class BooleanQueryBuilder implements ProtectedContextAwareInterface
{
    protected array $query = [];

    public function should(SearchQueryBuilderInterface $query): self
    {
        $this->query['should'][] = $query->buildQuery();
        return $this;
    }

    public function must(SearchQueryBuilderInterface $query): self
    {
        $this->query['must'][] = $query->buildQuery();
        return $this;
    }

    public function mustNot(SearchQueryBuilderInterface $query): self
    {
        $this->query['must_not'][] = $query->buildQuery();
        return $this;
    }

    public function filter(SearchQueryBuilderInterface $query): self
    {
        $this->query['filter'][] = $query->buildQuery();
        return $this;
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
