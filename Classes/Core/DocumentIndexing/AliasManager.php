<?php

namespace Sandstorm\LightweightElasticsearch\Core\DocumentIndexing;

use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCalls\AliasActionsBuilder;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ElasticsearchApiClient;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\AliasName;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\IndexName;

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
        // Remove all existing aliasses
        foreach ($indexNames as $indexNameToRemove) {
            $aliasActions->removeAlias($aliasName, $indexNameToRemove);
        }
        // add new alias
        $aliasActions->addAlias($aliasName, $indexName);
        $this->apiClient->updateAliases($aliasActions);
    }
}
