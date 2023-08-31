<?php

namespace Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient;

use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCalls\BulkApiCalls;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCalls\IndexApiCalls;
use Sandstorm\LightweightElasticsearch\Core\Settings\CreateIndexParameters;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\ElasticsearchBaseUrl;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\IndexName;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\Mapping\MappingDefinition;

/**
 * In case different elasticsearch versions need to be supported, this is dispatched here to different API commands
 *
 * @internal only called from within the indexing / search process.
 */
class ElasticsearchApiClient
{
    public function __construct(
        private readonly ElasticsearchBaseUrl $baseUrl,
        private readonly ApiCaller $apiCaller,
        private readonly IndexApiCalls $indexApi,
        private readonly BulkApiCalls $bulkApi,
    ) {
    }

    public function hasIndex(IndexName $indexName): bool
    {
        return $this->indexApi->hasIndex($this->apiCaller, $this->baseUrl, $indexName);
    }

    public function removeIndex(IndexName $indexName): void
    {
        $this->indexApi->removeIndex($this->apiCaller, $this->baseUrl, $indexName);
    }

    public function createIndex(IndexName $indexName, CreateIndexParameters $createIndexParameters): void
    {
        $this->indexApi->createIndex($this->apiCaller, $this->baseUrl, $indexName, $createIndexParameters);
    }

    public function updateMapping(IndexName $indexName, MappingDefinition $mappingDefinition): void
    {
        $this->indexApi->updateMapping($this->apiCaller, $this->baseUrl, $indexName, $mappingDefinition);
    }

    public function bulkIndex(IndexName $indexName, array $payloadLines): void
    {
        $this->bulkApi->bulkIndex($this->apiCaller, $this->baseUrl, $indexName, $payloadLines);
    }
}
