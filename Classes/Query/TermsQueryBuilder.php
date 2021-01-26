<?php


namespace Sandstorm\LightweightElasticsearch\Query;

use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class TermsQueryBuilder implements SearchQueryBuilderInterface
{
    /**
     * @var array[]
     */
    private array $query;

    public static function create(string $key, array $values): self
    {
        return new self($key, $values);
    }

    private function __construct(string $key, array $value)
    {
        $this->query = [
            'terms' => [
                $key => $value
            ]
        ];
    }

    public function buildQuery(): array
    {
        return $this->query;
    }
}
