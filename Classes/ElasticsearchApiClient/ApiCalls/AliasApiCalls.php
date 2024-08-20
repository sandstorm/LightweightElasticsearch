<?php

namespace Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls;

use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCaller;
use Sandstorm\LightweightElasticsearch\SharedModel\AliasName;
use Sandstorm\LightweightElasticsearch\SharedModel\ElasticsearchBaseUrl;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexName;

class AliasApiCalls
{
    /**
     * @return IndexName[]
     */
    public function indexNamesByAlias(ApiCaller $apiCaller, ElasticsearchBaseUrl $baseUrl, AliasName $aliasName): array
    {
        $response = $apiCaller->request('GET', $baseUrl->withPathSegment('_alias/' . $aliasName->value));
        $statusCode = $response->getStatusCode();
        if ($statusCode === 404) {
            // alias does not exist
            return [];
        }
        if ($statusCode !== 200) {
            throw new \RuntimeException('The alias "' . $aliasName->value . '" was not found with some unexpected error... (return code: ' . $statusCode . ')', 1383650137);
        }

        /** @var array<string,mixed> $contents */
        $contents = json_decode($response->getBody()->getContents(), true);
        return array_map(fn(string $indexName) => IndexName::fromString($indexName), array_keys($contents));
    }

    public function updateAliases(ApiCaller $apiCaller, ElasticsearchBaseUrl $baseUrl, AliasActionsBuilder $actions): void
    {
        $response = $apiCaller->request('POST', $baseUrl->withPathSegment('_aliases'), \json_encode(['actions' => $actions], JSON_THROW_ON_ERROR));
        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            throw new \RuntimeException('The aliases could not be updated - (return code: ' . $statusCode . ')', 1693487423);
        }
    }
}
