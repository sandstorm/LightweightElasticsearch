<?php

namespace Sandstorm\LightweightElasticsearch\NodeTypeMapping;

use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\Settings\DefaultConfigurationPerType;
use Sandstorm\LightweightElasticsearch\Settings\NodeTypeSearchSettings;
use Sandstorm\LightweightElasticsearch\SharedModel\MappingDefinition;

class NodeTypeMappingBuilder
{

    public function __construct(
        private readonly DefaultConfigurationPerType $defaultConfigurationPerType
    ) {
    }

    public function build(NodeTypeManager $nodeTypeManager, LoggerInterface $logger): MappingDefinition
    {
        $mapping = MappingDefinition::empty();
        foreach ($nodeTypeManager->getNodeTypes() as $nodeTypeName => $nodeType) {
            assert($nodeType instanceof NodeType);
            if ($nodeTypeName === 'unstructured' || $nodeType->isAbstract()) {
                continue;
            }
            $nodeTypeSearchSettings = NodeTypeSearchSettings::fromNodeType($nodeType, $this->defaultConfigurationPerType);

            // we only want to index fulltext roots
            // TODO: if we want to make this package behave like the "old" Elasticsearch package, we need to get rid of this restriction
            if (!$nodeTypeSearchSettings->isFulltextRoot) {
                continue;
            }

            if ($nodeTypeSearchSettings->elasticsearchMapping) {
                $mapping = $mapping->merge($nodeTypeSearchSettings->elasticsearchMapping);
            }
            $logger->debug('Node Type "' . $nodeTypeName . '":');

            foreach ($nodeTypeSearchSettings->properties as $propertySearchSettings) {
                if (!$propertySearchSettings->isIndexingEnabled()) {
                    // This property is configured to not be indexed, so do not add a mapping for it
                    continue;
                }

                if ($propertySearchSettings->elasticsearchMapping) {
                    $logger->debug('  Property "' . $propertySearchSettings->propertyName . '": found ElasticSearch Mapping in Node property.');
                    $mapping = $mapping->merge($propertySearchSettings->elasticsearchMapping);
                } elseif ($this->defaultConfigurationPerType->hasMapping($propertySearchSettings)) {
                    $logger->debug('  Node Type "' . $nodeTypeName . '" - property "' . $propertySearchSettings->propertyName . '": found ElasticSearch Mapping in default configuration.');
                    $mapping = $mapping->merge($this->defaultConfigurationPerType->getMapping($propertySearchSettings));
                }
            }
        }
        return $mapping;
    }
}
