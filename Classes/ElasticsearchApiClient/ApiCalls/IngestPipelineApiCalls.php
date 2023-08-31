<?php

namespace Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls;

use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCaller;
use Sandstorm\LightweightElasticsearch\SharedModel\ElasticsearchBaseUrl;

class IngestPipelineApiCalls
{
    public function simulate(ApiCaller $apiCaller, ElasticsearchBaseUrl $baseUrl, array $request): array
    {
        $response = $apiCaller->request('POST', $baseUrl->withPathSegment('_ingest/pipeline/_simulate'), json_encode($request));
        return json_decode($response->getBody()->getContents(), true);
    }
}
