<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Tests\Unit;

/**
 * Test for cache.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */

use CrowdSec\RemediationEngine\CacheStorage\AbstractCache;
use CrowdSec\RemediationEngine\CacheStorage\CacheException;
use CrowdSec\RemediationEngine\CacheStorage\Memcached;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\CacheStorage\Redis;
use CrowdSec\RemediationEngine\Constants;
use CrowdSec\RemediationEngine\Logger\FileLog;
use CrowdSec\RemediationEngine\Tests\Constants as TestConstants;
use CrowdSec\RemediationEngine\Tests\PHPUnitUtil;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

/**

 * @uses \CrowdSec\RemediationEngine\CacheStorage\Memcached::setCustomErrorHandler
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Memcached::unsetCustomErrorHandler
 * @uses \CrowdSec\RemediationEngine\Configuration\AbstractCache::addCommonNodes
 * @uses \CrowdSec\RemediationEngine\Configuration\Cache\Memcached::getConfigTreeBuilder
 * @uses \CrowdSec\RemediationEngine\Configuration\Cache\PhpFiles::getConfigTreeBuilder
 * @uses \CrowdSec\RemediationEngine\Configuration\Cache\Redis::getConfigTreeBuilder
 * @uses \CrowdSec\RemediationEngine\Logger\FileLog::__construct
 *
 * @covers \CrowdSec\RemediationEngine\CacheStorage\Memcached::clear
 * @covers \CrowdSec\RemediationEngine\CacheStorage\Memcached::commit
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::__construct
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::clear
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::commit
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getAdapter
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::prune
 * @covers \CrowdSec\RemediationEngine\CacheStorage\Memcached::__construct
 * @covers \CrowdSec\RemediationEngine\CacheStorage\PhpFiles::__construct
 * @covers \CrowdSec\RemediationEngine\CacheStorage\Redis::__construct
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getConfig
 * @covers \CrowdSec\RemediationEngine\CacheStorage\Memcached::configure
 * @covers \CrowdSec\RemediationEngine\CacheStorage\PhpFiles::configure
 * @covers \CrowdSec\RemediationEngine\CacheStorage\Redis::configure
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getCacheKey
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::parseDurationToSeconds
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::manageRange
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getMaxExpiration
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::cleanCachedValues
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::format
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getCachedIndex
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getItem
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getTags
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::handleBadIpDuration
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::retrieveDecisionsForIp
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::saveDeferred
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::store
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::storeDecision
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::updateCacheItem
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::remove
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::removeDecision
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::formatIpV4Range
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getRangeIntForIp
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::handleRangeScoped
 *
 *
 */
final class CacheTest extends TestCase
{
    /**
     * @var AbstractCache
     */
    private $cacheStorage;

    /**
     * @var PhpFiles
     */
    private $phpFileStorage;

    /**
     * @var Redis
     */
    private $redisStorage;

    /**
     * @var Memcached
     */
    private $memcachedStorage;

    public function setUp(): void
    {
        $this->root = vfsStream::setup(TestConstants::TMP_DIR);
        $currentDate = date('Y-m-d');
        $this->debugFile = 'debug-' . $currentDate . '.log';
        $this->prodFile = 'prod-' . $currentDate . '.log';
        $this->logger = new FileLog(['log_directory_path' => $this->root->url(), 'debug_mode' => true]);

        $cachePhpfilesConfigs = ['fs_cache_path' => $this->root->url()];
        $this->phpFileStorage = new PhpFiles($cachePhpfilesConfigs, $this->logger);
        $cacheMemcachedConfigs = [
            'memcached_dsn' => getenv('memcached_dsn') ?: 'memcached://memcached:11211',
        ];
        $this->memcachedStorage = new Memcached($cacheMemcachedConfigs, $this->logger);
        $cacheRedisConfigs = [
            'redis_dsn' => getenv('redis_dsn') ?: 'redis://redis:6379',
        ];
        $this->redisStorage = new Redis($cacheRedisConfigs, $this->logger);
    }

    protected function tearDown(): void
    {
        $this->cacheStorage->clear();
    }

