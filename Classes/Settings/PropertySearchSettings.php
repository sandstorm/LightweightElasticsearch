<?php

namespace Sandstorm\LightweightElasticsearch\Settings;

use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\SharedModel\MappingDefinition;

/**
 * Accessor for all search-related configuration options of a Property of a Node Type.
 *
 * This is a part of {@see NodeTypeSearchSettings}.
 */
#[Flow\Proxy(false)]
class PropertySearchSettings
{
    private function __construct(
        public readonly string $propertyName,
        public readonly string|null $propertyType,

        public readonly string|false $indexingEelExpression,
        public readonly string|false $fulltextExtractorEelExpression,
        public readonly MappingDefinition|null $elasticsearchMapping
    ) {
    }

    /**
     * @internal only called in {@see NodeTypeSearchSettings}
     */
    public static function fromNodeTypeConfiguration(string $propertyName, array $propertyConfiguration, DefaultConfigurationPerType $defaultConfigurationPerType): self
    {
        $indexingEelExpression = $propertyConfiguration['search']['indexing']
            ?? $defaultConfigurationPerType->getIndexingEelExpression($propertyConfiguration['type'] ?? null)
            ?? false;

        $fulltextExtractorEelExpression = $propertyConfiguration['search']['fulltextExtractor']
            ?? $defaultConfigurationPerType->getFulltextExtractorEelExpression($propertyConfiguration['type'] ?? null)
            ?? false;

        return new self(
            propertyName: $propertyName,
            propertyType: $propertyConfiguration['type'] ?? null,
            indexingEelExpression: $indexingEelExpression,
            fulltextExtractorEelExpression: $fulltextExtractorEelExpression,
            elasticsearchMapping: (isset($propertyConfiguration['search']['elasticSearchMapping']) && is_array($propertyConfiguration['search']['elasticSearchMapping']))
                ? MappingDefinition::forProperty($propertyName, $propertyConfiguration['search']['elasticSearchMapping'])
                : null,
        );
    }

    public function isIndexingEnabled(): bool
    {
        return $this->indexingEelExpression !== false && $this->indexingEelExpression !== '';
    }

    public function hasFulltextExtractor(): bool
    {
        return $this->fulltextExtractorEelExpression !== false && $this->fulltextExtractorEelExpression !== '';
    }
}
