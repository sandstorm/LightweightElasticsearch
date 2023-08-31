<?php

namespace Sandstorm\LightweightElasticsearch\Factory;

use A\B;
use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\CompilingEvaluator;
use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls\IngestPipelineApiCalls;
use Sandstorm\LightweightElasticsearch\Indexing\AliasManager;
use Sandstorm\LightweightElasticsearch\Indexing\BulkRequestSenderFactory;
use Sandstorm\LightweightElasticsearch\Indexing\SubgraphIndexer;
use Sandstorm\LightweightElasticsearch\Indexing\IndexingEelEvaluator;
use Sandstorm\LightweightElasticsearch\Elasticsearch;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCaller;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls\AliasApiCalls;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls\BulkApiCalls;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls\IndexApiCalls;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ApiCalls\SearchApiCalls;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ElasticsearchApiClient;
use Sandstorm\LightweightElasticsearch\NodeTypeMapping\NodeTypeMappingBuilder;
use Sandstorm\LightweightElasticsearch\Settings\ElasticsearchSettings;

#[Flow\Scope("singleton")]
class ElasticsearchFactory
{
    /**
     * @Flow\InjectConfiguration(package="Sandstorm.LightweightElasticsearch")
     * @var array
     */
    protected array $settings;

    public function __construct(
        private readonly ContentRepositoryRegistry $contentRepositoryRegistry,
        private readonly ApiCaller $apiCaller,
        private readonly CompilingEvaluator $eelEvaluator,
    ) {
    }

    public function injectSettings(array $settings): void
    {
        $this->settings = $settings;

    }

    private function buildElasticsearchApiClient(ElasticsearchSettings $settings): ElasticsearchApiClient
    {
        $this->apiCaller->initializeRequestEngine($settings);
        return new ElasticsearchApiClient(
            baseUrl: $settings->baseUrl,
            apiCaller: $this->apiCaller,
            aliasApi: new AliasApiCalls(),
            indexApi: new IndexApiCalls(),
            bulkApi: new BulkApiCalls(),
            ingestPipelineApi: new IngestPipelineApiCalls(),
            searchApi: new SearchApiCalls()
        );
    }

    public function build(ContentRepositoryId $contentRepositoryId, LoggerInterface $logger): Elasticsearch
    {
        $settings = ElasticsearchSettings::fromArray($this->settings);
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $apiClient = $this->buildElasticsearchApiClient($settings);

        return new Elasticsearch(
            settings: $settings,
            contentRepository: $contentRepository,
            apiClient: $apiClient,
            nodeTypeMappingBuilder: new NodeTypeMappingBuilder(
                $settings->defaultConfigurationPerType,
                $logger
            ),
            documentIndexer: new SubgraphIndexer(
                new BulkRequestSenderFactory(
                    $apiClient,
                    $settings
                ),
                new IndexingEelEvaluator(
                    $this->eelEvaluator,
                    $settings
                ),
                $settings
            ),
            aliasManager: new AliasManager(
                $apiClient
            ),
            logger: $logger
        );
    }
}

