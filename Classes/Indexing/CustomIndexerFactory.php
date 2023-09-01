<?php

namespace Sandstorm\LightweightElasticsearch\Indexing;

use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\Elasticsearch;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ElasticsearchApiClient;
use Sandstorm\LightweightElasticsearch\Settings\ElasticsearchSettings;
use Sandstorm\LightweightElasticsearch\SharedModel\AliasName;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexDiscriminator;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexName;

/**
 * Internal, {@see Elasticsearch::customIndexer()}
 *
 * @internal
 */
#[Flow\Proxy(false)]
class CustomIndexerFactory
{
    public function __construct(
        private readonly BulkRequestSenderFactory $bulkRequestSenderFactory,

        private readonly LoggerInterface $logger,
        private readonly ElasticsearchSettings $settings,
        private readonly ElasticsearchApiClient $apiClient,
        private readonly AliasManager $aliasManager,
    ) {
    }

    public function build(IndexName $indexName, AliasName $customAliasName, IndexDiscriminator $discriminator): CustomIndexer
    {
        return new CustomIndexer(
            indexName: $indexName,
            aliasName: $customAliasName,
            discriminator: $discriminator,
            bulkRequestSender: $this->bulkRequestSenderFactory->withIndexName($indexName),

            logger: $this->logger,
            settings: $this->settings,
            apiClient: $this->apiClient,
            aliasManager: $this->aliasManager
        );
    }
}
