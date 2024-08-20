<?php

namespace Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls;

use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCaller;
use Sandstorm\LightweightElasticsearch\SharedModel\ElasticsearchBaseUrl;

class SystemApiCalls
{
    /**
     * @return array<mixed>
     */
    public function stats(ApiCaller $apiCaller, ElasticsearchBaseUrl $baseUrl): array
    {
        $response = $apiCaller->request('GET', $baseUrl->withPathSegment('_stats'));
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Error getting status code: ' . $response->getBody()->getContents(), 1701346286);
        }
        return json_decode($response->getBody()->getContents(), true);
    }
}
