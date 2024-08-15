<?php

namespace Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls;

use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCaller;
use Sandstorm\LightweightElasticsearch\SharedModel\AliasName;
use Sandstorm\LightweightElasticsearch\SharedModel\ElasticsearchBaseUrl;

class SearchApiCalls
{
    /**
     * @param array<mixed> $aliasNames
     * @param array<mixed> $searchRequest
     * @return array<mixed>
     */
    public function search(ApiCaller $apiCaller, ElasticsearchBaseUrl $baseUrl, array $aliasNames, array $searchRequest): array
    {
        $aliasNamesString = implode(',', array_map(fn(AliasName $aliasName) => $aliasName->value, $aliasNames));
        $response = $apiCaller->request('GET', $baseUrl->withPathSegment($aliasNamesString . '/_search'), json_encode($searchRequest, JSON_THROW_ON_ERROR));

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Error creating index: ' . $response->getBody()->getContents(), 1693488943);
        }
        return json_decode($response->getBody()->getContents(), true);
    }
}
