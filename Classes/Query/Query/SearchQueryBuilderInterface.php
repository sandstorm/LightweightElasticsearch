<?php


namespace Sandstorm\LightweightElasticsearch\Query\Query;

interface SearchQueryBuilderInterface
{
    public function buildQuery(): array;
}
