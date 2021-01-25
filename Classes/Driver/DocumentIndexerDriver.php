<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Driver;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Domain\Model\TargetContextPath;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\AbstractIndexerDriver;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Driver\IndexerDriverInterface;
use Flowpack\ElasticSearch\Domain\Model\Document as ElasticSearchDocument;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Search\Indexer\NodeIndexerInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;

/**
 * @Flow\Scope("singleton")
 */
class DocumentIndexerDriver extends AbstractIndexerDriver implements IndexerDriverInterface
{
    /**
     * @var \SplObjectStorage
     */
    protected $tempStorage;

    /**
     * {@inheritdoc}
     */
    public function document(string $indexName, NodeInterface $node, ElasticSearchDocument $document, array $documentData): array
    {
        // We expect document() being called directly before fulltext(), see
        // https://github.com/Flowpack/Flowpack.ElasticSearch.ContentRepositoryAdaptor/blob/master/Classes/Indexer/NodeIndexer.php#L267-L271
        //
        // we only want to create a single bulk request with all data

        // workspaceName might have been overridden
        $document->setData($documentData);

        $this->tempStorage->attach($node, ['doc' => $document, 'index' => $indexName]);

        return [];
    }

    /**
     * {@inheritdoc}
     * @throws \Neos\Flow\Persistence\Exception\IllegalObjectTypeException
     */
    public function fulltext(NodeInterface $node, array $fulltextIndexOfNode, string $targetWorkspaceName = null): array
    {
        assert($this->tempStorage->offsetExists($node));
        $documentAndIndex = $this->tempStorage->offsetGet($node);
        $document = $documentAndIndex['doc'];
        $indexName = $documentAndIndex['index'];
        assert($document instanceof ElasticSearchDocument);
        $this->tempStorage->offsetUnset($node);

        return [
            [
                'index' => [
                    '_type' => '_doc',
                    '_id' => $document->getId(),
                    '_index' => $indexName
                ]
            ],
            $document->getData()
        ];
    }
}
