<?php

namespace Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCalls;

use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\AliasName;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\IndexName;

#[Flow\Proxy(false)]
class AliasActionsBuilder implements \JsonSerializable
{
    private array $aliasActions = [];

    public function removeAlias(AliasName $aliasName, IndexName $indexName)
    {
        $this->aliasActions[] = [
            'remove' => [
                'index' => $indexName->value,
                'alias' => $aliasName->value
            ]
        ];
    }

    public function addAlias(AliasName $aliasName, IndexName $indexName)
    {
        $this->aliasActions[] = [
            'add' => [
                'index' => $indexName->value,
                'alias' => $aliasName->value
            ]
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->aliasActions;
    }
}
