<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Configuration;

use CrowdSec\RemediationEngine\CapiRemediation;
use CrowdSec\RemediationEngine\Constants;
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
     * @throws \RuntimeException
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('config');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()
            ->scalarNode('fallback_remediation')
                ->defaultValue(Constants::REMEDIATION_BYPASS)
            ->end()
            ->arrayNode('ordered_remediations')->cannotBeEmpty()
                ->validate()
                ->ifArray()
                ->then(function (array $remediations) {
                    // Remove Bypass
                    foreach ($remediations as $key => $remediation){
                        if($remediation === Constants::REMEDIATION_BYPASS){
                            unset($remediations[$key]);
                        }
                    }
                    // Add Bypass as the lowest priority remediation
                    $remediations = array_merge($remediations, [Constants::REMEDIATION_BYPASS]);
                    return array_values(array_unique($remediations));
                })
                ->end()
                ->scalarPrototype()->cannotBeEmpty()
                ->end()
            ->defaultValue(array_merge(CapiRemediation::ORDERED_REMEDIATIONS, [Constants::REMEDIATION_BYPASS]))
            ->end()
        ->end()
        ;
        $this->validate($rootNode);

        return $treeBuilder;
    }

    /**
     * Conditional validation.
     *
     * @return void
     */
    private function validate($rootNode)
    {
        $rootNode->validate()
            ->ifTrue(function (array $v) {
                return $v['fallback_remediation'] !== Constants::REMEDIATION_BYPASS &&
                       !in_array($v['fallback_remediation'], $v['ordered_remediations']);
            })
            ->thenInvalid('Fallback remediation must belong to ordered remediations.')
            ->end();
    }
}
