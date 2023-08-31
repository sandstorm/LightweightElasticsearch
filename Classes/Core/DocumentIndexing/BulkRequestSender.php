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
class BulkRequestSender
{

    /**
     * JSON line Payload of the current requests
     * @var string
     */
    protected $payloadLines = [];

    /**
     * Size in octet of the total payload
     * @var int
     */
    protected $size = 0;

    public function __construct(
        private readonly IndexName $indexName,
        private readonly ElasticsearchApiClient $apiClient,
        private readonly ElasticsearchSettings $settings,
    ) {
    }

    public function indexDocument(string $elasticsearchDocumentId, array $documentData): void
    {
        $this->addPayloadLine(
            [
                'index' => [
                    '_id' => $elasticsearchDocumentId,
                    '_index' => $this->indexName->value,
                ]
            ],
            $documentData
        );
    }

    private function addPayloadLine(array $headerLine, array $bodyLine): self
    {
        $encodedHeader = json_encode($headerLine);
        $encodedBody = json_encode($bodyLine);

        $this->payloadLines[] = $encodedHeader;
        $this->payloadLines[] = $encodedBody;
        $this->size += strlen($encodedHeader) + strlen($encodedBody);

        if (count($this->payloadLines) > $this->settings->indexingBatchSizeElements || $this->size > $this->settings->indexingBatchSizeOctets) {
            $this->sendRequest();
        }
        return $this;
    }

    /**
     * MUST be called at the end of usage, to send the last request
     */
    public function close(): void
    {
        $this->sendRequest();
    }

    private function sendRequest(): void
    {
        $this->apiClient->bulkIndex($this->indexName, $this->payloadLines);
        $this->payloadLines = [];
        $this->size = [];
    }
}