    public function cacheTypeProvider(): array
    {
        return [
            'PhpFilesAdapter' => ['PhpFilesAdapter'],
            'RedisAdapter' => ['RedisAdapter'],
            'MemcachedAdapter' => ['MemcachedAdapter'],
        ];
    }

    private function setCache(string $type)
    {
        switch ($type) {
            case 'PhpFilesAdapter':
                $this->cacheStorage = $this->phpFileStorage;
                break;
            case 'RedisAdapter':
                $this->cacheStorage = $this->redisStorage;
                break;
            case 'MemcachedAdapter':
                $this->cacheStorage = $this->memcachedStorage;
                break;
            default:
                throw new \Exception('Unknown $type:' . $type);
        }
    }

    /**
     * @dataProvider cacheTypeProvider
     */
    public function testCache($cacheType)
    {
        $this->setCache($cacheType);

        switch ($cacheType) {
            case 'PhpFilesAdapter':
                $this->assertEquals(
                    'Symfony\Component\Cache\Adapter\TagAwareAdapter',
                    get_class($this->cacheStorage->getAdapter()),
                    'Adapter should be as expected'
                );
                break;
            case 'RedisAdapter':
                $this->assertEquals(
                    'Symfony\Component\Cache\Adapter\RedisTagAwareAdapter',
                    get_class($this->cacheStorage->getAdapter()),
                    'Adapter should be as expected'
                );
                break;
            case 'MemcachedAdapter':
                $this->assertEquals(
                    'Symfony\Component\Cache\Adapter\MemcachedAdapter',
                    get_class($this->cacheStorage->getAdapter()),
                    'Adapter should be as expected'
                );
                break;
            default:
                throw new \Exception('Unknown $type:' . $cacheType);
        }

        $this->assertEquals(
            Constants::CACHE_EXPIRATION_FOR_CLEAN_IP,
            $this->cacheStorage->getConfig('clean_ip_cache_duration'),
            'Should set default config'
        );

        $result = $this->cacheStorage->commit();
        $this->assertEquals(
            true,
            $result,
            'Commit should be ok'
        );

        $result = $this->cacheStorage->clear();
        $this->assertEquals(
            true,
            $result,
            'Cache should be clearable'
        );

        $error = '';
        try {
            $this->cacheStorage->prune();
        } catch (CacheException $e) {
            $error = $e->getMessage();
        }
        if ($cacheType === 'PhpFilesAdapter') {
            $this->assertEquals(
                '',
                $error,
                'Php files Cache can be pruned'
            );
        } else {
            PHPUnitUtil::assertRegExp(
                $this,
                '/can not be pruned/',
                $error,
                'Should throw error if try to prune'
            );
        }
    }

    public function testCacheKey()
    {
        $this->setCache('PhpFilesAdapter');

        $cacheKey = $this->cacheStorage->getCacheKey('ip', '1.2.3.4');

        $this->assertEquals(
            'ip_1.2.3.4',
            $cacheKey,
            'Should format cache key'
        );

        $error = '';
        try {
            $this->cacheStorage->getCacheKey('Dummy', '1.2.3.4');
        } catch (CacheException $e) {
            $error = $e->getMessage();
        }

        PHPUnitUtil::assertRegExp(
            $this,
            '/Unknown scope/',
            $error,
            'Should throw error if unknown scope'
        );

        $cacheKey = $this->cacheStorage->getCacheKey('range', '1.2.3.4/24');
        $this->assertEquals(
            'range_1.2.3.4_24',
            $cacheKey,
            'Should format cache key'
        );

        $cacheKey = $this->cacheStorage->getCacheKey('ip', '1111::2222::3333@4=');
        $this->assertEquals(
            'ip_1111__2222__3333_4_',
            $cacheKey,
            'Should format cache key'
        );
    }

