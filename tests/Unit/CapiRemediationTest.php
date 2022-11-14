<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Tests\Unit;

/**
 * Test for capi remediation.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */

use CrowdSec\CapiClient\Watcher;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\CacheStorage\Memcached;
use CrowdSec\RemediationEngine\CacheStorage\Redis;
use org\bovigo\vfs\vfsStream;
use CrowdSec\RemediationEngine\CapiRemediation;
use CrowdSec\RemediationEngine\CacheStorage\AbstractCache;
use CrowdSec\RemediationEngine\Constants;
use CrowdSec\RemediationEngine\Tests\Constants as TestConstants;
use CrowdSec\RemediationEngine\Tests\MockedData;
use CrowdSec\RemediationEngine\Logger\FileLog;
use org\bovigo\vfs\vfsStreamDirectory;

/**
 * @covers \CrowdSec\RemediationEngine\CapiRemediation::__construct
 * @covers \CrowdSec\RemediationEngine\CapiRemediation::getIpRemediation
 * @covers \CrowdSec\RemediationEngine\CapiRemediation::createInternalDecision
 * @covers \CrowdSec\RemediationEngine\CapiRemediation::storeDecisions
 * @covers \CrowdSec\RemediationEngine\CapiRemediation::sortDecisionsByRemediationPriority
 * @covers \CrowdSec\RemediationEngine\CapiRemediation::refreshDecisions
 * @covers \CrowdSec\RemediationEngine\Configuration\Capi::getConfigTreeBuilder
 */
final class CapiRemediationTest extends AbstractRemediation
{
    /**
     * @var vfsStreamDirectory
     */
    private $root;

    /**
     * @var string
     */
    private $debugFile;
    /**
     * @var string
     */
    private $prodFile;

    /**
     * @var FileLog
     */
    private $logger;
    /**
     * @var Watcher
     */
    private $watcher;

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

    /**
     * set up test environment.
     */
    public function setUp(): void
    {
        $this->root = vfsStream::setup(TestConstants::TMP_DIR);
        $currentDate = date('Y-m-d');
        $this->debugFile = 'debug-' . $currentDate . '.log';
        $this->prodFile = 'prod-' . $currentDate . '.log';
        $this->logger = new FileLog(['log_directory_path' => $this->root->url(), 'debug_mode' => true]);
        $this->watcher = $this->getWatcherMock();

        $cachePhpfilesConfigs = ['fs_cache_path' => $this->root->url()];
        $this->phpFileStorage = $this->getCacheMock('PhpFilesAdapter', $cachePhpfilesConfigs);
        $cacheMemcachedConfigs = [
            'memcached_dsn' => getenv('memcached_dsn') ?: 'memcached://memcached:11211',
        ];
        $this->memcachedStorage = $this->getCacheMock('MemcachedAdapter', $cacheMemcachedConfigs);
        $cacheRedisConfigs = [
            'redis_dsn' => getenv('redis_dsn') ?: 'redis://redis:6379',
        ];
        $this->redisStorage = $this->getCacheMock('RedisAdapter', $cacheRedisConfigs);
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
    public function testGetIpRemediation($cacheType)
    {
        $this->setCache($cacheType);

        $remediationConfigs = [];
        // Test that cache is forced to stream mode
        $this->cacheStorage->expects($this->exactly(1))
            ->method('setStreamMode')
            ->with(true);

        $remediation = new CapiRemediation($remediationConfigs, $this->watcher, $this->cacheStorage, $this->logger);
        // Test default configs
        $this->assertEquals(
            Constants::REMEDIATION_BYPASS,
            $remediation->getConfig('fallback_remediation'),
            'Default fallback should be bypass'
        );
        $this->assertEquals(
            [Constants::REMEDIATION_BAN, Constants::REMEDIATION_BYPASS],
            $remediation->getConfig('ordered_remediations'),
            'Default ordered remediation should be as expected'
        );
        // Prepare next tests
        $this->cacheStorage->method('retrieveDecisionsForIp')->will(
            $this->onConsecutiveCalls(
                [],  // Test 1 : retrieve IP
                [],  // Test 1 : retrieve Range
                [[[
                    'bypass',
                    999999999999,
                    'remediation-engine-bypass-ip-1.2.3.4',
                    0
                ]]], // Test 2 : retrieve cached first bypass
                [],  // Test 2 : retrieve Range
                [[[
                    'bypass',
                    999999999999,
                    'remediation-engine-bypass-ip-1.2.3.4',
                    1
                ]]], // Test 3 : retrieve bypass
                [[[
                    'ban',
                    999999999999,
                    'remediation-engine-ban-ip-1.2.3.4',
                    0
                ]]]  // Test 3 : retrieve ban
            )
        );
        // Test 1
        $result = $remediation->getIpRemediation(TestConstants::IP_V4);
        $this->assertEquals(
            Constants::REMEDIATION_BYPASS,
            $result,
            'Uncached (clean) IP should return a bypass remediation'
        );

        $adapter = $this->cacheStorage->getAdapter();
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_CACHE_KEY));
        $this->assertEquals(
            true,
            $item->isHit(),
            'Remediation should have been cached'
        );

