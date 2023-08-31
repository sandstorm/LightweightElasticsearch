<?php

namespace Sandstorm\LightweightElasticsearch\SharedModel;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;

final class AliasName implements \JsonSerializable
{
    private function __construct(
        public readonly string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }

    public static function createForWorkspaceAndDimensionSpacePoint(IndexNamePrefix $prefix, ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, DimensionSpacePoint $dimensionSpacePoint)
    {

        return self::fromString($prefix->value . '-' . $contentRepositoryId->value . '-' . $workspaceName->value . '-' . $dimensionSpacePoint->hash);
    }


    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function equals(AliasName $other): bool
    {
        return $this->value === $other->value;
    }
}
