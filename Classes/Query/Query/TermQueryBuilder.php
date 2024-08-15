<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query\Query;
use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
class TermQueryBuilder implements SearchQueryBuilderInterface
{
    /**
     * @var array<mixed>
     */
    private array $query;

    public static function create(string $key, mixed $value): self
    {
        return new self($key, $value);
    }

    private function __construct(string $key, mixed $value)
    {
        $this->query = [
            'term' => [
                $key => $value
            ]
        ];
    }

    /**
     * @return array<mixed>
     */
    public function buildQuery(): array
    {
        return $this->query;
    }
}
