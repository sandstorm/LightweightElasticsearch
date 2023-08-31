<?php

namespace Sandstorm\LightweightElasticsearch\Core\SharedModel;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;

final class ElasticsearchBaseUrl
{

    private function __construct(
        public readonly string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        return new self(rtrim($value, '/'));
    }

    public function withPathSegment(string $pathSegment) : UriInterface
    {
        return new Uri($this->value . '/' . ltrim($pathSegment, '/'));
    }
}
