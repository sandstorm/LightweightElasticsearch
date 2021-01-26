<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\DocumentIndexing;

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
use Sandstorm\LightweightElasticsearch\DocumentIndexing\DocumentNodeIndexer;

/**
 * This is an "implementation detail class" to {@see DocumentNodeIndexer}. Please start reading this class first, before
 * coming back here.
 *
 * This class implements a WORKAROUND: We did not want to replace {@see NodeIndexer::indexNode} completely; but wanted to
 * do a SINGLE elasticsearch request for the contents and the fulltext.
 *
 * That's why we have created our own DocumentIndexerDriver here. We expect that {@see DocumentIndexerDriver::document()}
 * and then {@see DocumentIndexerDriver::fulltext()} is called **directly afterwards**. We rely on this ordering
 * to index document and fulltext in a single Elasticsearch call.
 *
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

        // workspaceName might have been overridden in $documentData; so we need to store it back in the $document
        // before remembering it in $this->tempStorage;
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
        var_dump("FULLTEXT");

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
