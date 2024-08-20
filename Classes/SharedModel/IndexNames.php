<?php

declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\SharedModel;

/**
 * @implements \IteratorAggregate<IndexName>
 */
final readonly class IndexNames implements \IteratorAggregate
{
    /**
     * @var array<IndexName>
     */
    private array $items;

    private function __construct(IndexName ...$items)
    {
        $this->items = $items;
    }

    /**
     * @param array<string> $indexNames
     */
    public static function fromArray(array $indexNames): self
    {
        return new self(...array_map(
            fn (string $indexName) => IndexName::fromString($indexName),
            $indexNames
        ));
    }

    /**
     * @return \Traversable<IndexName>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->items;
    }
}