    public function testPrivateOrProtectedMethods()
    {

        $this->setCache('PhpFilesAdapter');
        // parseDurationToSeconds
        $result = PHPUnitUtil::callMethod(
            $this->cacheStorage,
            'parseDurationToSeconds',
            ['1h']
        );
        $this->assertEquals(
            3600,
            $result,
            'Should convert in seconds'
        );

        $result = PHPUnitUtil::callMethod(
            $this->cacheStorage,
            'parseDurationToSeconds',
            ['147h']
        );
        $this->assertEquals(
            3600*147,
            $result,
            'Should convert in seconds'
        );

        $result = PHPUnitUtil::callMethod(
            $this->cacheStorage,
            'parseDurationToSeconds',
            ['147h23m43s']
        );
        $this->assertEquals(
            3600*147+23*60+43,
            $result,
            'Should convert in seconds'
        );

        $result = PHPUnitUtil::callMethod(
            $this->cacheStorage,
            'parseDurationToSeconds',
            ['147h23m43000.5665ms']
        );
        $this->assertEquals(
            3600*147+23*60+43,
            $result,
            'Should convert in seconds'
        );

        $result = PHPUnitUtil::callMethod(
            $this->cacheStorage,
            'parseDurationToSeconds',
            ['23m43s']
        );
        $this->assertEquals(
            23*60+43,
            $result,
            'Should convert in seconds'
        );
        $result = PHPUnitUtil::callMethod(
            $this->cacheStorage,
            'parseDurationToSeconds',
            ['-23m43s']
        );
        $this->assertEquals(
            -23*60-43,
            $result,
            'Should convert in seconds'
        );

        $result = PHPUnitUtil::callMethod(
            $this->cacheStorage,
            'parseDurationToSeconds',
            ['abc']
        );
        $this->assertEquals(
            0,
            $result,
            'Should return 0 on bad format'
        );
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*400.*"type":"CACHE_DURATION_PARSE_ERROR"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // manageRange
        $decision = $this->getMockBuilder('CrowdSec\RemediationEngine\Decision')
            ->disableOriginalConstructor()
            ->onlyMethods(['getValue', 'toArray'])
            ->getMock();
        $decision->method('getValue')->will(
            $this->onConsecutiveCalls(
                '1.2.3.4', // Test 1 : failed because not a range
                '2001:0db8:85a3:0000:0000:8a2e:0370:7334/24', // Test 2 IP v6 range not implemented
                '1.2.3.4/24' // Test 3 :ok
            )
        );
        // Test 1
        $result = PHPUnitUtil::callMethod(
            $this->cacheStorage,
            'manageRange',
            [$decision]
        );
        $this->assertEquals(
            null,
            $result,
            'Should return null for an IP with no range'
        );
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*400.*"type":"INVALID_RANGE"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // Test 2
        $result = PHPUnitUtil::callMethod(
            $this->cacheStorage,
            'manageRange',
            [$decision]
        );
        $this->assertEquals(
            null,
            $result,
            'Should return null for an IP V6'
        );
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*300.*"type":"IPV6_RANGE_NOT_IMPLEMENTED"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // Test 3
        $result = PHPUnitUtil::callMethod(
            $this->cacheStorage,
            'manageRange',
            [$decision]
        );
        $this->assertEquals(
            'IPLib\Range\Subnet',
            get_class($result),
            'Should return correct range'
        );
        // getMaxExpiration
        $itemsToCache = [
            [
                'ban',
                1668577960,
                'CAPI-ban-range-52.3.230.0/24',
                0
            ],
            [
                'ban',
                1668577970,
                'CAPI-ban-range-52.3.230.0/24',
                0
            ],

        ];
        $result = PHPUnitUtil::callMethod(
            $this->cacheStorage,
            'getMaxExpiration',
            [$itemsToCache]
        );
        $this->assertEquals(
            1668577970,
            $result,
            'Should return correct maximum'
        );
        // cleanCachedValues
        $cachedValues = [
            [
                'ban',
                911125444, //  Sunday 15 November 1998 10:24:04 (expired)
                'CAPI-ban-range-52.3.230.0/24',
                0
            ],
            [
                'ban',
                5897183044, //  Monday 15 November 2156 10:24:04 (not expired, I guess)
                'CAPI-ban-range-52.3.230.0/24',
                0
            ],

        ];
        $result = PHPUnitUtil::callMethod(
            $this->cacheStorage,
            'cleanCachedValues',
            [$cachedValues]
        );
        $this->assertEquals(
            ['1' => [
                'ban',
                5897183044,
                'CAPI-ban-range-52.3.230.0/24',
                0
            ]],
            $result,
            'Should return correct maximum'
        );
    }


