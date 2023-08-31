<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\AssetExtraction;

/*
 * This file is part of the Flowpack.ElasticSearch.ContentRepositoryAdaptor package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Media\Domain\Model\AssetInterface;
use Sandstorm\LightweightElasticsearch\AssetExtraction\Dto\AssetContent;

interface AssetExtractorInterface
{
    /**
     * Takes an asset and extracts content and meta data.
     *
     * @param AssetInterface $asset
     * @return AssetContent
     */
    public function extract(AssetInterface $asset): AssetContent;
}
