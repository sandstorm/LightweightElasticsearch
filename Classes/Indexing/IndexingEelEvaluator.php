<?php

namespace Sandstorm\LightweightElasticsearch\Indexing;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\Eel\EelEvaluatorInterface;
use Neos\Eel\Utility as EelUtility;
use Sandstorm\LightweightElasticsearch\Settings\ElasticsearchSettings;
use Sandstorm\LightweightElasticsearch\Settings\PropertySearchSettings;

/**
 * Implementation utility of {@see SubgraphIndexer}, evaluating Eel expressions in "search.indexing"
 * and "search.fulltextExtractor"
 */
class IndexingEelEvaluator
{

    public function __construct(
        private readonly EelEvaluatorInterface $eelEvaluator,
        private readonly ElasticsearchSettings $settings,
    ) {
    }

    /**
     * the default context variables available inside Eel (cached)
     */
    protected ?array $defaultContextVariables = null;

    public function runPropertyIndexingExpression(PropertySearchSettings $propertySearchSettings, Node $node): mixed
    {
        return $this->evaluate($propertySearchSettings->indexingEelExpression, $node, $propertySearchSettings->propertyName);
    }

    public function runFulltextExpression(PropertySearchSettings $propertySearchSettings, Node $node, array &$fulltextIndex): void
    {
        $extractedFulltext = $this->evaluate($propertySearchSettings->fulltextExtractorEelExpression, $node, $propertySearchSettings->propertyName);

        if (!is_array($extractedFulltext)) {
            throw new \RuntimeException('The fulltext index for property "' . $propertySearchSettings->propertyName . '" of node "' . $node->nodeAggregateId->value . '" could not be retrieved; the Eel expression "' . $propertySearchSettings->fulltextExtractorEelExpression . '" is no valid fulltext extraction expression.', 1693468443);
        }

        foreach ($extractedFulltext as $bucket => $value) {
            if (!isset($fulltextIndex[$bucket])) {
                $fulltextIndex[$bucket] = '';
            }

            $value = trim($value);
            if ($value !== '') {
                $fulltextIndex[$bucket] .= ' ' . $value;
            }
        }
    }

    /**
     * Evaluate an Eel expression.
     *
     * @param string $expression The Eel expression to evaluate
     * @param Node $node
     * @param string $propertyName
     * @return mixed The result of the evaluated Eel expression
     */
    private function evaluate(string $expression, Node $node, string $propertyName): mixed
    {
        if ($this->defaultContextVariables === null) {
            $this->defaultContextVariables = EelUtility::getDefaultContextVariables($this->settings->defaultContext);
        }

        $contextVariables = array_merge($this->defaultContextVariables, [
            'node' => $node,
            'propertyName' => $propertyName,
            'value' => $node->getProperty($propertyName),
        ]);

        return EelUtility::evaluateEelExpression($expression, $this->eelEvaluator, $contextVariables);
    }
}
