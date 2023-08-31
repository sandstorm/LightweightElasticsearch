<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Command;

/*
 * This file is part of the Sandstorm.LightweightElasticsearch package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Classes\Elasticsearch;
use Doctrine\Common\Collections\ArrayCollection;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\ConfigurationException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\RuntimeException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\WorkspaceIndexer;
use Flowpack\ElasticSearch\Domain\Model\Mapping;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;
use Neos\ContentRepository\Domain\Service\ContentDimensionPresetSourceInterface;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Cli\Exception\StopCommandException;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Booting\Exception\SubProcessException;
use Neos\Flow\Core\Booting\Scripts;
use Neos\Flow\Log\Backend\ConsoleBackend;
use Neos\Flow\Log\Psr\Logger;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Utility\Files;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\DocumentIndexing\DocumentNodeIndexer;
use Sandstorm\LightweightElasticsearch\DocumentIndexing\NodeTypeMappingBuilder;
use Sandstorm\LightweightElasticsearch\Factory\ElasticsearchFactory;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexPostfix;

/**
 * Provides CLI features for index handling
 *
 * @Flow\Scope("singleton")
 */
class NodeIndexCommandController extends CommandController
{

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var ElasticsearchFactory
     */
    protected $elasticsearchFactory;

    /**
     * Index all nodes by creating a new index and when everything was completed, switch the index alias.
     *
     * This command (re-)indexes all nodes contained in the content repository and sets the schema beforehand.
     *
     * @param int|null $limit Amount of nodes to index at maximum
     * @param bool $update if TRUE, do not throw away the index at the start. Should *only be used for development*.
     * @param string|null $workspace name of the workspace which should be indexed
     * @param string|null $postfix Index postfix, index with the same postfix will be deleted if exist
     * @return void
     * @throws StopCommandException
     * @throws Exception
     * @throws ConfigurationException
     * @throws ApiException
     */
    public function buildCommand(int $limit = null, bool $update = false, string $workspace = 'live', bool $verbose = false): void
    {
        $elasticsearch = $this->elasticsearchFactory->build(ContentRepositoryId::fromString('default'));
        $consoleLogBackend = new ConsoleBackend();
        if ($verbose) {
            $consoleLogBackend->setSeverityThreshold(LOG_DEBUG);
        }
        $logger = new Logger([$consoleLogBackend]);

        $elasticsearch->indexWorkspace(WorkspaceName::fromString($workspace), $logger);

        //$createIndicesAndApplyMapping = function (array $dimensionsValues) use ($update, $postfix) {
        //    $this->executeInternalCommand('createInternal', [
        //        'dimensionsValues' => json_encode($dimensionsValues),
        //        'update' => $update,
        //        'postfix' => $postfix,
        //    ]);
        //};
        return;

        $buildIndex = function (array $dimensionsValues) use ($workspace, $limit, $postfix) {
            $this->build($dimensionsValues, $workspace, $postfix, $limit);
        };

        $refresh = function (array $dimensionsValues) use ($postfix) {
            $this->executeInternalCommand('refreshInternal', [
                'dimensionsValues' => json_encode($dimensionsValues),
                'postfix' => $postfix,
            ]);
        };

        $updateAliases = function (array $dimensionsValues) use ($update, $postfix) {
            $this->executeInternalCommand('aliasInternal', [
                'dimensionsValues' => json_encode($dimensionsValues),
                'postfix' => $postfix,
                'update' => $update,
            ]);
        };
        $contentRepository = $this->contentRepositoryRegistry->get(\Neos\ContentRepository\Core\Factory\ContentRepositoryId::fromString('default'));
        $dimensionSpacePoints = $contentRepository->getInterDimensionalVariationGraph()->getDimensionSpacePoints();
        // TODO 9.0 migration: try to directly work with $dimensionSpacePoints, instead of converting them to the legacy dimension format


        $combinations = new ArrayCollection(array_map(fn(\Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint $dimensionSpacePoint) => $dimensionSpacePoint->toLegacyDimensionArray(), iterator_to_array($dimensionSpacePoints)));

        $runAndLog = function ($command, string $stepInfo) use ($combinations) {
            $timeStart = microtime(true);
            $this->output(str_pad($stepInfo . '... ', 20));
            $combinations->map($command);
            $this->outputLine('<success>Done</success> (took %s seconds)', [number_format(microtime(true) - $timeStart, 2)]);
        };

        $runAndLog($createIndicesAndApplyMapping, 'Creating indices and apply mapping');

        if ($this->aliasesExist() === false) {
            $runAndLog($updateAliases, 'Set up aliases');
        }

        $runAndLog($buildIndex, 'Indexing nodes');

        $runAndLog($refresh, 'Refresh indicies');
        $runAndLog($updateAliases, 'Update aliases');

        $this->outputLine('Update main alias');
        $this->nodeIndexer->updateMainAlias();

        $this->outputLine();
        $this->outputMemoryUsage();
    }

    /**
     * Clean up old indexes (i.e. all but the current one)
     *
     * @return void
     * @throws ConfigurationException
     * @throws Exception
     */
    public function cleanupCommand(): void
    {
        throw new \RuntimeException("TODO IMPLEMENT");




        $removed = false;
        $contentRepository = $this->contentRepositoryRegistry->get(\Neos\ContentRepository\Core\Factory\ContentRepositoryId::fromString('default'));
        $dimensionSpacePoints = $contentRepository->getInterDimensionalVariationGraph()->getDimensionSpacePoints();
        // TODO 9.0 migration: try to directly work with $dimensionSpacePoints, instead of converting them to the legacy dimension format

        $combinations = array_map(fn(\Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint $dimensionSpacePoint) => $dimensionSpacePoint->toLegacyDimensionArray(), iterator_to_array($dimensionSpacePoints));
        foreach ($combinations as $dimensionsValues) {
            try {
                $this->nodeIndexer->setDimensions($dimensionsValues);
                $removedIndices = $this->nodeIndexer->removeOldIndices();

                foreach ($removedIndices as $indexToBeRemoved) {
                    $removed = true;
                    $this->logger->info('Removing old index ' . $indexToBeRemoved, LogEnvironment::fromMethodName(__METHOD__));
                }
            } catch (ApiException $exception) {
                $exception->getResponse()->getBody()->rewind();
                $response = json_decode($exception->getResponse()->getBody()->getContents(), false);
                $message = sprintf('Nothing removed. ElasticSearch responded with status %s', $response->status);

                if (isset($response->error->type)) {
                    $this->logger->error(sprintf('%s, saying "%s: %s"', $message, $response->error->type, $response->error->reason), LogEnvironment::fromMethodName(__METHOD__));
                } else {
                    $this->logger->error(sprintf('%s, saying "%s"', $message, $response->error), LogEnvironment::fromMethodName(__METHOD__));
                }
            }
        }
        if ($removed === false) {
            $this->logger->info('Nothing to remove.', LogEnvironment::fromMethodName(__METHOD__));
        }
    }

    private function outputMemoryUsage(): void
    {
        $this->outputLine('! Memory usage %s', [Files::bytesToSizeString(memory_get_usage(true))]);
    }
}
