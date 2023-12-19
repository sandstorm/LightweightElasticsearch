<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Indexing\AssetExtraction;

class AssetContent
{
    public function __construct(
        public readonly string $content,
        public readonly string $title,
        public readonly string $name,
        public readonly string $author,
        public readonly string $keywords,
        public readonly string $date,
        public readonly string $contentType,
        public readonly int $contentLength,
        public readonly string $language,

    ) {
    }
}
