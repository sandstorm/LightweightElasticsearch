<?php

namespace Your\Package\Command;

use Neos\Flow\Cli\CommandController;
use Sandstorm\LightweightElasticsearch\CustomIndexing\CustomIndexer;

class CustomIndexCommandController extends CommandController
{

    public function indexCommand()
    {
        $indexer = CustomIndexer::create('faq');
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
