<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Configuration\Cache;

use CrowdSec\RemediationEngine\Configuration\AbstractCache;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The remediation cache configuration for Redis.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */
class Redis extends AbstractCache implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('config');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()
            ->scalarNode('redis_dsn')->isRequired()->cannotBeEmpty()->end()
        ->end()
        ;

        return $treeBuilder;
    }
}
