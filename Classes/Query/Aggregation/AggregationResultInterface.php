<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query\Aggregation;

/**
 * Marker interface for aggregation results.
 *
 * An aggregation result is always created by calling {@see AggregationBuilderInterface::bindResponse());
 * and each AggregationBuilder implementation has a corresponding AggregationResult implementation.
 */
interface AggregationResultInterface
{
}
