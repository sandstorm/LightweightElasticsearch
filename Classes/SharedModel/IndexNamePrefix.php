<?php

namespace Sandstorm\LightweightElasticsearch\SharedModel;

final class IndexNamePrefix implements \JsonSerializable
{
    private function __construct(
        public readonly string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }


    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function equals(IndexNamePrefix $other): bool
    {
        return $this->value === $other->value;
    }
}
