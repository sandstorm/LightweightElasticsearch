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

use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Log\Backend\ConsoleBackend;
use Neos\Flow\Log\Psr\Logger;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Utility\Files;
use Sandstorm\LightweightElasticsearch\Factory\ElasticsearchFactory;

/**
 * Provides CLI features for index handling
 */
#[Flow\Scope("singleton")]
class NodeIndexCommandController extends CommandController
{
    #[Flow\Inject]
    protected ElasticsearchFactory $elasticsearchFactory;

    /**
     * Index all nodes by creating a new index and when everything was completed, switch the index alias.
     *
     * This command (re-)indexes all nodes contained in the content repository and sets the schema beforehand.
     *
     * @param string $workspace name of the workspace which should be indexed
     * @param bool $verbose output extra details
     */
    public function buildCommand(string $workspace = 'live', bool $verbose = false): void
    {
        $consoleLogBackend = new ConsoleBackend();
        if ($verbose) {
            $consoleLogBackend->setSeverityThreshold(LOG_DEBUG);
        }
        $logger = new Logger([$consoleLogBackend]);

        $elasticsearch = $this->elasticsearchFactory->build(ContentRepositoryId::fromString('default'), $logger);


        $elasticsearch->indexWorkspace(WorkspaceName::fromString($workspace));
        $this->outputMemoryUsage();
    }

    /**
     * Clean up old indexes (i.e. all but the current one)
     *
     * @return void
     */
    public function cleanupCommand(string $workspace = 'live', bool $verbose = false): void
    {
        $consoleLogBackend = new ConsoleBackend();
        if ($verbose) {
            $consoleLogBackend->setSeverityThreshold(LOG_DEBUG);
        }
        $logger = new Logger([$consoleLogBackend]);

        $elasticsearch = $this->elasticsearchFactory->build(ContentRepositoryId::fromString('default'), $logger);
        $elasticsearch->removeObsoleteIndices(WorkspaceName::fromString($workspace));
    }

    private function outputMemoryUsage(): void
    {
        $this->outputLine('! Memory usage %s', [Files::bytesToSizeString(memory_get_usage(true))]);
    }
}
