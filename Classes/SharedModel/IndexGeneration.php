<?php

declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\SharedModel;

/**
 * A timestamp when the index was created. Only the newest timestamp stays.
 */
final class IndexGeneration implements \JsonSerializable
{
    public const PATTERN = '/^[0-9]+$/';

    private function __construct(
        public readonly string $value
    ) {
        if (preg_match(self::PATTERN, $this->value) !== 1) {
            throw new \InvalidArgumentException(
                'Invalid Index Postfix "' . $this->value
                . '" (a node name must only contain lowercase characters, numbers and the "-" sign).',
                1693390462
            );
        }
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }

    public static function createFromCurrentTime(): self
    {
        return new self((string)time());
    }


    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function equals(IndexGeneration $other): bool
    {
        return $this->value === $other->value;
    }
}
