<?php

namespace Sandstorm\LightweightElasticsearch\SharedModel;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;

/**
 * This value object is used to separate different data types in the same query; i.e. between Neos Nodes
 * and other (custom) values.
 */
final class IndexDiscriminator
{
    // how is the discriminator column named
    const KEY = 'index_discriminator';

    const NEOS_NODES = 'neos_nodes';

    private function __construct(
        public readonly string $value
    ) {
    }

    public static function createForCustomIndex(string $value)
    {
        return new self($value);
    }
}
