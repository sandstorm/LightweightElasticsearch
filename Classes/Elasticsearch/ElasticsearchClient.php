<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Elasticsearch;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Exception\ConfigurationException;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\Service\DimensionsService;
use Flowpack\ElasticSearch\Domain\Model\Client;
use Flowpack\ElasticSearch\Domain\Model\Index;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Flow\Annotations as Flow;

/**
 * The elasticsearch client to be used by the content repository adapter.
 *
 * Used to:
 *
 * - make the ElasticSearch Client globally available
 * - allow to access the index to be used for reading/writing in a global way
 *
 * @Flow\Scope("singleton")
 */
class ElasticsearchClient extends Client
{
    /**
     * @var string
     * @Flow\InjectConfiguration(path="elasticSearch.indexName", package="Neos.ContentRepository.Search")
     */
    protected $indexNamePrefix;

    /**
     * @var DimensionsService
     * __Flow\Inject
     */
    protected $dimensionsService;

    /**
     * @var string
     */
    protected $dimensionsHash;

    /**
     * @var \Neos\ContentRepository\Core\Projection\ContentGraph\Node
     */
    protected $contextNode;

    /**
     * @return \Neos\ContentRepository\Core\Projection\ContentGraph\Node
     */
    public function getContextNode(): \Neos\ContentRepository\Core\Projection\ContentGraph\Node
    {
        return $this->contextNode;
    }

    /**
     * @param \Neos\ContentRepository\Core\Projection\ContentGraph\Node $contextNode
     */
    public function setContextNode(\Neos\ContentRepository\Core\Projection\ContentGraph\Node $contextNode): void
    {
        $this->setDimensions($contextNode->getContext()->getTargetDimensions());
        $this->contextNode = $contextNode;
    }

    /**
     * @param array $dimensionValues
     */
    public function setDimensions(array $dimensionValues = []): void
    {
        $this->dimensionsHash = $this->dimensionsService->hash($dimensionValues);
    }

    /**
     * @return string
     */
    public function getDimensionsHash(): string
    {
        return $this->dimensionsHash;
    }

    /**
     * @param \Closure $closure
     * @param array $dimensionValues
     * @throws \Exception
     */
    public function withDimensions(\Closure $closure, array $dimensionValues = []): void
    {
        $previousDimensionHash = $this->dimensionsHash;
        try {
            $this->setDimensions($dimensionValues);
            $closure();
        } finally {
            $this->dimensionsHash = $previousDimensionHash;
        }
    }

    /**
     * Get the index name to be used
     *
     * @return string
     * @throws Exception
     * @throws ConfigurationException
     * @todo Add a contraints, if the system use content dimensions, the dimensionsHash MUST be set
     */
    public function getIndexNamePrefix(): string
    {
        $name = $this->indexNamePrefix;
        if ($this->dimensionsHash !== null) {
            $name .= '-' . $this->dimensionsHash;
        }
        return $name;
    }

    /**
     * Retrieve the index to be used for querying or on-the-fly indexing.
     * In Elasticsearch, this index is an *alias* to the currently used index.
     *
     * @return \Flowpack\ElasticSearch\Domain\Model\Index
     * @throws Exception
     * @throws \Flowpack\ElasticSearch\Exception
     * @throws ConfigurationException
     */
    public function getIndex(): Index
    {
        return $this->findIndex($this->getIndexNamePrefix());
    }
}
