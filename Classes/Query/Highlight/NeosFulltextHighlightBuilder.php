<?php


namespace Sandstorm\LightweightElasticsearch\Query\Highlight;

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
final class NeosFulltextHighlightBuilder implements HighlightBuilderInterface, ProtectedContextAwareInterface
{
    private int $fragmentSize;
    private ?int $fragmentCount;
    private array $extraHighlightFields = [];

    public static function create(int $fragmentSize, int $fragmentCount = null): self
    {
        return new self($fragmentSize, $fragmentCount);
    }

    private function __construct(int $fragmentSize, int $fragmentCount = null)
    {
        $this->fragmentSize = $fragmentSize;
        $this->fragmentCount = $fragmentCount;
    }

    /**
     * add an extra field to the fulltext
     *
     * @param string $fieldName
     * @return $this
     */
    public function extraField(string $fieldName): self
    {
        $this->extraHighlightFields[] = $fieldName;
        return $this;
    }

    public function buildHighlightRequestPart(array $extraFields = []): array
    {
        $highlightRequestPart = [
            'fields' => [
                'neos_fulltext*' => [
                    'fragment_size' => $this->fragmentSize,
                    'no_match_size' => $this->fragmentSize,
                    'number_of_fragments' => $this->fragmentCount
                ]
            ]
        ];

        foreach ($this->extraHighlightFields as $fieldName) {
            $highlightRequestPart['fields'][$fieldName] = $highlightRequestPart['fields']['neos_fulltext*'];
        }

        return $highlightRequestPart;
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
