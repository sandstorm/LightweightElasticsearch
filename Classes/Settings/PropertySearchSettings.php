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
readonly class PropertySearchSettings
{
    private function __construct(
        public string $propertyName,
        public string|null $propertyType,
        public string|false $indexingEelExpression,
        public string|false $fulltextExtractorEelExpression,
        public MappingDefinition|null $elasticsearchMapping
    ) {
    }

    /**
     * @internal only called in {@see NodeTypeSearchSettings}
     *
     * @param array<string,mixed> $propertyConfiguration
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
