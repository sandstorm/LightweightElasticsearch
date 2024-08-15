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

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\ProtectedContextAwareInterface;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\Factory\ElasticsearchFactory;
use Sandstorm\LightweightElasticsearch\Query\Aggregation\TermsAggregationBuilder;
use Sandstorm\LightweightElasticsearch\Query\AggregationRequestBuilder;
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
    #[Flow\Inject]
    protected LoggerInterface $logger;
    public function __construct(
        private readonly ElasticsearchFactory $elasticsearchFactory,
    ) {
    }

    /**
     * Create a new Search Query builder
     * @param array<mixed> $additionalIndices
     */
    public function createRequest(Node $contextNode = null, array $additionalIndices = []): SearchRequestBuilder
    {
        $contentRepositoryId = $contextNode?->contentRepositoryId ?: ContentRepositoryId::fromString('default');
        $elasticsearch = $this->elasticsearchFactory->build(
            $contentRepositoryId,
            $this->logger
        );
        return new SearchRequestBuilder($elasticsearch, $contextNode, $additionalIndices);
    }

    public function createBooleanQuery(): BooleanQueryBuilder
    {
        return BooleanQueryBuilder::create();
    }

    public function createNeosFulltextQuery(Node $contextNode): NeosFulltextQueryBuilder
    {
        return NeosFulltextQueryBuilder::create($contextNode);
    }

    public function createTermQuery(string $fieldName, mixed $value): TermQueryBuilder
    {
        return TermQueryBuilder::create($fieldName, $value);
    }

    /**
     * @param array<mixed> $additionalIndices
     */
    public function createAggregationRequest(Node $contextNode = null, array $additionalIndices = []): AggregationRequestBuilder
    {
        $contentRepositoryId = $contextNode?->contentRepositoryId ?: ContentRepositoryId::fromString('default');
        $elasticsearch = $this->elasticsearchFactory->build(
            $contentRepositoryId,
            $this->logger
        );
        return new AggregationRequestBuilder($elasticsearch, $contextNode, $additionalIndices);
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
