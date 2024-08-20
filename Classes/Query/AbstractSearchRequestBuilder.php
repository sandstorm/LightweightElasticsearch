<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\Elasticsearch;
use Sandstorm\LightweightElasticsearch\SharedModel\AliasName;


abstract class AbstractSearchRequestBuilder implements ProtectedContextAwareInterface
{
    #[Flow\Inject]
    protected ThrowableStorageInterface $throwableStorage;

    #[Flow\Inject]
    protected LoggerInterface $logger;

    protected bool $logThisQuery = false;

    protected ?string $logMessage;
    private WorkspaceName $workspaceName;

    /**
     * @param array<mixed> $additionalAliases
     */
    public function __construct(
        protected readonly Elasticsearch $elasticsearch,
        protected readonly Node|null $contextNode = null,
        protected readonly array $additionalAliases = [],
    ) {
        $this->workspaceName = $this->contextNode?->workspaceName ?: WorkspaceName::forLive();
        foreach ($this->additionalAliases as $alias) {
            if (!$alias instanceof AliasName) {
                throw new \RuntimeException('alias is no AliasName, but ' . get_class($alias), 1693488463);
            }
        }
    }

    /**
     * Log the current request to the Elasticsearch log for debugging after it has been executed.
     *
     * @param string|null $message an optional message to identify the log entry
     * @api
     */
    public function log(string $message = null): self
    {
        $this->logThisQuery = true;
        $this->logMessage = $message;

        return $this;
    }

    /**
     * Execute the query and return the SearchResult object as result.
     *
     * You can call this method multiple times; and the request is only executed at the first time; and cached
     * for later use
     *
     * @param array<mixed> $request
     * @return array<mixed>
     */
    protected function executeInternal(array $request): array
    {
        try {
            $timeBefore = microtime(true);

            $aliasNames = $this->additionalAliases;
            if ($this->contextNode !== null) {
                $aliasNames[] = AliasName::createForWorkspaceAndDimensionSpacePoint(
                    $this->elasticsearch->settings->nodeIndexNamePrefix,
                    $this->contextNode->contentRepositoryId,
                    $this->workspaceName,
                    $this->contextNode->dimensionSpacePoint,
                );
            }

            $jsonResponse = $this->elasticsearch->apiClient->search($aliasNames, $request);
            $timeAfterwards = microtime(true);

            $this->logThisQuery && $this->logger->debug(sprintf('Query Log (%s): Indexname: %s %s -- execution time: %s ms -- Number of results returned: %s -- Total Results: %s', $this->logMessage, implode(',', $aliasNames), json_encode($request), (($timeAfterwards - $timeBefore) * 1000), count($jsonResponse['hits']['hits']), $jsonResponse['hits']['total']['value']), LogEnvironment::fromMethodName(__METHOD__));
            return $jsonResponse;
        } catch (\RuntimeException $exception) {
            $message = $this->throwableStorage->logThrowable($exception);
            $this->logger->error(sprintf('Request failed with %s', $message), LogEnvironment::fromMethodName(__METHOD__));
            throw $exception;
        }
    }

    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }
}
