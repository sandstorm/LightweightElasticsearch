<?php


namespace Sandstorm\LightweightElasticsearch\Query\Query;

interface SearchQueryBuilderInterface
{
    /**
     * @return array<mixed>
     */
    public function buildQuery(): array;
}
