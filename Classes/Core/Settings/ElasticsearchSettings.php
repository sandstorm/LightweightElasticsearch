<?php

namespace Sandstorm\LightweightElasticsearch\Core\Settings;

use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\ElasticsearchBaseUrl;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\IndexNamePrefix;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\Mapping\DefaultMappingConfigurationPerType;

#[Flow\Proxy(false)]
class ElasticsearchSettings
{


    private function __construct(
        public readonly IndexNamePrefix $indexNamePrefix,
        public readonly int $transferConnectionTimeout,
        public readonly ElasticsearchBaseUrl $baseUrl,
        public readonly bool $transferSslVerifyPeer,
        public readonly bool $transferSslVerifyHost,
        public readonly DefaultMappingConfigurationPerType $defaultConfigurationPerType,
    ) {
    }

    public static function fromArray(array $settings): self
    {
        return new self(
            // OLD: @Flow\InjectConfiguration(path="elasticSearch.indexName", package="Neos.ContentRepository.Search")
            indexNamePrefix: IndexNamePrefix::fromString($settings['elasticsearch']['indexName'] ?? 'index'),
            // OLD: @Flow\InjectConfiguration(path="transfer.connectionTimeout", package="Flowpack.Elasticsearch")
            transferConnectionTimeout: $settings['elasticsearch']['connectionTimeout'] ?? 1,
            // TODO where old config?
            baseUrl: ElasticsearchBaseUrl::fromString($settings['elasticsearch']['baseUrl'] ?? throw new \RuntimeException('Base URL not set')),
            // OLD: @Flow\InjectConfiguration(path="transfer.sslVerifyPeer", package="Flowpack.Elasticsearch")
            transferSslVerifyPeer: $settings['transfer']['sslVerifyPeer'] ?? true,
            // OLD: @Flow\InjectConfiguration(path="transfer.sslVerifyHost", package="Flowpack.Elasticsearch")
            transferSslVerifyHost: $settings['transfer']['sslVerifyHost'] ?? true,

            // OLD: @Flow\InjectConfiguration(package="Neos.ContentRepository.Search", path="defaultConfigurationPerType")
            defaultConfigurationPerType: DefaultMappingConfigurationPerType::fromArray($settings['defaultConfigurationPerType'] ?? []),
        );
    }

    public function createIndexParameters(\Sandstorm\LightweightElasticsearch\Core\SharedModel\IndexName $indexName): CreateIndexParameters
    {
        // Settings of Flowpack.Elasticsearch
        // if ($this->client instanceof Client) {
        //            $path = 'indexes.' . $this->client->getBundle() . '.' . $this->settingsKey;
        //        } else {
        //            $path = 'indexes.default' . '.' . $this->settingsKey;
        //        }
        //
        //        $configuration = Arrays::getValueByPath($this->settings, $path);
        return new CreateIndexParameters();
    }
}
