<?php

namespace Sandstorm\LightweightElasticsearch\CustomIndexing;

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexDriverInterface;
use Neos\Flow\Annotations as Flow;
use Flowpack\ElasticSearch\Domain\Factory\ClientFactory;
use Flowpack\ElasticSearch\Domain\Model\GenericType;
use Flowpack\ElasticSearch\Domain\Model\Index;
use Flowpack\ElasticSearch\Domain\Model\Mapping;

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
 */
class CustomIndexer
{
    /**
     * @Flow\Inject
     * @var ClientFactory
     */
    protected $clientFactory;

    /**
     * @Flow\Inject
     * @var IndexAliasManager
     */
    protected $indexAliasManager;

    /**
     * @Flow\Inject
     * @var IndexDriverInterface
     */
    protected $indexDriver;


    private \Flowpack\ElasticSearch\Domain\Model\Client $elasticsearchClient;

    protected string $aliasName;
    protected string $discriminatorValue;
    protected string $indexName;
    protected ?Index $index;

    protected int $bulkSize = 100;
    protected array $currentBulkRequest = [];


    /**
     * Create a custom indexer with the given $aliasName as index alias (i.e. what you specify in the 2nd argument
     * of `Elasticsearch.createRequest(site, ['myAlias'])` in Eel).
     *
     * The given $discriminatorValue is used as value of the `index_discriminator` key in every indexed document;
     * and can be used to distinguish different document types inside a query.
     *
     * If no $discriminatorValue is specified, the $aliasName is used by default.
     *
     * @param string $aliasName name of the Elasticsearch alias to create/update when indexing is completed
     * @param string|null $discriminatorValue value of the index_discriminator field of all documents indexed by this indexer. If null, $aliasName is used.
     * @return CustomIndexer
     */
    public static function create(string $aliasName, string $discriminatorValue = null): CustomIndexer
    {
        if ($discriminatorValue === null) {
            $discriminatorValue = $aliasName;
        }

        return new CustomIndexer($aliasName, $discriminatorValue);
    }

    protected function __construct(string $aliasName, string $discriminatorValue)
    {
        $this->aliasName = $aliasName;
        $this->discriminatorValue = $discriminatorValue;
        $this->indexName = $aliasName . '-' . time();
    }

    public function initializeObject()
    {
        $this->elasticsearchClient = $this->clientFactory->create();
        $this->index = new Index($this->indexName, $this->elasticsearchClient);
    }

    /**
     * Create a new index with the given Elasticsearch mapping.
     *
     * @param array $fullMapping
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Neos\Flow\Http\Exception
     */
    public function createIndexWithMapping(array $fullMapping): void
    {
        $this->index->create();
        $mapping = new Mapping(new GenericType($this->index, $this->discriminatorValue));

        // enforce correct type of index_discriminator
        $fullMapping['properties']['index_discriminator'] = [
            'type' => 'keyword'
        ];

        $mapping->setFullMapping($fullMapping);
        $mapping->apply();
    }

    /**
     * Determines after how many calls to index() the request is sent to Elasticsearch
     * @param int $bulkSize
     */
    public function setBulkSize(int $bulkSize): void
    {
        $this->bulkSize = $bulkSize;
    }

    /**
     * Index a document, optionally also specifying the document ID.
     *
     * @param array $documentProperties
     * @param string|null $documentId
     */
    public function index(array $documentProperties, ?string $documentId = null): void
    {
        if ($documentId !== null) {
            $this->currentBulkRequest[] = [
                'index' => [
                    '_id' => $documentId,
                ]
            ];
        } else {
            $this->currentBulkRequest[] = [
                'index' => new \stdClass()
            ];
        }

        $documentProperties['index_discriminator'] = $this->discriminatorValue;
        $this->currentBulkRequest[] = $documentProperties;

        // for every request, we have two rows in $this->currentBulkRequest
        if (count($this->currentBulkRequest) / 2 >= $this->bulkSize) {
            $this->sendCurrentBulkRequest();
        }
    }

    protected function sendCurrentBulkRequest(): void
    {
        if (count($this->currentBulkRequest) > 0) {
            // Bulk request MUST end with line return
            $request = implode("\n", array_map(fn($requestPart) => json_encode($requestPart), $this->currentBulkRequest)) . "\n";

            $this->index->request('POST', '/_bulk', [], $request);
        }

        $this->currentBulkRequest = [];
    }

    /**
     * Send the last bulk request to ensure indexing is completed; and then switch the index alias, so that
     * documents can be found.
     *
     * @throws \Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Flowpack\ElasticSearch\Transfer\Exception\ApiException
     */
    public function finalizeAndSwitchAlias(): void
    {
        $this->sendCurrentBulkRequest();
        $this->indexAliasManager->updateIndexAlias($this->aliasName, $this->indexName);
    }

    /**
     * Remove old indices which are not active anymore (remember, each bulk index creates a new index from scratch,
     * making the "old" index a stale one).
     *
     * @return array<string> a list of index names which were removed
     * @throws \Flowpack\ElasticSearch\Transfer\Exception
     * @throws \Flowpack\ElasticSearch\Transfer\Exception\ApiException
     * @throws \Neos\Flow\Http\Exception
     */
    public function removeObsoleteIndices(): array
    {
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
