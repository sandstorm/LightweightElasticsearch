g<?php
namespace Sandstorm\LightweightElasticsearch\CustomIndexing;

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Indexer\NodeIndexer;
use Neos\Flow\Annotations as Flow;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexDriverInterface;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\Transfer\Exception\ApiException;

/**
 * Extracted from {@see NodeIndexer::updateIndexAlias()}; and made generic.
 *
 * You do not need this directly; but can use {@see CustomIndexer} instead.
 *
 * @internal
 */
class IndexAliasManager
{

    /**
     * @Flow\Inject
     * @var \Flowpack\ElasticSearch\ContentRepositoryAdaptor\ElasticSearchClient
     */
    protected $searchClient;

    /**
     * @var IndexDriverInterface
     * @Flow\Inject
     */
    protected $indexDriver;

    /**
     * Update the index alias
     *
     * @return void
     * @throws Exception
     * @throws ApiException
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws \Exception
     */
    public function updateIndexAlias($aliasName, $indexName): void
    {
        if ($indexName === $aliasName) {
            throw new Exception('Index and alias names must differ', 1611586513);
        }

        $index = $this->searchClient->findIndex($indexName);
        if (!$index->exists()) {
            throw new Exception(sprintf('The target index "%s" does not exist.', $index->getName()), 1611586520);
        }

        $aliasActions = [];
        try {
            $indexNames = $this->indexDriver->getIndexNamesByAlias($aliasName);
            if ($indexNames === []) {
                // if there is an actual index with the name we want to use as alias, remove it now
                $this->indexDriver->deleteIndex($aliasName);
            } else {
                // Remove all existing aliasses
                foreach ($indexNames as $indexNameToRemove) {
                    $aliasActions[] = [
                        'remove' => [
                            'index' => $indexNameToRemove,
                            'alias' => $aliasName
                        ]
                    ];
                }
            }
        } catch (ApiException $exception) {
            // in case of 404, do not throw an error...
            if ($exception->getResponse()->getStatusCode() !== 404) {
                throw $exception;
            }
        }

        $aliasActions[] = [
            'add' => [
                'index' => $indexName,
                'alias' => $aliasName
            ]
        ];

        $this->indexDriver->aliasActions($aliasActions);
    }
}
