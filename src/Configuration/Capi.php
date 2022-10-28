<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Configuration;

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
     * @return TreeBuilder
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('config');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()
            ->enumNode('fallback_remediation')
                ->values(
                    [
                        Constants::REMEDIATION_BYPASS,
                        Constants::REMEDIATION_BAN,
                        Constants::REMEDIATION_CAPTCHA
                    ]
                )
                ->defaultValue(Constants::REMEDIATION_BYPASS)
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
            ->defaultValue(Constants::ORDERED_REMEDIATIONS)
            ->end()
        ->end()
        ;

        return $treeBuilder;
    }
}
