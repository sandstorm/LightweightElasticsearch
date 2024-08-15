<?php
declare(strict_types=1);

namespace Sandstorm\LightweightElasticsearch\Query\Query;
use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
class TermsQueryBuilder implements SearchQueryBuilderInterface
{
    /**
     * @var array<mixed>
     */
    private array $query;

    /**
     * @param array<mixed> $values
     */
    public static function create(string $key, array $values): self
    {
        return new self($key, $values);
    }

    /**
     * @param array<mixed> $value
     */
    private function __construct(string $key, array $value)
    {
        $this->query = [
            'terms' => [
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
