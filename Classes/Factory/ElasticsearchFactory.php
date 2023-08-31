<?php

namespace Sandstorm\LightweightElasticsearch\Factory;

use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\CompilingEvaluator;
use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\Core\DocumentIndexing\BulkRequestSenderFactory;
use Sandstorm\LightweightElasticsearch\Core\DocumentIndexing\DocumentIndexer;
use Sandstorm\LightweightElasticsearch\Core\DocumentIndexing\IndexingEelEvaluator;
use Sandstorm\LightweightElasticsearch\Core\Elasticsearch;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCaller;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCalls\BulkApiCalls;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCalls\IndexApiCalls;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ElasticsearchApiClient;
use Sandstorm\LightweightElasticsearch\Core\NodeTypeMapping\NodeTypeMappingBuilder;
use Sandstorm\LightweightElasticsearch\Core\Settings\ElasticsearchSettings;

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
            $settings->baseUrl,
            $this->apiCaller,
            new IndexApiCalls(),
            new BulkApiCalls()
        );
    }

    public function build(ContentRepositoryId $contentRepositoryId): Elasticsearch
    {
        $settings = ElasticsearchSettings::fromArray($this->settings);
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $apiClient = $this->buildElasticsearchApiClient($settings);

        return new Elasticsearch(
            $settings,
            $contentRepository,
            $apiClient,
            new NodeTypeMappingBuilder($settings->defaultConfigurationPerType),
            new DocumentIndexer(
                new BulkRequestSenderFactory(
                    $apiClient,
                    $settings
                ),
                new IndexingEelEvaluator(
                    $this->eelEvaluator,
                    $settings
                ),
                $settings
            )
        );
    }
}

