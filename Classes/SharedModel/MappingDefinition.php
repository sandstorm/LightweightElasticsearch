<?php

namespace Sandstorm\LightweightElasticsearch\SharedModel;

use Neos\Utility\Arrays;

class MappingDefinition implements \JsonSerializable
{
    public const NEOS_FULLTEXT_FIELD = 'neos_fulltext';
    public const NEOS_TYPE_FIELD = 'neos_type';

    /**
     * @param array<mixed> $payload
     */
    private function __construct(
        private readonly array $payload
    ) {
    }

    /**
     * @param array<mixed> $propertyMapping
     */
    public static function createWithProperties(array $propertyMapping): self
    {
        return new self([
            'properties' => $propertyMapping
        ]);
    }

    /**
     * @param array<mixed> $mapping
     */
    public static function fromArray(array $mapping): self
    {
        return new self($mapping);
    }

    /**
     * @param string $propertyName
     * @param array<mixed> $propertyMapping
     */
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
                self::NEOS_TYPE_FIELD => ['type' => 'keyword'],
                IndexDiscriminator::KEY => ['type' => 'keyword'],
            ]
        ]);
    }

    public function merge(MappingDefinition $other): self
    {
        return new self(Arrays::arrayMergeRecursiveOverrule($this->payload, $other->payload));
    }

    /**
     * @return \stdClass|array<mixed>
     */
    public function jsonSerialize(): \stdClass|array
    {
        return empty($this->payload) ? new \stdClass() : $this->payload;
    }
}