        $cachedValue = $item->get();
        $this->assertEquals(
            Constants::REMEDIATION_BYPASS,
            $cachedValue[0][AbstractCache::INDEX_VALUE],
            'Remediation should have been cached with correct value'
        );
        $this->assertEquals(
            1,
            $cachedValue[0][AbstractCache::INDEX_PRIO],
            'Remediation should have been cached with correct priority'
        );
        // Test 2
        $result = $remediation->getIpRemediation(TestConstants::IP_V4);
        $this->assertEquals(
            Constants::REMEDIATION_BYPASS,
            $result,
            'Cached clean IP should return a bypass remediation'
        );
        // Test 3
        $result = $remediation->getIpRemediation(TestConstants::IP_V4);
        $this->assertEquals(
            Constants::REMEDIATION_BAN,
            $result,
            'Remediations should be ordered by priority'
        );
    }

    /**
     * @dataProvider cacheTypeProvider
     */
    public function testRefreshDecisions($cacheType)
    {
        $this->setCache($cacheType);

        $remediationConfigs = [];

        $remediation = new CapiRemediation($remediationConfigs, $this->watcher, $this->cacheStorage, $this->logger);

        // Prepare next tests
        $this->watcher->method('getStreamDecisions')->will(
            $this->onConsecutiveCalls(
                MockedData::CAPI_DECISIONS['new_ip_v4'],          // Test 1 : retrieve a new IP decision (ban)
                MockedData::CAPI_DECISIONS['new_ip_v4'],          // Test 2 : retrieve same IP decision (ban)
                MockedData::CAPI_DECISIONS['deleted_ip_v4'],      // Test 3 : retrieve a deleted IP decision
                MockedData::CAPI_DECISIONS['new_ip_v4_range'],    // Test 4 : retrieve a new RANGE decision (ban)
                MockedData::CAPI_DECISIONS['delete_ip_v4_range'], // Test 5 : retrieve a deleted RANGE decision
                MockedData::CAPI_DECISIONS['ip_v4_multiple'],     // Test 6 : retrieve multiple RANGE and IP decision
                MockedData::CAPI_DECISIONS['ip_v4_multiple_bis']  // Test 7 : retrieve multiple new and delete
            )
        );
        // Test 1
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 1, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );

        $adapter = $this->cacheStorage->getAdapter();
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_2_CACHE_KEY));
        $this->assertEquals(
            true,
            $item->isHit(),
            'Remediation should have been cached'
        );
        $cachedValue = $item->get();
        $this->assertEquals(
            Constants::REMEDIATION_BAN,
            $cachedValue[0][AbstractCache::INDEX_VALUE],
            'Remediation should have been cached with correct value'
        );
        // Test 2
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_2_CACHE_KEY));
        $this->assertEquals(
            true,
            $item->isHit(),
            'Remediation should still be cached'
        );
        // Test 3
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 1],
            $result,
            'Refresh count should be correct'
        );
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_2_CACHE_KEY));
        $this->assertEquals(
            false,
            $item->isHit(),
            'Remediation should have been deleted'
        );
        // Test 4
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 1, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );
        $item = $adapter->getItem(
            base64_encode(TestConstants::IP_V4_RANGE_CACHE_KEY)
        );
        $this->assertEquals(
            true,
            $item->isHit(),
            'Remediation should have been cached'
        );
        $item = $adapter->getItem(
            base64_encode(
                TestConstants::IP_V4_BUCKET_CACHE_KEY)
        );
        $this->assertEquals(
            true,
            $item->isHit(),
            'Range bucket should have been cached'
        );
        // Test 5
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 1],
            $result,
            'Refresh count should be correct'
        );
        $item = $adapter->getItem(
            base64_encode(TestConstants::IP_V4_RANGE_CACHE_KEY)
        );
        $this->assertEquals(
            false,
            $item->isHit(),
            'Remediation should have been deleted'
        );
        $item = $adapter->getItem(
            base64_encode(
                TestConstants::IP_V4_BUCKET_CACHE_KEY)
        );
        $this->assertEquals(
            false,
            $item->isHit(),
            'Range bucket should have been deleted'
        );
        // Test 6
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 5, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_CACHE_KEY));
        $cachedValue = $item->get();
        $this->assertEquals(
            2,
            count($cachedValue),
            'Should have cached 2 remediations'
        );
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_2_CACHE_KEY));
        $cachedValue = $item->get();
        $this->assertEquals(
            1,
            count($cachedValue),
            'Should have cached 1 remediation'
        );
        // Test 7
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 1, 'deleted' => 1],
            $result,
            'Refresh count should be correct'
        );
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_CACHE_KEY));
        $cachedValue = $item->get();
        $this->assertEquals(
            1,
            count($cachedValue),
            'Should stay 1 cached remediation'
        );
        $item = $adapter->getItem(base64_encode(TestConstants::IP_V4_2_CACHE_KEY));
        $cachedValue = $item->get();
        $this->assertEquals(
            2,
            count($cachedValue),
            'Should now have 2 cached remediation'
        );
    }
}
