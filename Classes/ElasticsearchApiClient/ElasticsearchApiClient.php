<?php

namespace Sandstorm\LightweightElasticsearch\ElasticsearchApiClient;

use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls\AliasActionsBuilder;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls\AliasApiCalls;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls\BulkApiCalls;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls\IndexApiCalls;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls\IngestPipelineApiCalls;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls\SearchApiCalls;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls\SystemApiCalls;
use Sandstorm\LightweightElasticsearch\Settings\CreateIndexParameters;
use Sandstorm\LightweightElasticsearch\SharedModel\AliasName;
use Sandstorm\LightweightElasticsearch\SharedModel\ElasticsearchBaseUrl;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexName;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexNames;
use Sandstorm\LightweightElasticsearch\SharedModel\MappingDefinition;

/**
 * In case different elasticsearch versions need to be supported, we can dispatch here to different API commands
 *
 * @internal only called from within the indexing / search process.
 */
#[Flow\Proxy(false)]
readonly class ElasticsearchApiClient
{
    public function __construct(
        private ElasticsearchBaseUrl $baseUrl,
        private ApiCaller $apiCaller,
        private AliasApiCalls $aliasApi,
        private IndexApiCalls $indexApi,
        private BulkApiCalls $bulkApi,
        private IngestPipelineApiCalls $ingestPipelineApi,
        private SearchApiCalls $searchApi,
        private SystemApiCalls $systemApi,
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

    /**
     * @param array<mixed> $payloadLines
     */
    public function bulkIndex(IndexName $indexName, array $payloadLines): void
    {
        $this->bulkApi->bulkIndex($this->apiCaller, $this->baseUrl, $indexName, $payloadLines);
    }

    public function getAllIndexNames(): IndexNames
    {
        $stats = $this->systemApi->stats($this->apiCaller, $this->baseUrl);
        $indexNames = array_keys($stats['indices']);
        asort($indexNames);
        return IndexNames::fromArray($indexNames);
    }

    /**
     * @param array<mixed> $aliasNames
     * @param array<mixed> $searchRequest
     * @return array<mixed>
     */
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

    /**
     * @param array<mixed> $request
     * @return array<mixed>
     */
    public function ingestPipelineSimulate(array $request): array
    {
        return $this->ingestPipelineApi->simulate($this->apiCaller, $this->baseUrl, $request);
    }
}
