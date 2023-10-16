<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Indexing\AssetExtraction;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Utility\Arrays;
use Sandstorm\LightweightElasticsearch\Elasticsearch;

#[Flow\Scope('singleton')]
class IngestAttachmentAssetExtractor implements AssetExtractorInterface
{
    #[Flow\Inject]
    protected ThrowableStorageInterface $throwableStorage;

    /**
     * Takes an asset and extracts content and meta data.
     *
     * @param AssetInterface $asset
     * @param Elasticsearch $elasticsearch
     * @return AssetContent
     */
    public function extract(AssetInterface $asset, Elasticsearch $elasticsearch): AssetContent
    {
        if ($asset->getResource()->getFileSize() > $elasticsearch->settings->assetMaximumFileSize) {
            $elasticsearch->logger->info(sprintf('The asset %s with size of %s bytes exceeds the maximum size of %s bytes. The file content was not ingested.', $asset->getResource()->getFilename(), $asset->getResource()->getFileSize(), $elasticsearch->settings->assetMaximumFileSize), LogEnvironment::fromMethodName(__METHOD__));
            return $this->buildAssetContentObject([]);
        }

        $extractedAsset = null;

        $request = [
            'pipeline' => [
                'description' => 'Attachment Extraction',
                'processors' => [
                    [
                        'attachment' => [
                            'field' => 'neos_asset',
                            'indexed_chars' => 100000,
                            'ignore_missing' => true,
                        ]
                    ]
                ]
            ],
            'docs' => [
                [
                    '_source' => [
                        'neos_asset' => $this->getAssetContent($asset, $elasticsearch)
                    ]
                ]
            ]
        ];

        $result = $elasticsearch->apiClient->ingestPipelineSimulate($request);

        $extractedAsset = Arrays::getValueByPath($result, 'docs.0.doc._source.attachment');

        if (!is_array($extractedAsset)) {
            $elasticsearch->logger->error(sprintf('Error while extracting fulltext data from file "%s". See Elasticsearch error log line for details.', $asset->getResource()->getFilename()), LogEnvironment::fromMethodName(__METHOD__));
        } else {
            $elasticsearch->logger->debug(sprintf('Extracted asset %s of type %s. Extracted %s characters of content', $asset->getResource()->getFilename(), $extractedAsset['content_type'] ?? '-no-content-type-', $extractedAsset['content_length'] ?? '0'), LogEnvironment::fromMethodName(__METHOD__));
        }

        return $this->buildAssetContentObject($extractedAsset);

    }

    /**
     * @param AssetInterface $asset
     * @return string
     */
    protected function getAssetContent(AssetInterface $asset, Elasticsearch $elasticsearch): string
    {
        try {
            $stream = $asset->getResource()->getStream();
        } catch (\Exception $e) {
            $message = $this->throwableStorage->logThrowable($e);
            $elasticsearch->logger->error(sprintf('An exception occured while fetching resource with sha1 %s of asset %s. %s', $asset->getResource()->getSha1(), $asset->getResource()->getFilename(), $message), LogEnvironment::fromMethodName(__METHOD__));
            return '';
        }

        if ($stream === false) {
            $elasticsearch->logger->error(sprintf('Could not get the file stream of resource with sha1 %s of asset %s.', $asset->getResource()->getSha1(), $asset->getResource()->getFilename()), LogEnvironment::fromMethodName(__METHOD__));
            return '';
        }

        stream_filter_append($stream, 'convert.base64-encode');
        $result = stream_get_contents($stream);
        return $result !== false ? $result : '';
    }


    /**
     * @param array|null $extractedAsset
     * @return AssetContent
     */
    protected function buildAssetContentObject(?array $extractedAsset): AssetContent
    {
        return new AssetContent(
            $extractedAsset['content'] ?? '',
            $extractedAsset['title'] ?? '',
            $extractedAsset['name'] ?? '',
            $extractedAsset['author'] ?? '',
            $extractedAsset['keywords'] ?? '',
            $extractedAsset['date'] ?? '',
            $extractedAsset['content_type'] ?? '',
            $extractedAsset['content_length'] ?? 0,
            $extractedAsset['language'] ?? ''
        );
    }

}
