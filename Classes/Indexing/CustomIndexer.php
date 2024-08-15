<?php

namespace Sandstorm\LightweightElasticsearch\Indexing;

use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ElasticsearchApiClient;
use Sandstorm\LightweightElasticsearch\Settings\ElasticsearchSettings;
use Sandstorm\LightweightElasticsearch\SharedModel\AliasName;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexDiscriminator;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexName;
use Sandstorm\LightweightElasticsearch\SharedModel\MappingDefinition;

/**
 * Helper to use for indexing custom data sources into Elasticsearch.
 *
 * USAGE - INDEXING
 *
 * $indexer = CustomIndexer::create('faq');
 * $indexer->createIndexWithMapping([
 *   'properties' => [
 *     'faqEntry' => ['type' => 'text']
 *   ]
 * ]);
 * $indexer->index([
 *   'faqEntryTitle' => 'FAQ Dresden'
 * ]);
 * $indexer->finalizeAndSwitchAlias();
 *
 *
 *
 * USAGE - CLEANING OLD INDICES
 *
 * $indexer = CustomIndexer::create('faq');
 * $indexer->removeObsoleteIndices();
 *
 * @api
 */
#[Flow\Proxy(false)]
class CustomIndexer
{

    protected int $bulkSize = 100;

    /**
     * @internal only to be called from {@see CustomIndexerFactory::build()}
     */
    public function __construct(
        private readonly IndexName $indexName,
        private readonly AliasName $aliasName,
        private readonly IndexDiscriminator $discriminator,
        private readonly BulkRequestSender $bulkRequestSender,

        private readonly LoggerInterface $logger,
        private readonly ElasticsearchSettings $settings,
        private readonly ElasticsearchApiClient $apiClient,
        private readonly AliasManager $aliasManager,
    ) {
    }

    /**
     * Create a new index with the given Elasticsearch mapping.
     *
     * @param array<mixed> $fullMapping
     */
    public function createIndexWithMapping(array $fullMapping): void
    {
        $this->logger->info('Creating custom Index: ' . $this->indexName->value);
        if ($this->apiClient->hasIndex($this->indexName)) {
            $this->logger->info('  Index exists, removing and recreating');
            $this->apiClient->removeIndex($this->indexName);
        }
        $this->apiClient->createIndex($this->indexName, $this->settings->createIndexParameters($this->indexName));

        // enforce correct type of index_discriminator
        $fullMapping['properties'][IndexDiscriminator::KEY] = [
            'type' => 'keyword'
        ];
        $this->apiClient->updateMapping($this->indexName, MappingDefinition::fromArray($fullMapping));
    }

    /**
     * Index a document, optionally also specifying the document ID.
     *
     * @param array<mixed> $documentProperties
     * @param string|null $documentId
     */
    public function index(array $documentProperties, ?string $documentId = null): void
    {
        $documentProperties['index_discriminator'] = $this->discriminator->value;
        $this->bulkRequestSender->indexDocument($documentProperties, $documentId);
    }

    /**
     * Send the last bulk request to ensure indexing is completed; and then switch the index alias, so that
     * documents can be found.
     */
    public function finalizeAndSwitchAlias(): void
    {
        $this->bulkRequestSender->close();
        $this->logger->info('-> updating aliases');
        $this->aliasManager->updateIndexAlias($this->aliasName, $this->indexName);
    }

    /**
     * Remove old indices which are not active anymore (remember, each bulk index creates a new index from scratch,
     * making the "old" index a stale one).
     *
     * @return array<string> a list of index names which were removed
     */
    public function removeObsoleteIndices(): array
    {
        throw new \RuntimeException('TODO IMPLEMENT ME');
        $currentlyLiveIndices = $this->indexDriver->getIndexNamesByAlias($this->aliasName);

        $indexStatus = $this->elasticsearchClient->request('GET', '/_stats')->getTreatedContent();
        $allIndices = array_keys($indexStatus['indices']);

        $indicesToBeRemoved = [];

        foreach ($allIndices as $indexName) {
            if (strpos($indexName, $this->aliasName . '-') !== 0) {
                // filter out all indices not starting with the alias-name, as they are unrelated to our application
                continue;
            }

            if (in_array($indexName, $currentlyLiveIndices, true)) {
                // skip the currently live index names from deletion
                continue;
            }

            $indicesToBeRemoved[] = $indexName;
        }

        array_map(function ($index) {
            $this->indexDriver->deleteIndex($index);
        }, $indicesToBeRemoved);

        return $indicesToBeRemoved;
    }
}
