<?php

namespace Sandstorm\LightweightElasticsearch\Core\SharedModel\Mapping;

use Neos\Utility\Arrays;
use Sandstorm\LightweightElasticsearch\Core\NodeTypeMapping\NodeTypeMappingBuilder;

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
        // filter out null values
        $propertyMapping = array_filter($propertyMapping, fn($value) => $value !== null);
        return self::createWithProperties([
            $propertyName => $propertyMapping
        ]);
    }

    public static function empty(): self
    {
        return new self([
            'properties' => [
                NodeTypeMappingBuilder::NEOS_TYPE_FIELD => ['type' => 'keyword'],
            ]
        ]);
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
