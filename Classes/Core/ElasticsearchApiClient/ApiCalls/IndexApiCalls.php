<?php

namespace Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCalls;

use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCaller;
use Sandstorm\LightweightElasticsearch\Core\Settings\CreateIndexParameters;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\ElasticsearchBaseUrl;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\IndexName;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\Mapping\MappingDefinition;

class IndexApiCalls
{

    public function hasIndex(ApiCaller $apiCaller, ElasticsearchBaseUrl $baseUrl, IndexName $indexName): bool
    {
        $response = $apiCaller->request('HEAD', $baseUrl->withPathSegment($indexName->value));
        return $response->getStatusCode() === 200;
    }

    public function removeIndex(ApiCaller $apiCaller, ElasticsearchBaseUrl $baseUrl, IndexName $indexName)
    {
        $apiCaller->request('DELETE', $baseUrl->withPathSegment($indexName->value));
    }

    /**
     * https://www.elastic.co/guide/en/elasticsearch/reference/current/indices-create-index.html
     */
    public function createIndex(ApiCaller $apiCaller, ElasticsearchBaseUrl $baseUrl, IndexName $indexName, CreateIndexParameters $createIndexParameters)
    {
        $response = $apiCaller->request('PUT', $baseUrl->withPathSegment($indexName->value), json_encode($createIndexParameters));
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Error creating index: ' . $response->getBody()->getContents(), 1693401613);
        }
    }

    public function updateMapping(ApiCaller $apiCaller, ElasticsearchBaseUrl $baseUrl, IndexName $indexName, MappingDefinition $mappingDefinition)
    {
        $response = $apiCaller->request('PUT', $baseUrl->withPathSegment($indexName->value . '/_mapping'), json_encode($mappingDefinition));
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Error updating mapping: ' . $response->getBody()->getContents(), 1693401631);
        }
    }
}
