<?php

namespace Sandstorm\LightweightElasticsearch\Command;

use Neos\Flow\Annotations as Flow;
use Psr\Log\LoggerInterface;

#[Flow\Proxy(false)]
final class ErrorCounter
{
    private int $errorCount = 0;

    public function __construct(
        private readonly LoggerInterface $logger,
    )
    {
    }

    /**
     * @param string $message
     * @param $context
     */
    public function log(string $message, $context): void
    {
        $this->errorCount++;
        $this->logger->error($message, $context);
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function hasError(): bool
    {
        return $this->errorCount > 0;
    }
}
