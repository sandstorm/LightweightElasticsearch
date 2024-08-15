<?php


namespace Sandstorm\LightweightElasticsearch\Query\Highlight;


interface HighlightBuilderInterface
{
    /**
     * @return array<mixed>
     */
    public function buildHighlightRequestPart(): array;

}
