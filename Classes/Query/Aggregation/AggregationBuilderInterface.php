<?php


namespace Sandstorm\LightweightElasticsearch\Query\Aggregation;

use Sandstorm\LightweightElasticsearch\Query\AggregationRequestBuilder;

interface AggregationBuilderInterface
{
    /**
     * Returns the Elasticsearch aggregation request part; so the part inside {"aggs": ...}.
     *
     * Is called by the framework (usually inside {@see AggregationRequestBuilder}, not by the end-user.
     *
     * @return array<mixed>
     */
    public function buildAggregationRequest(): array;

    /**
     * Binds the aggregation response to this aggregation; effectively creating an aggregation response object
     * for this request.
     *
     * Is called by the framework (usually inside {@see AggregationRequestBuilder}, not by the end-user.
     *
     * @param array<mixed> $aggregationResponse
     */
    public function bindResponse(array $aggregationResponse): AggregationResultInterface;

}
