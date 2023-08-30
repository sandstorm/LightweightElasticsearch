<?php

namespace Sandstorm\LightweightElasticsearch\Factory;

use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\Core\Elasticsearch;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCaller;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ApiCalls\IndexApiCalls;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ElasticsearchApiClient;
use Sandstorm\LightweightElasticsearch\Core\NodeType\NodeTypeMappingBuilder;
use Sandstorm\LightweightElasticsearch\Core\Settings\ElasticsearchSettings;

#[Flow\Scope("singleton")]
class ElasticsearchFactory
{
    private array $settings;

    public function __construct(
        private readonly ContentRepositoryRegistry $contentRepositoryRegistry,
        private readonly ApiCaller $apiCaller,
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
            new IndexApiCalls()
        );
    }

    public function build(ContentRepositoryId $contentRepositoryId): Elasticsearch
    {
        $settings = ElasticsearchSettings::fromArray($this->settings);

        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        return new Elasticsearch(
            $settings,
            $contentRepository,
            $this->buildElasticsearchApiClient($settings),
            new NodeTypeMappingBuilder($settings->defaultConfigurationPerType)
        );
    }
}

