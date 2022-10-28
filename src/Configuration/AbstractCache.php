<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Configuration;

use CrowdSec\RemediationEngine\Constants;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * The remediation cache configuration.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */
abstract class AbstractCache implements ConfigurationInterface
{
    /**
     * Common cache settings
     *
     * @param NodeDefinition|ArrayNodeDefinition $rootNode
     * @return void
     * @throws InvalidArgumentException
     */
    protected function addCommonNodes($rootNode)
    {
        $rootNode->children()
            ->integerNode('clean_ip_cache_duration')
            ->min(1)->defaultValue(Constants::CACHE_EXPIRATION_FOR_CLEAN_IP)
            ->end()
            ->integerNode('bad_ip_cache_duration')
            ->min(1)->defaultValue(Constants::CACHE_EXPIRATION_FOR_BAD_IP)
            ->end();
    }
}