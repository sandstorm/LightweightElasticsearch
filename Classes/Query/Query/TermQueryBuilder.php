<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query\Query;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Proxy(false)
 */
class TermQueryBuilder implements SearchQueryBuilderInterface
{
    /**
     * @var array[]
     */
    private array $query;

    public static function create(string $key, $value): self
    {
        return new self($key, $value);
    }

    private function __construct(string $key, $value)
    {
        $this->query = [
            'term' => [
                $key => $value
            ]
        ];
    }

    public function buildQuery(): array
    {
        return $this->query;
    }
}
