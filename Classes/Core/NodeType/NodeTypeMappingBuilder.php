<?php

namespace Sandstorm\LightweightElasticsearch\Core\NodeType;

use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\Mapping\DefaultMappingConfigurationPerType;

class NodeTypeMappingBuilder
{

    public function __construct(
        private readonly DefaultMappingConfigurationPerType $defaultConfigurationPerType
    )
    {

    }

    public function build(NodeTypeManager $nodeTypeManager, LoggerInterface $logger): MappingDefinition
    {
        $mapping = MappingDefinition::empty();
        foreach ($nodeTypeManager->getNodeTypes() as $nodeTypeName => $nodeType) {
            assert($nodeType instanceof NodeType);
            if ($nodeTypeName === 'unstructured' || $nodeType->isAbstract()) {
                continue;
            }

            $nodeTypeConfiguration = $nodeType->getFullConfiguration();
            // we only want to index fulltext roots
            if (!($nodeTypeConfiguration['search']['fulltext']['isRoot'] ?? false)) {
                continue;
            }

            if (isset($nodeTypeConfiguration['search']['elasticSearchMapping'])) {
                $mapping = $mapping->merge(MappingDefinition::fromArray($nodeTypeConfiguration['search']['elasticSearchMapping']));
            }

            foreach ($nodeType->getProperties() as $propertyName => $propertyConfiguration) {
                // This property is configured to not be index, so do not add a mapping for it
                if (isset($propertyConfiguration['search']) && array_key_exists('indexing', $propertyConfiguration['search']) && $propertyConfiguration['search']['indexing'] === false) {
                    $logger->info('Node Type "' . $nodeTypeName . '" - property "' . $propertyName . '": Indexing disabled (search.indexing=false)');
                    continue;
                }

                if (isset($propertyConfiguration['search']['elasticSearchMapping'])) {
                    if (is_array($propertyConfiguration['search']['elasticSearchMapping'])) {
                        $logger->info('Node Type "' . $nodeTypeName . '" - property "' . $propertyName . '": found ElasticSearch Mapping in Node property.');
                        $propertyMapping = array_filter($propertyConfiguration['search']['elasticSearchMapping'], static function ($value) {
                            return $value !== null;
                        });
                        $mapping = $mapping->merge(MappingDefinition::forProperty($propertyName, $propertyMapping));
                    }
                } elseif (isset($propertyConfiguration['type']) && $this->defaultConfigurationPerType->hasMapping($propertyConfiguration['type'])) {
                    $logger->info('Node Type "' . $nodeTypeName . '" - property "' . $propertyName . '": found ElasticSearch Mapping in default configuration.');
                    $mapping = $mapping->merge(MappingDefinition::forProperty($propertyName, $this->defaultConfigurationPerType->getMapping($propertyConfiguration['type'])));
                } else {
                    $logger->info('Node Type "' . $nodeTypeName . '" - property "' . $propertyName . '": No ElasticSearch Mapping found.');
                }
            }
        }
        return $mapping;
    }
}
