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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

abstract class AbstractRemediation extends TestCase
{
    protected function getCacheMock(
        string $type,
        array $configs,
        LoggerInterface $logger = null,
        array $methods = []
    ): MockObject {
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
            ->setConstructorArgs(['configs' => $configs, 'logger' => $logger])
            ->onlyMethods($methods)
            ->getMock();
    }

    protected function getWatcherMock()
    {
        return $this->getMockBuilder('CrowdSec\CapiClient\Watcher')
            ->disableOriginalConstructor()
            ->onlyMethods(['getStreamDecisions'])
            ->getMock();
    }
}
