<?php

namespace Sandstorm\LightweightElasticsearch\Core\SharedModel;

final class ElasticsearchRequestUrl
{

    private function __construct(
        public readonly string $value
    ) {
    }

    public static function fromBaseUrlAndString(ElasticsearchBaseUrl $baseUrl, string $value): self
    {
        return new self($baseUrl->value . $value);
    }
}
