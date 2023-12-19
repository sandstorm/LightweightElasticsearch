<?php
declare(strict_types=1);

namespace Your\Package\Command;

use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Log\Backend\ConsoleBackend;
use Neos\Flow\Log\Psr\Logger;
use Sandstorm\LightweightElasticsearch\Factory\ElasticsearchFactory;
use Sandstorm\LightweightElasticsearch\Indexing\CustomIndexer;
use Sandstorm\LightweightElasticsearch\SharedModel\AliasName;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexDiscriminator;

class CustomIndexCommandController extends CommandController
{

    #[Flow\Inject]
    protected ElasticsearchFactory $elasticsearchFactory;

    public function indexCommand()
    {
        $logBackend = new ConsoleBackend();
        $logBackend->setSeverityThreshold(LOG_DEBUG);
        $logger = new Logger([$logBackend]);

        $elasticsearch = $this->elasticsearchFactory->build(
            ContentRepositoryId::fromString('default'),
            $logger
        );
        $indexer = $elasticsearch->customIndexer(
            AliasName::createForCustomIndex('custom_faq'),
            IndexDiscriminator::createForCustomIndex('custom_faq')
        );
        $indexer->createIndexWithMapping(['properties' => [
            'faqEntryTitle' => [
                'type' => 'text'
            ]
        ]]);
        $indexer->index([
            'faqEntryTitle' => 'FAQ Dresden'
        ]);
        $indexer->index([
            'faqEntryTitle' => 'FAQ Berlin'
        ]);
        $indexer->finalizeAndSwitchAlias();
    }

    public function cleanupCommand()
    {
        $indexer = CustomIndexer::create('faq');
        $removedIndices = $indexer->removeObsoleteIndices();
        foreach ($removedIndices as $index) {
            $this->outputLine('Removed ' . $index);
        }
    }
}
