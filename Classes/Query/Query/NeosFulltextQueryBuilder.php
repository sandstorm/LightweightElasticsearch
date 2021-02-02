<?php


namespace Sandstorm\LightweightElasticsearch\Query\Query;

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\Model\NodeInterface;

/**
 * Do a fulltext search in Neos nodes, by searching neos_fulltext appropriately.
 *
 * Also allows to further restrict the result set by calling filter().
 *
 * @Flow\Proxy(false)
 */
class NeosFulltextQueryBuilder implements SearchQueryBuilderInterface, ProtectedContextAwareInterface
{
    protected BooleanQueryBuilder $boolQuery;

    public static function create(NodeInterface $contextNode): self
    {
        return new self($contextNode);
    }

    private function __construct(NodeInterface $contextNode)
    {
        $this->boolQuery = BooleanQueryBuilder::create()
            // on indexing, the neos_parent_path is tokenized to contain ALL parent path parts,
            // e.g. /foo, /foo/bar/, /foo/bar/baz; to speed up matching.. That's why we use a simple "term" filter here.
            // http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/query-dsl-term-filter.html
            // another term filter against the path allows the context node itself to be found
            ->filter(
                BooleanQueryBuilder::create()
                    ->should(TermQueryBuilder::create('neos_parent_path', $contextNode->getPath()))
                    ->should(TermQueryBuilder::create('neos_path', $contextNode->getPath()))
            )
            ->filter(
            // http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/query-dsl-terms-filter.html
                TermsQueryBuilder::create('neos_workspace', array_unique(['live', $contextNode->getContext()->getWorkspace()->getName()]))
            );
    }

    /**
     * Specify the fulltext query string to be used for searching.
     *
     * NOTE: this method can be called multiple times; this corresponds to an "AND" of the queries (i.e. BOTH must match to include the result).
     *       I am not yet sure if this is a good idea or not :-)
     *
     * @param string|null $query
     * @return $this
     */
    public function fulltext(string $query = null): self
    {
        $this->boolQuery->must(SimpleQueryStringBuilder::create($query ?? '')->fields([
            'neos_fulltext.h1^5',
            'neos_fulltext.h2^4',
            'neos_fulltext.h3^3',
            'neos_fulltext.h4^2',
            'neos_fulltext.h5^1',
            'neos_fulltext.h6',
            'neos_fulltext.text',
        ]));
        return $this;
    }

    /**
     * Add a query to the "filter" part of the query. This query must ALWAYS match for a document to be included in the results; and ranking information is discarded.
     *
     * @param SearchQueryBuilderInterface $query
     * @return $this
     */
    public function filter(SearchQueryBuilderInterface $query): self
    {
        $this->boolQuery->filter($query);
        return $this;
    }

    public function buildQuery(): array
    {
        return $this->boolQuery->buildQuery();
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
