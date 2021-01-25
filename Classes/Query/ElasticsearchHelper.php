<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query;

/*
 * This file is part of the Neos.ContentRepository.Search package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManager;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Search\Search\QueryBuilderInterface;

/**
 * Eel Helper to start search queries
 *
 * Elasticsearch.createRequest('indexnames')
 *   .query(
 *      Elasticsearch.createBooleanQuery()
 *         .should(Elasticsearch.createNeosFulltextQuery(site).fulltext(...).filter(Elasticsearch.createTermQuery("key", "value")))
 *         .should(...)
 *         .must(...)
 *   )
 *   .execute()
 */
class ElasticsearchHelper implements ProtectedContextAwareInterface
{
    /**
     * Create a new Search Query builder
     *
     * @param NodeInterface $contextNode
     * @return QueryBuilderInterface
     */
    public function createRequest(): SearchRequestBuilder
    {
        return new SearchRequestBuilder();
    }

    public function createBooleanQuery(): BooleanQueryBuilder
    {
        return new BooleanQueryBuilder();
    }

    public function createNeosFulltextQuery(NodeInterface $contextNode): NeosFulltextQueryBuilder
    {
        return new NeosFulltextQueryBuilder($contextNode);
    }

    public function createTermQuery(string $fieldName, $value): TermQueryBuilder
    {
        return new TermQueryBuilder($fieldName, $value);
    }

    /**
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
