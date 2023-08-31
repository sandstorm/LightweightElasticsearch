<?php

namespace Sandstorm\LightweightElasticsearch\Indexing;

use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls\AliasActionsBuilder;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ElasticsearchApiClient;
use Sandstorm\LightweightElasticsearch\SharedModel\AliasName;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexName;

/**
 * After indexing has completed, switch the index aliases correctly to make it available for searching.
 */
#[Flow\Proxy(false)]
class AliasManager
{
    public function __construct(
        private readonly ElasticsearchApiClient $apiClient,
    ) {
    }

    public function updateIndexAlias(AliasName $aliasName, IndexName $indexName): void
    {
        if (!$this->apiClient->hasIndex($indexName)) {
            throw new \RuntimeException(sprintf('The target index "%s" does not exist.', $indexName->value), 1611586520);
        }

        $aliasActions = new AliasActionsBuilder();
        $indexNames = $this->apiClient->indexNamesByAlias($aliasName);
        // Remove all existing aliases
        foreach ($indexNames as $indexNameToRemove) {
            $aliasActions->removeAlias($aliasName, $indexNameToRemove);
        }
        // add new alias
        $aliasActions->addAlias($aliasName, $indexName);
        $this->apiClient->updateAliases($aliasActions);
    }
}
