<?php


namespace Sandstorm\LightweightElasticsearch\Query\Highlight;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
final class NeosFulltextHighlightBuilder implements HighlightBuilderInterface
{
    private int $fragmentSize;
    private ?int $fragmentCount;

    public static function create(int $fragmentSize, int $fragmentCount = null): self
    {
        return new self($fragmentSize, $fragmentCount);
    }

    private function __construct(int $fragmentSize, int $fragmentCount = null)
    {
        $this->fragmentSize = $fragmentSize;
        $this->fragmentCount = $fragmentCount;
    }


    public function buildHighlightRequestPart(): array
    {
        return [
            'fields' => [
                'neos_fulltext*' => [
                    'fragment_size' => $this->fragmentSize,
                    'no_match_size' => $this->fragmentSize,
                    'number_of_fragments' => $this->fragmentCount
                ]
            ]
        ];
    }
}
