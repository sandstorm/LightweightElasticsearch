<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query\Aggregation;

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;

/**
 * Placeholder for an aggregation result in case of a query error
 *
 * @Flow\Proxy(false)
 */
class QueryErrorAggregationResult implements AggregationResultInterface, ProtectedContextAwareInterface
{

    public function isError() {
        return true;
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
