<?php


namespace Sandstorm\LightweightElasticsearch\Query\Highlight;


interface HighlightBuilderInterface
{
    public function buildHighlightRequestPart(): array;

}
