<?php


namespace Sandstorm\LightweightElasticsearch\Query;


class TermQueryBuilder implements SearchQueryBuilderInterface
{
    /**
     * @var array[]
     */
    private array $query;

    public function __construct(string $key, $value)
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
