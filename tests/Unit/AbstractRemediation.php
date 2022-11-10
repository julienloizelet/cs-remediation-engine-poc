<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Tests\Unit;

/**
 * Abstract class for remediation test.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */

use PHPUnit\Framework\TestCase;

abstract class AbstractRemediation extends TestCase
{

    protected function getWatcherMock()
    {
        return $this->getMockBuilder('CrowdSec\CapiClient\Watcher')
            ->disableOriginalConstructor()
            ->onlyMethods(['getStreamDecisions'])
            ->getMock();
    }


    protected function getPhpfilesCacheMock(array $configs)
    {
        return $this->getMockBuilder('CrowdSec\RemediationEngine\CacheStorage\PhpFiles')
            ->setConstructorArgs(['configs' => $configs])
            ->onlyMethods(['retrieveDecisionsForIp', 'setStreamMode'])
            ->getMock();
    }

    protected function getCacheMock(string $type, array $configs)
    {
        switch ($type) {
            case 'PhpFilesAdapter':
                $class = 'CrowdSec\RemediationEngine\CacheStorage\PhpFiles';
                break;
            case 'RedisAdapter':
                $class = 'CrowdSec\RemediationEngine\CacheStorage\Redis';
                break;
            case 'MemcachedAdapter':
                $class = 'CrowdSec\RemediationEngine\CacheStorage\Memcached';
                break;
            default:
                throw new \Exception('Unknown $type:' . $type);
        }

        return $this->getMockBuilder($class)
            ->setConstructorArgs(['configs' => $configs])
            ->onlyMethods(['retrieveDecisionsForIp', 'setStreamMode'])
            ->getMock();
    }

}
