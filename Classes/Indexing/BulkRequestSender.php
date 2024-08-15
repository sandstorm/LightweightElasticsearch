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

namespace Sandstorm\LightweightElasticsearch\Indexing;

use Neos\Flow\Annotations as Flow;
use Sandstorm\LightweightElasticsearch\ElasticsearchApiClient\ElasticsearchApiClient;
use Sandstorm\LightweightElasticsearch\Settings\ElasticsearchSettings;
use Sandstorm\LightweightElasticsearch\SharedModel\IndexName;

/**
 * Implementation detail of {@see SubgraphIndexer}, which sends the indexing documents to Elasticsearch when certain
 * thresholds are reached (i.e. how many documents or how big the payload is).
 */
#[Flow\Proxy(false)]
class BulkRequestSender
{

    /**
     * JSON line Payload of the current requests
     * @var string[]
     */
    protected array $payloadLines = [];

    /**
     * Size in octet of the total payload
     * @var int
     */
    protected int $size = 0;

    public function __construct(
        private readonly IndexName $indexName,
        private readonly ElasticsearchApiClient $apiClient,
        private readonly ElasticsearchSettings $settings,
    ) {
    }

    /**
     * @param array<mixed> $documentData
     */
    public function indexDocument(array $documentData, string $documentId = null): void
    {
        if ($documentId === null) {
            $this->addPayloadLine(
                [
                    'index' => [
                        '_index' => $this->indexName->value,
                    ]
                ],
                $documentData
            );
        } else {
            $this->addPayloadLine(
                [
                    'index' => [
                        '_id' => $documentId,
                        '_index' => $this->indexName->value,
                    ]
                ],
                $documentData
            );
        }
    }

    /**
     * @param array<mixed> $headerLine
     * @param array<mixed> $bodyLine
     */
    private function addPayloadLine(array $headerLine, array $bodyLine): self
    {
        $encodedHeader = json_encode($headerLine, JSON_THROW_ON_ERROR);
        $encodedBody = json_encode($bodyLine, JSON_THROW_ON_ERROR);

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
        $this->size = 0;
    }
}
