<?php


namespace Sandstorm\LightweightElasticsearch\Query\Aggregation;

use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\Query\SearchQueryBuilderInterface;

/**
 * A Terms aggregation can be used to build faceted search.
 *
 * It needs to be configured using:
 * - the Elasticsearch field name which should be faceted (should be of type "keyword" to have useful results)
 * - The selected value from the request, if any.
 *
 * The Terms Aggregation can be additionally used as search filter.
 *
 * See https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-terms-aggregation.html for the details of usage.
 *
 * @Flow\Proxy(false)
 */
class TermsAggregationBuilder implements AggregationBuilderInterface, SearchQueryBuilderInterface
{
    private string $fieldName;
    /**
     * @var string|null the selected value, as taken from the URL parameters
     */
    private ?string $selectedValue;

    public static function create(string $fieldName, ?string $selectedValue = null): self
    {
        return new self($fieldName, $selectedValue);
    }

    private function __construct(string $fieldName, ?string $selectedValue = null)
    {
        $this->fieldName = $fieldName;
        $this->selectedValue = $selectedValue;
    }

    public function buildAggregationRequest(): array
    {
        // This is a Terms aggregation, with the field name specified by the user.
        return [
            'terms' => [
                'field' => $this->fieldName
            ]
        ];
    }

    public function bindResponse(array $aggregationResponse): AggregationResultInterface
    {
        return TermsAggregationResult::create($aggregationResponse, $this);
    }

    public function buildQuery(): array
    {
        // for implementing faceting, we build the restriction query here
        if ($this->selectedValue) {
            return [
                'term' => [
                    $this->fieldName => $this->selectedValue
                ]
            ];
        }

        // json_encode([]) === "[]"
        // json_encode(new \stdClass) === "{}" <-- we need this!
        return ['match_all' => new \stdClass()];
    }

    /**
     * @return string|null
     */
    public function getSelectedValue(): ?string
    {
        return $this->selectedValue;
    }
}
