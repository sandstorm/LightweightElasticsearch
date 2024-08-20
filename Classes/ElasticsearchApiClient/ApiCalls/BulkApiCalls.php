<?php

namespace Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls;

use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCaller;
use Sandstorm\LightweightElasticsearch\SharedModel\ElasticsearchBaseUrl;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexName;

class BulkApiCalls
{
    /**
     * @param array<mixed> $payloadLines
     */
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
