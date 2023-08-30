<?php

namespace Sandstorm\LightweightElasticsearch\Core\SharedModel;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;

final class IndexName implements \JsonSerializable
{
    private function __construct(
        public readonly string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }

    public static function createForWorkspaceAndDimensionSpacePoint(IndexNamePrefix $prefix, WorkspaceName $workspaceName, DimensionSpacePoint $dimensionSpacePoint, IndexPostfix $postfix)
    {

        return self::fromString($prefix->value . '-' . $workspaceName->value . '-' . $dimensionSpacePoint->hash . '-' . $postfix->value);
    }


    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function equals(IndexName $other): bool
    {
        return $this->value === $other->value;
    }
}
