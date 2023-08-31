<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query\Eel;

/*
 * This file is part of the Neos.ContentRepository.Search package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\ProtectedContextAwareInterface;
use Sandstorm\LightweightElasticsearch\Factory\ElasticsearchFactory;
use Sandstorm\LightweightElasticsearch\Query\Aggregation\TermsAggregationBuilder;
use Sandstorm\LightweightElasticsearch\Query\Highlight\NeosFulltextHighlightBuilder;
use Sandstorm\LightweightElasticsearch\Query\Query\BooleanQueryBuilder;
use Sandstorm\LightweightElasticsearch\Query\Query\NeosFulltextQueryBuilder;
use Sandstorm\LightweightElasticsearch\Query\Query\TermQueryBuilder;
use Sandstorm\LightweightElasticsearch\Query\SearchRequestBuilder;

/**
 * Eel Helper to write search queries.
 * Example:
 *
 * Elasticsearch.createRequest(site)
 *   .query(
 *      Elasticsearch.createBooleanQuery()
 *         .should(Elasticsearch.createNeosFulltextQuery(site).fulltext("mein Suchstring")))
 *         .should(...)
 *         .should(...)
 *   )
 *   .aggregation()
 *   .execute()
 */
class ElasticsearchHelper implements ProtectedContextAwareInterface
{
    public function __construct(
        private readonly ContentRepositoryRegistry $contentRepositoryRegistry,
        private readonly ElasticsearchFactory $elasticsearchFactory,
    ) {
    }

    /**
     * Create a new Search Query builder
     */
    public function createRequest(Node $contextNode = null, array $additionalIndices = []): SearchRequestBuilder
    {
        $elasticsearch = $this->elasticsearchFactory->build($contextNode?->subgraphIdentity->contentRepositoryId ?? ContentRepositoryId::fromString('default'));
        return new SearchRequestBuilder($this->contentRepositoryRegistry, $elasticsearch->settings, $elasticsearch->apiClient, $contextNode, $additionalIndices);
    }

    public function createBooleanQuery(): BooleanQueryBuilder
    {
        return BooleanQueryBuilder::create();
    }

    public function createNeosFulltextQuery(Node $contextNode): NeosFulltextQueryBuilder
    {
        return NeosFulltextQueryBuilder::create($contextNode, $this->contentRepositoryRegistry);
    }

    public function createTermQuery(string $fieldName, $value): TermQueryBuilder
    {
        return TermQueryBuilder::create($fieldName, $value);
    }

    public function createAggregationRequest(NodeInterface $contextNode = null, array $additionalIndices = []): AggregationRequestBuilder
    {
        return new AggregationRequestBuilder($contextNode, $additionalIndices);
    }

    public function createTermsAggregation(string $fieldName, ?string $selectedValue = null): TermsAggregationBuilder
    {
        return TermsAggregationBuilder::create($fieldName, $selectedValue);
    }

    public function createNeosFulltextHighlight(int $fragmentSize = 150, int $fragmentCount = 2): NeosFulltextHighlightBuilder
    {
        return NeosFulltextHighlightBuilder::create($fragmentSize, $fragmentCount);
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
