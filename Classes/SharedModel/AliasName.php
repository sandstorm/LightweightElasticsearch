<?php

namespace Sandstorm\LightweightElasticsearch\SharedModel;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;

final class AliasName
{
    private function __construct(
        public readonly string $value
    ) {
    }

    public static function createForWorkspaceAndDimensionSpacePoint(IndexNamePrefix $prefix, ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, DimensionSpacePoint $dimensionSpacePoint): AliasName
    {
        return new self($prefix->value . '-' . $contentRepositoryId->value . '-' . $workspaceName->value . '-' . $dimensionSpacePoint->hash);
    }

    public static function createForCustomIndex(string $aliasName): AliasName
    {
        return new self($aliasName);
    }
}
