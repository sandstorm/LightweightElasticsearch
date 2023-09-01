<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\ElasticsearchApiClient;

/*
 * This file is part of the Flowpack.ElasticSearch package.
 *
 * (c) Contributors of the Flowpack Team - flowpack.org
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Client\Browser;
use Neos\Flow\Http\Client\CurlEngine;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use Sandstorm\LightweightElasticsearch\Settings\ElasticsearchSettings;

/**
 * Handles the API requests. Implementation detail of the {@see ElasticsearchApiClient} and the relevant API Calls.
 *
 * @internal
 */
class ApiCaller
{
    /**
     * @Flow\Inject
     * @var Browser
     */
    protected $browser;

    /**
     * @Flow\Inject
     * @var ServerRequestFactoryInterface
     */
    protected $requestFactory;

    /**
     * @Flow\Inject
     * @var StreamFactoryInterface
     */
    protected $contentStreamFactory;

    /**
     * @internal
     */
    public function initializeRequestEngine(ElasticsearchSettings $settings)
    {
        $requestEngine = new CurlEngine();
        $requestEngine->setOption(CURLOPT_TIMEOUT, $settings->transferConnectionTimeout);
        $requestEngine->setOption(CURLOPT_SSL_VERIFYPEER, $settings->transferSslVerifyPeer ? 2 : 0);
        $requestEngine->setOption(CURLOPT_SSL_VERIFYHOST, $settings->transferSslVerifyHost ? 2 : 0);
        $this->browser->setRequestEngine($requestEngine);
    }

    public function request($method, UriInterface $url, string $content = null): ResponseInterface
    {
        $request = $this->requestFactory->createServerRequest($method, $url);
        $request = $request->withHeader('Content-Type', 'application/json');

        if ($content !== null) {
            $request = $request->withBody($this->contentStreamFactory->createStream($content));
        }

        return $this->browser->sendRequest($request);
    }
}
