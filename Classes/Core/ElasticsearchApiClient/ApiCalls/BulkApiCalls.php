<?php

namespace Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCalls;

use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCaller;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\ElasticsearchBaseUrl;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\IndexName;

class BulkApiCalls
{
    public function bulkIndex(ApiCaller $apiCaller, ElasticsearchBaseUrl $baseUrl, IndexName $indexName, array $payloadLines): void
    {
        if (count($payloadLines) === 0) {
            //return;
        }
        // Bulk request MUST end with line return
        $requestBody = implode("\n", $payloadLines) . "\n";
        $response = $apiCaller->request('POST', $baseUrl->withPathSegment($indexName->value . '/_bulk'), $requestBody);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Error running bulk request: ' . $response->getBody()->getContents(), 1693471824);
        }
    }
}
