<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query;

use Neos\Flow\Annotations as Flow;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Sandstorm\LightweightElasticsearch\Query\Result\SearchResult;

class SearchRequestBuilder implements ProtectedContextAwareInterface
{

    /**
     * @Flow\Inject
     * @var ElasticSearchClient
     */
    protected $elasticSearchClient;

    private array $indexNames;

    public function __construct(array $indexNames) {
        $this->indexNames = $indexNames;
    }

    protected array $request = [];

    public function from(int $offset) {
        $this->request['from'] = $offset;
    }

    public function size(int $size) {
        $this->request['size'] = $size;
    }

    public function query(SearchQueryBuilderInterface $query)
    {
        $this->request['query'] = $query->buildQuery();
    }


    /**
     * Execute the query and return the list of nodes as result.
     *
     * This method is rather internal; just to be called from the ElasticSearchQueryResult. For the public API, please use execute()
     *
     * @throws Exception
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Neos\Flow\Http\Exception
     */
    public function execute(): SearchResult
    {
        try {
            $timeBefore = microtime(true);
            $request = $this->request;
            $response = $this->elasticSearchClient->request('GET', '/' . implode(',', $this->indexNames) . '/_search', [], $request);
            $timeAfterwards = microtime(true);

            $searchResult = SearchResult::fromElasticsearchJsonResponse($response->getTreatedContent());

            $this->logThisQuery && $this->logger->debug(sprintf('Query Log (%s): Indexname: %s %s -- execution time: %s ms -- Limit: %s -- Number of results returned: %s -- Total Results: %s', $this->logMessage, $this->getIndexName(), $request, (($timeAfterwards - $timeBefore) * 1000), $this->limit, count($searchResult->getHits()), $searchResult->getTotal()), LogEnvironment::fromMethodName(__METHOD__));

            return $searchResult;
        } catch (ApiException $exception) {
            $message = $this->throwableStorage->logThrowable($exception);
            $this->logger->error(sprintf('Request failed with %s', $message), LogEnvironment::fromMethodName(__METHOD__));
            return SearchResult::error();
        }
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
