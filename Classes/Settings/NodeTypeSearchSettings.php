<?php

namespace Sandstorm\LightweightElasticsearch\Settings;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Sandstorm\LightweightElasticsearch\SharedModel\MappingDefinition;

/**
 * Accessor for all search-related configuration options of a {@see NodeType}.
 */
#[Flow\Proxy(false)]
class NodeTypeSearchSettings
{
    private function __construct(
        public readonly bool $isIndexed,
        public readonly bool $isFulltextRoot,
        public readonly bool $isFulltextEnabled,
        public readonly MappingDefinition|null $elasticsearchMapping,
        /**
         * @var PropertySearchSettings[]
         */
        public readonly array $properties,
    )
    {
    }

    public static function fromNodeType(NodeType $nodeType, DefaultConfigurationPerType $defaultConfigurationPerType): self
    {
        $configuration = $nodeType->getFullConfiguration();
        return new self(
            isIndexed: $configuration['search']['isIndexed'] ?? false,
            isFulltextRoot: $configuration['search']['fulltext']['isRoot'] ?? false,
            isFulltextEnabled: $configuration['search']['fulltext']['enable'] ?? false,
            elasticsearchMapping: isset($configuration['search']['elasticSearchMapping']) ? MappingDefinition::fromArray($configuration['search']['elasticSearchMapping']) : null,
            properties: self::prepareProperties($nodeType, $defaultConfigurationPerType),
        );
    }

    /**
     * @return PropertySearchSettings[]
     */
    private static function prepareProperties(NodeType $nodeType, DefaultConfigurationPerType $defaultConfigurationPerType): array
    {
        $result = [];
        foreach ($nodeType->getProperties() as $propertyName => $propertyConfiguration) {
            $result[] = PropertySearchSettings::fromNodeTypeConfiguration($propertyName, $propertyConfiguration, $defaultConfigurationPerType);
        }
        return $result;
    }
}
