<?php

namespace Sandstorm\LightweightElasticsearch\Core\NodeType;

use Neos\Utility\Arrays;

class MappingDefinition implements \JsonSerializable
{
    private function __construct(private readonly array $payload)
    {
    }

    public static function createWithProperties(array $propertyMapping): self
    {
        return new self([
            'properties' => $propertyMapping
        ]);
    }

    public static function fromArray(array $mapping): self
    {
        return new self($mapping);
    }

    public static function forProperty(string $propertyName, array $propertyMapping): self
    {
        return self::createWithProperties([
            $propertyName => $propertyMapping
        ]);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function merge(MappingDefinition $other): self
    {
        return new self(Arrays::arrayMergeRecursiveOverrule($this->payload, $other->payload));
    }

    public function jsonSerialize(): \stdClass|array
    {
        return empty($this->payload) ? new \stdClass() : $this->payload;
    }
}
