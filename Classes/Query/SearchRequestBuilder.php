<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Utility;
use Neos\Flow\Annotations as Flow;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\Query\Result\SearchResult;


class SearchRequestBuilder implements ProtectedContextAwareInterface
{

    /**
     * @Flow\Inject
     * @var ElasticSearchClient
     */
    protected $elasticSearchClient;

    /**
     * @Flow\Inject
     * @var ThrowableStorageInterface
     */
    protected $throwableStorage;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    private array $additionalIndices;

    /**
     * @var boolean
     */
    protected $logThisQuery = false;

    /**
     * @var string
     */
    protected $logMessage;

    /**
     * @var NodeInterface|null
     */
    private ?NodeInterface $contextNode;

    public function __construct(NodeInterface $contextNode = null, array $additionalIndices = [])
    {
        $this->contextNode = $contextNode;
        $this->additionalIndices = $additionalIndices;
    }

    protected array $request = [];


    public function query(SearchQueryBuilderInterface $query): self
    {
        $this->request['query'] = $query->buildQuery();
        return $this;
    }

    public function from(int $offset): self
    {
        $this->request['from'] = $offset;
        return $this;
    }

    public function size(int $size): self
    {
        $this->request['size'] = $size;
        return $this;
    }

    /**
     * Log the current request to the Elasticsearch log for debugging after it has been executed.
     *
     * @param string $message an optional message to identify the log entry
     * @api
     */
    public function log($message = null): self
    {
        $this->logThisQuery = true;
        $this->logMessage = $message;

        return $this;
    }

    /**
     * Execute the query and return the SearchResult object as result
     *
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Neos\Flow\Http\Exception
     */
    public function execute(): SearchResult
    {
        try {
            $timeBefore = microtime(true);
            $request = $this->request;

            $indexNames = $this->additionalIndices;
            if ($this->contextNode !== null) {
                $dimensionValues = $this->contextNode->getContext()->getDimensions();
                $dimensionHash = Utility::sortDimensionValueArrayAndReturnDimensionsHash($dimensionValues);
                $indexNames[] = 'neoscr-' . $dimensionHash;
            }


            $response = $this->elasticSearchClient->request('GET', '/' . implode(',', $indexNames) . '/_search', [], $request);
            $timeAfterwards = microtime(true);

            $searchResult = SearchResult::fromElasticsearchJsonResponse($response->getTreatedContent(), $this->contextNode);

            $this->logThisQuery && $this->logger->debug(sprintf('Query Log (%s): Indexname: %s %s -- execution time: %s ms -- Limit: %s -- Number of results returned: %s -- Total Results: %s', $this->logMessage, implode(',', $indexNames), $request, (($timeAfterwards - $timeBefore) * 1000), $this->size, count(iterator_to_array($searchResult->getIterator())), $searchResult->total()), LogEnvironment::fromMethodName(__METHOD__));
            return $searchResult;
        } catch (ApiException $exception) {
            $message = $this->throwableStorage->logThrowable($exception);
            $this->logger->error(sprintf('Request failed with %s', $message), LogEnvironment::fromMethodName(__METHOD__));
            return SearchResult::error();
        }
    }

    /**
     * DO NOT USE THIS METHOD DIRECTLY; it is implemented to ensure Flowpack.Listable plays well with these objects here.
     *
     * @return int
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Neos\Flow\Http\Exception
     * @internal
     */
    public function count(): int
    {
        return $this->execute()->total();
    }

    /**
     * Returns the full request as it is sent to Elasticsearch; useful for debugging purposes.
     *
     * @return array
     */
    public function requestForDebugging(): array
    {
        return $this->request;
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
