<?php

namespace Sandstorm\LightweightElasticsearch\Core\DocumentIndexing;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\Eel\EelEvaluatorInterface;
use Neos\Eel\Utility as EelUtility;
use Sandstorm\LightweightElasticsearch\Core\Settings\ElasticsearchSettings;
use Sandstorm\LightweightElasticsearch\Core\Settings\PropertySearchSettings;

class IndexingEelEvaluator
{

    public function __construct(
        private readonly EelEvaluatorInterface $eelEvaluator,
        private readonly ElasticsearchSettings $settings,
    ) {
    }

    /**
     * the default context variables available inside Eel (cached)
     *
     * @var array
     */
    protected $defaultContextVariables;

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
     * @param mixed $propertyValue
     * @return mixed The result of the evaluated Eel expression
     * @throws \Neos\Eel\Exception
     */
    private function evaluate(string $expression, Node $node, string $propertyName)
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
