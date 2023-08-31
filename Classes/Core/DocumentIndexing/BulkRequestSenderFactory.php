<?php
declare(strict_types=1);

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

namespace Sandstorm\LightweightElasticsearch\Core\DocumentIndexing;

use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\Core\ElasticsearchApiClient\ElasticsearchApiClient;
use Sandstorm\LightweightElasticsearch\Core\Settings\ElasticsearchSettings;
use Sandstorm\LightweightElasticsearch\Core\SharedModel\IndexName;

#[Flow\Proxy(false)]
class BulkRequestSenderFactory
{
    public function __construct(
        private readonly ElasticsearchApiClient $apiClient,
        private readonly ElasticsearchSettings $settings,
    ) {
    }

    public function withIndexName(IndexName $indexName): BulkRequestSender
    {
        return new BulkRequestSender(
            $indexName,
            $this->apiClient,
            $this->settings
        );
    }
}
