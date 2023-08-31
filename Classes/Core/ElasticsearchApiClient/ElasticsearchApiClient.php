<?php

namespace Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient;

use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCalls\AliasActionsBuilder;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCalls\AliasApiCalls;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCalls\BulkApiCalls;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCalls\IndexApiCalls;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCalls\SearchApiCalls;
use Sandstorm\LightweightElasticsearch\Core\Settings\CreateIndexParameters;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\AliasName;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\ElasticsearchBaseUrl;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\IndexName;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\Mapping\MappingDefinition;

/**
 * In case different elasticsearch versions need to be supported, we can dispatch here to different API commands
 *
 * @internal only called from within the indexing / search process.
 */
#[Flow\Proxy(false)]
class ElasticsearchApiClient
{
    public function __construct(
        private readonly ElasticsearchBaseUrl $baseUrl,
        private readonly ApiCaller $apiCaller,
        private readonly AliasApiCalls $aliasApi,
        private readonly IndexApiCalls $indexApi,
        private readonly BulkApiCalls $bulkApi,
        private readonly SearchApiCalls $searchApi,
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

    public function search(array $aliasNames, array $searchRequest): array
    {
        foreach ($aliasNames as $alias) {
            if (!$alias instanceof AliasName) {
                throw new \RuntimeException('alias is no AliasName, but ' . get_class($alias), 1693488722);
            }
        }
        return $this->searchApi->search($this->apiCaller, $this->baseUrl, $aliasNames, $searchRequest);
    }

    /**
     * In case the alias does not exist, an empty list is returned.
     *
     * @param AliasName $aliasName
     * @return IndexName[]
     */
    public function indexNamesByAlias(AliasName $aliasName): array
    {
        return $this->aliasApi->indexNamesByAlias($this->apiCaller, $this->baseUrl, $aliasName);
    }

    public function updateAliases(AliasActionsBuilder $actions): void
    {
        $this->aliasApi->updateAliases($this->apiCaller, $this->baseUrl, $actions);
    }
}
