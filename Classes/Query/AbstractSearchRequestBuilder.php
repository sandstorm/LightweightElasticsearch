<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ElasticsearchApiClient;
use Sandstorm\LightweightElasticsearch\Core\Settings\ElasticsearchSettings;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\AliasName;
use Symfony\Component\DependencyInjection\Alias;


abstract class AbstractSearchRequestBuilder implements ProtectedContextAwareInterface
{

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

    /**
     * @var boolean
     */
    protected $logThisQuery = false;

    /**
     * @var string
     */
    protected $logMessage;
    private WorkspaceName $workspaceName;

    public function __construct(
        protected readonly ContentRepositoryRegistry $contentRepositoryRegistry,
        protected readonly ElasticsearchSettings $settings,
        protected readonly ElasticsearchApiClient $apiClient,
        protected readonly Node|null $contextNode = null,
        protected readonly array $additionalAliases = [],

    ) {
        $contentRepository = $this->contentRepositoryRegistry->get($this->contextNode->subgraphIdentity->contentRepositoryId);
        $workspace = $contentRepository->getWorkspaceFinder()->findOneByCurrentContentStreamId($this->contextNode->subgraphIdentity->contentStreamId);
        $this->workspaceName = $workspace->workspaceName;
        foreach ($this->additionalAliases as $alias) {
            if (!$alias instanceof AliasName) {
                throw new \RuntimeException('alias is no AliasName, but ' . get_class($alias), 1693488463);
            }
        }
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

            $aliasNames = $this->additionalAliases;
            if ($this->contextNode !== null) {
                $aliasNames[] = AliasName::createForWorkspaceAndDimensionSpacePoint(
                    $this->settings->nodeIndexNamePrefix,
                    $this->contextNode->subgraphIdentity->contentRepositoryId,
                    $this->workspaceName,
                    $this->contextNode->subgraphIdentity->dimensionSpacePoint,
                );
            }

            $jsonResponse = $this->apiClient->search($aliasNames, $request);
            $timeAfterwards = microtime(true);

            $this->logThisQuery && $this->logger->debug(sprintf('Query Log (%s): Indexname: %s %s -- execution time: %s ms -- Number of results returned: %s -- Total Results: %s', $this->logMessage, implode(',', $aliasNames), json_encode($request), (($timeAfterwards - $timeBefore) * 1000), count($jsonResponse['hits']['hits']), $jsonResponse['hits']['total']['value']), LogEnvironment::fromMethodName(__METHOD__));
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
