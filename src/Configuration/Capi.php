<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Configuration;

use CrowdSec\RemediationEngine\CapiRemediation;
use CrowdSec\RemediationEngine\Constants;
use RuntimeException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The Capi remediation configuration.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */
class Capi implements ConfigurationInterface
{

    /**
     * @return TreeBuilder
     * @throws RuntimeException
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $defaultOrderedRemediations = CapiRemediation::ORDERED_REMEDIATIONS;
        $treeBuilder = new TreeBuilder('config');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()
            ->scalarNode('fallback_remediation')
                ->defaultValue($defaultOrderedRemediations[1])
            ->end()
            ->arrayNode('ordered_remediations')->cannotBeEmpty()
                ->validate()
                ->ifArray()
                ->then(function (array $value) {
                    return array_unique(array_values($value));
                })
                ->end()
                ->scalarPrototype()->cannotBeEmpty()
                ->end()
            ->defaultValue(CapiRemediation::ORDERED_REMEDIATIONS)
            ->end()
        ->end()
        ;
        $this->validate($rootNode);

        return $treeBuilder;
    }


    /**
     * Conditional validation
     *
     * @param $rootNode
     * @return void
     */
    private function validate($rootNode)
    {
        $rootNode->validate()
            ->ifTrue(function (array $v) {
                return !in_array($v['fallback_remediation'], $v['ordered_remediations']);
            })
            ->thenInvalid('Fallback remediation must belong to ordered remediations')
            ->end()
            ->validate()
            ->ifTrue(function (array $v) {
                return !in_array(Constants::REMEDIATION_BYPASS, $v['ordered_remediations']);
            })
            ->thenInvalid('Bypass remediation must belong to ordered remediations.')
            ->end();
    }
}
