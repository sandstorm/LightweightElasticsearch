<?php

namespace Sandstorm\LightweightElasticsearch\Core\SharedModel;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
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

    public static function createForAlias(AliasName $aliasName, IndexGeneration $indexGeneration)
    {
        return self::fromString($aliasName->value . '__' . $indexGeneration->value);
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
