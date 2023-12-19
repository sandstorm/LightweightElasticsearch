<?php

namespace Sandstorm\LightweightElasticsearch\Settings;


use Sandstorm\LightweightElasticsearch\SharedModel\MappingDefinition;

/**
 * Wrapper around defaultConfigurationPerType in Settings.
 *
 * @internal - only used as implementation detail in {@see ElasticsearchSettings}.
 */
final class DefaultConfigurationPerType
{

    private function __construct(
        private readonly array $configuration
    ) {
    }

    public static function fromArray(array $value): self
    {
        return new self($value);
    }

    public function hasMapping(PropertySearchSettings $propertySearchSettings): bool
    {
        $propertyType = $propertySearchSettings->propertyType;
        if ($propertyType === null) {
            return false;
        }
        return isset($this->configuration[$propertyType]['elasticSearchMapping']) && is_array($this->configuration[$propertyType]['elasticSearchMapping']);
    }
    public function getMapping(PropertySearchSettings $propertySearchSettings): MappingDefinition
    {
        $defaultMapping = $this->configuration[$propertySearchSettings->propertyType]['elasticSearchMapping'];
        return MappingDefinition::forProperty($propertySearchSettings->propertyName, $defaultMapping);
    }

    public function getIndexingEelExpression(string|null $propertyType): ?string
    {
        if ($propertyType === null) {
            return null;
        }

        return $this->configuration[$propertyType]['indexing'] ?? null;
    }

    public function getFulltextExtractorEelExpression(string|null $propertyType): ?string
    {
        if ($propertyType === null) {
            return null;
        }

        return $this->configuration[$propertyType]['fulltextExtractor'] ?? null;
    }
}
