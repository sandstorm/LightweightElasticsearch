<?php

namespace Sandstorm\LightweightElasticsearch\SharedModel;


use Traversable;

final class IndexNames implements \IteratorAggregate
{
    private function __construct(
        /**
         * @var IndexName[]
         */
        public readonly array $names
    ) {
    }

    public static function fromArray(array $indices)
    {
        $names = [];
        foreach ($indices as $indexName) {
            $names[] = IndexName::fromString($indexName);
        }

        return new self($names);
    }

    /**
     * @return Traversable<IndexName>
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->names);
    }
}
