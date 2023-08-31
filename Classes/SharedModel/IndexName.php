<?php

namespace Sandstorm\LightweightElasticsearch\SharedModel;


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
