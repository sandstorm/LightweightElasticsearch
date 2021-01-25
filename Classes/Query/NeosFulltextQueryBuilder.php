<?php


namespace Sandstorm\LightweightElasticsearch\Query;


use Neos\ContentRepository\Domain\Model\NodeInterface;

class NeosFulltextQueryBuilder extends BooleanQueryBuilder
{
    public function __construct(NodeInterface $contextNode)
    {

    }

    public function fulltext(string $query)
    {

    }
}
