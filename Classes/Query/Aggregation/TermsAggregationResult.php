<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query\Aggregation;

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;

/**
 *
 * Example usage:
 *
 * ```fusion
 * nodeTypesFacet = Neos.Fusion:Component {
 *     termsAggregationResult = ${searchRequest.execute().aggregation("nodeTypes")}
 *     renderer = afx`
 *             <Neos.Fusion:Loop items={props.termsAggregationResult.buckets} itemName="bucket">
 *                 <Neos.Neos:NodeLink node={documentNode} addQueryString={true} arguments={props.termsAggregationResult.buildUriArgumentForFacet(bucket.key)}>{bucket.key}</Neos.Neos:NodeLink> {bucket.doc_count}
 *             </Neos.Fusion:Loop>
 *     `
 * }
 * ```
 */
#[Flow\Proxy(false)]
class TermsAggregationResult implements AggregationResultInterface, ProtectedContextAwareInterface
{
    /**
     * @var array<mixed>
     */
    private array $aggregationResponse;
    private TermsAggregationBuilder $termsAggregationBuilder;

    /**
     * @param array<mixed> $aggregationResponse
     */
    private function __construct(array $aggregationResponse, TermsAggregationBuilder $aggregationRequestBuilder)
    {
        $this->aggregationResponse = $aggregationResponse;
        $this->termsAggregationBuilder = $aggregationRequestBuilder;
    }

    /**
     * @param array<mixed> $aggregationResponse
     */
    public static function create(array $aggregationResponse, TermsAggregationBuilder $aggregationRequestBuilder): self
    {
        return new self($aggregationResponse, $aggregationRequestBuilder);
    }

    /**
     * @return array<mixed>
     */
    public function getBuckets(): array
    {
        return $this->aggregationResponse['buckets'];
    }

    /**
     * @return string|null
     */
    public function getSelectedValue(): ?string
    {
        return $this->termsAggregationBuilder->getSelectedValue();
    }

    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }
}