    public function testStoreAndRemoveAndRetrieveDecisionsForIpScope(){

        $this->setCache('PhpFilesAdapter');

        $decision = $this->getMockBuilder('CrowdSec\RemediationEngine\Decision')
            ->disableOriginalConstructor()
            ->onlyMethods(['getValue', 'getType', 'getDuration', 'getScope', 'getIdentifier', 'getPriority'])
            ->getMock();
        $decision->method('getValue')->will(
            $this->returnValue(
                TestConstants::IP_V4
            )
        );
        $decision->method('getType')->will(
            $this->returnValue(
                Constants::REMEDIATION_BAN
            )
        );
        $decision->method('getDuration')->will(
            $this->returnValue(
                "147h"
            )
        );
        $decision->method('getScope')->will(
            $this->returnValue(
                Constants::SCOPE_IP
            )
        );
        $decision->method('getIdentifier')->will(
            $this->returnValue(
                'testip'
            )
        );
        $decision->method('getPriority')->will(
            $this->returnValue(
                0
            )
        );
        // Test 1 : retrieve stored IP
        $this->cacheStorage->storeDecision($decision);

        $result = $this->cacheStorage->retrieveDecisionsForIp(Constants::SCOPE_IP,  TestConstants::IP_V4);
        $this->assertCount(
            1,
            $result[0],
            'Should get stored decisions'
        );
        $this->assertEquals(
            Constants::REMEDIATION_BAN,
            $result[0][0][0],
            'Should get stored decisions'
        );

        // Test 2 : retrieve unstored IP
        $this->cacheStorage->removeDecision($decision);
        $result = $this->cacheStorage->retrieveDecisionsForIp(Constants::SCOPE_IP,  TestConstants::IP_V4);
        $this->assertCount(
            0,
            $result,
            'Should get unstored decisions'
        );
    }

    public function testStoreAndRemoveAndRetrieveDecisionsForRangeScope(){

        $this->setCache('PhpFilesAdapter');

        $decision = $this->getMockBuilder('CrowdSec\RemediationEngine\Decision')
            ->disableOriginalConstructor()
            ->onlyMethods(['getValue', 'getType', 'getDuration', 'getScope', 'getIdentifier', 'getPriority'])
            ->getMock();
        $decision->method('getValue')->will(
            $this->returnValue(
                TestConstants::IP_V4 . '/' . TestConstants::IP_RANGE
            )
        );
        $decision->method('getType')->will(
            $this->returnValue(
                Constants::REMEDIATION_BAN
            )
        );
        $decision->method('getDuration')->will(
            $this->returnValue(
                "147h"
            )
        );
        $decision->method('getScope')->will(
            $this->returnValue(
                Constants::SCOPE_RANGE
            )
        );
        $decision->method('getIdentifier')->will(
            $this->returnValue(
                'testrange'
            )
        );
        $decision->method('getPriority')->will(
            $this->returnValue(
                0
            )
        );
        // Test 1 : retrieve stored Range
        $this->cacheStorage->storeDecision($decision);

        $result = $this->cacheStorage->retrieveDecisionsForIp(Constants::SCOPE_RANGE,  TestConstants::IP_V4);
        $this->assertCount(
            1,
            $result[0],
            'Should get stored decisions'
        );
        $this->assertEquals(
            Constants::REMEDIATION_BAN,
            $result[0][0][0],
            'Should get stored decisions'
        );

        // Test 2 : retrieve unstored Range
        $this->cacheStorage->removeDecision($decision);
        $result = $this->cacheStorage->retrieveDecisionsForIp(Constants::SCOPE_RANGE,  TestConstants::IP_V4);
        $this->assertCount(
            0,
            $result,
            'Should get unstored decisions'
        );
    }

    public function testRetrieveUnknownScope(){

        $this->setCache('PhpFilesAdapter');
        $result = $this->cacheStorage->retrieveDecisionsForIp('UNDEFINED',  TestConstants::IP_V4);
        $this->assertCount(
            0,
            $result,
            'Should return empty array'
        );
        PHPUnitUtil::assertRegExp(
            $this,
            '/.*300.*"type":"CACHE_RETRIEVE_FOR_IP_NON_IMPLEMENTED_SCOPE"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );

    }
}
