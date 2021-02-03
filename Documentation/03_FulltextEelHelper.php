<?php
declare(strict_types=1);

namespace My\Package\Eel;

use Neos\Eel\ProtectedContextAwareInterface;
use Sandstorm\LightweightElasticsearch\Query\Query\BooleanQueryBuilder;
use Sandstorm\LightweightElasticsearch\Query\Query\SearchQueryBuilderInterface;
use Sandstorm\LightweightElasticsearch\Query\Query\SimpleQueryStringBuilder;
use Sandstorm\LightweightElasticsearch\Query\Query\TermQueryBuilder;

class MyQueries implements ProtectedContextAwareInterface
{

    public function faqQuery(string $query): SearchQueryBuilderInterface
    {
        return BooleanQueryBuilder::create()
            ->filter(TermQueryBuilder::create('index_discriminator', 'faq'))
            ->must(
                SimpleQueryStringBuilder::create($query ?? '')->fields([
                    'faqEntryTitle^5',
                ])
            );
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
