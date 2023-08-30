<?php

namespace Sandstorm\LightweightElasticsearch\Core\SharedModel\Mapping;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;

final class DefaultMappingConfigurationPerType
{

    private function __construct(
        private readonly array $configuration
    ) {
    }

    public static function fromArray(array $value): self
    {
        return new self($value);
    }

    public function hasMapping(string $type): bool
    {
        return is_array($this->configuration[$type]['elasticSearchMapping']);
    }
    public function getMapping(string $type): array
    {
        return $this->configuration[$type]['elasticSearchMapping'];
    }
}
