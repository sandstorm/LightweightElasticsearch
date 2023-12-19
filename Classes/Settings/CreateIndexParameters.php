<?php

namespace Sandstorm\LightweightElasticsearch\Settings;

/**
 * Corresponds to https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-create-index.html#indices-create-api-request-body
 *
 * @internal - detail of {@see ElasticsearchSettings}
 */
class CreateIndexParameters implements \JsonSerializable
{

    private array $settings = [];
    private array $aliases = [];
    private array $mappings = [];

    public function jsonSerialize(): mixed
    {
        return [
            'settings' => empty($this->settings) ? new \stdClass() : $this->settings,
            'aliases' => empty($this->aliases) ? new \stdClass() : $this->aliases,
            'mappings' => empty($this->mappings) ? new \stdClass() : $this->mappings
        ];
    }
}
