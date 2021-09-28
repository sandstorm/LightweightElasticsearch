<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;


abstract class AbstractSearchRequestBuilder implements ProtectedContextAwareInterface
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
    protected ?NodeInterface $contextNode;

    public function __construct(NodeInterface $contextNode = null, array $additionalIndices = [])
    {
        $this->contextNode = $contextNode;
        $this->additionalIndices = $additionalIndices;
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
     * Execute the query and return the SearchResult object as result.
     *
     * You can call this method multiple times; and the request is only executed at the first time; and cached
     * for later use.
     *
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Neos\Flow\Http\Exception
     */
    protected function executeInternal(array $request): array
    {
        try {
            $timeBefore = microtime(true);

            $indexNames = $this->additionalIndices;
            if ($this->contextNode !== null) {
                $this->elasticSearchClient->setContextNode($this->contextNode);
                $indexNames[] = $this->elasticSearchClient->getIndexName();
            }

            $response = $this->elasticSearchClient->request('GET', '/' . implode(',', $indexNames) . '/_search', [], $request);
            $timeAfterwards = microtime(true);

            $jsonResponse = $response->getTreatedContent();
            $this->logThisQuery && $this->logger->debug(sprintf('Query Log (%s): Indexname: %s %s -- execution time: %s ms -- Number of results returned: %s -- Total Results: %s', $this->logMessage, implode(',', $indexNames), json_encode($request), (($timeAfterwards - $timeBefore) * 1000), count($jsonResponse['hits']['hits']), $jsonResponse['hits']['total']['value']), LogEnvironment::fromMethodName(__METHOD__));
            return $jsonResponse;
        } catch (ApiException $exception) {
            $message = $this->throwableStorage->logThrowable($exception);
            $this->logger->error(sprintf('Request failed with %s', $message), LogEnvironment::fromMethodName(__METHOD__));
            throw $exception;
        }
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
