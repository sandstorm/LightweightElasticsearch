<?php


namespace Sandstorm\LightweightElasticsearch\Query;


interface SearchQueryBuilderInterface
{
    public function buildQuery(): array;
}
