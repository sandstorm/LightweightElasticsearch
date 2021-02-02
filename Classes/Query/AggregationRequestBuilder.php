<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query;

use Neos\Flow\Annotations as Flow;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Sandstorm\LightweightElasticsearch\Query\Aggregation\AggregationBuilderInterface;
use Sandstorm\LightweightElasticsearch\Query\Aggregation\AggregationResultInterface;
use Sandstorm\LightweightElasticsearch\Query\Aggregation\QueryErrorAggregationResult;
use Sandstorm\LightweightElasticsearch\Query\Query\SearchQueryBuilderInterface;

/**
 * Specialized request builder which can run a SINGLE top-level aggregation.
 * If you want to run multiple aggregations, we suggest to use multiple requests.
 */
class AggregationRequestBuilder extends AbstractSearchRequestBuilder
{
    /**
     * @Flow\InjectConfiguration("handleElasticsearchExceptions")
     * @var string
     */
    protected $handleElasticsearchExceptions;

    /**
     * @var AggregationBuilderInterface
     */
    private AggregationBuilderInterface $aggregationBuilder;

    // this builder can only run a single aggregation; so we can hardcode the aggregation name.
    private const AGGREGATION_NAME = 'innerAggregation';

    protected array $request = [
        // we do not need any results returned, as we are only interested in the aggregations
        'size' => 0,
        'query' => [
            // the aggregations need to run on a subset of all documents;
            // so we prepare a boolean filter query for this.
            'bool' => [
                'filter' => []
            ]
        ],
        'aggs' => [
            // we prepare the aggregation; we do not know its type yet.
            self::AGGREGATION_NAME => []
        ]
    ];

    /**
     * Cached aggregation result
     *
     * @var AggregationResultInterface|null
     */
    private ?AggregationResultInterface $aggregationResult = null;


    /**
     * Add a filter to the aggregation.
     *
     * @param SearchQueryBuilderInterface $query
     * @return $this
     */
    public function filter(SearchQueryBuilderInterface $query): self
    {

        if ($this->aggregationResult !== null) {
            // we need to reset the aggregation result cache when the builder is mutated
            $this->aggregationResult = null;
        }

        $this->request['query']['bool']['filter'][] = $query->buildQuery();
        return $this;
    }

    /**
     * Set the actual Aggregation to run
     *
     * @param AggregationBuilderInterface $aggregationBuilder
     */
    public function aggregation(AggregationBuilderInterface $aggregationBuilder): self
    {
        if ($this->aggregationResult !== null) {
            // we need to reset the aggregation result cache when the builder is mutated
            $this->aggregationResult = null;
        }

        $this->aggregationBuilder = $aggregationBuilder;
        return $this;
    }

    /**
     * Execute the query and return the Aggregation Result object as result.
     *
     * You can call this method multiple times; and the request is only executed at the first time; and cached
     * for later use.
     *
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Neos\Flow\Http\Exception
     */
    public function execute(): AggregationResultInterface
    {

        if ($this->aggregationResult === null) {
            try {

                $request = $this->prepareRequest();
                $jsonResponse = $this->executeInternal($request);
                $this->aggregationResult = $this->aggregationBuilder->bindResponse($jsonResponse['aggregations'][self::AGGREGATION_NAME]);
            } catch (ApiException $exception) {
                if ($this->handleElasticsearchExceptions === 'throw') {
                    throw $exception;
                }

                $this->aggregationResult = new QueryErrorAggregationResult();
            }


        }
        return $this->aggregationResult;
    }

    private function prepareRequest(): array
    {
        $request = $this->request;
        $request['aggs'][self::AGGREGATION_NAME] = $this->aggregationBuilder->buildAggregationRequest();

        // add an empty match_all filter which handles the case that we do not have any filters and need all results
        // returned.
        $request['query']['bool']['filter'][] = [
            'match_all' => new \stdClass()
        ];
        return $request;
    }


    /**
     * Returns the full request as it is sent to Elasticsearch; useful for debugging purposes.
     *
     * @return array
     */
    public function requestForDebugging(): array
    {
        return $this->prepareRequest();
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
