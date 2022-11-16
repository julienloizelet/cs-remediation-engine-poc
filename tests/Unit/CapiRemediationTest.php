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
use CrowdSec\RemediationEngine\Tests\PHPUnitUtil;
use CrowdSec\RemediationEngine\Logger\FileLog;
use org\bovigo\vfs\vfsStreamDirectory;

/**
 * @uses \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::__construct
 * @uses \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::cleanCachedValues
 * @uses \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getAdapter
 * @uses \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getConfig
 * @uses \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getMaxExpiration
 * @uses \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::parseDurationToSeconds
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Memcached::__construct
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Memcached::configure
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Memcached::setCustomErrorHandler
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Memcached::unsetCustomErrorHandler
 * @uses \CrowdSec\RemediationEngine\CacheStorage\PhpFiles::__construct
 * @uses \CrowdSec\RemediationEngine\CacheStorage\PhpFiles::configure
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Redis::__construct
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Redis::configure
 * @uses \CrowdSec\RemediationEngine\Configuration\AbstractCache::addCommonNodes
 * @uses \CrowdSec\RemediationEngine\Configuration\AbstractCache::addCommonNodes
 * @uses \CrowdSec\RemediationEngine\Configuration\Cache\Memcached::getConfigTreeBuilder
 * @uses \CrowdSec\RemediationEngine\Configuration\Cache\PhpFiles::getConfigTreeBuilder
 * @uses \CrowdSec\RemediationEngine\Configuration\Cache\Redis::getConfigTreeBuilder
 * @uses \CrowdSec\RemediationEngine\Configuration\Capi::validate
 * @uses \CrowdSec\RemediationEngine\Logger\FileLog::__construct
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Memcached::clear
 * @uses \CrowdSec\RemediationEngine\CacheStorage\Memcached::commit
 * @uses \CrowdSec\RemediationEngine\Decision::getOrigin
 * @uses \CrowdSec\RemediationEngine\Decision::toArray
 *
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::__construct
 * @covers \CrowdSec\RemediationEngine\CapiRemediation::__construct
 * @covers \CrowdSec\RemediationEngine\CapiRemediation::configure
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::getConfig
 * @covers \CrowdSec\RemediationEngine\CapiRemediation::getIpRemediation
 * @covers \CrowdSec\RemediationEngine\CapiRemediation::createInternalDecision
 * @covers \CrowdSec\RemediationEngine\CapiRemediation::storeDecisions
 * @covers \CrowdSec\RemediationEngine\CapiRemediation::sortDecisionsByRemediationPriority
 * @covers \CrowdSec\RemediationEngine\CapiRemediation::refreshDecisions
 * @covers \CrowdSec\RemediationEngine\Configuration\Capi::getConfigTreeBuilder
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::removeDecisions
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::clear
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::commit
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::format
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::formatIpV4Range
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getCacheKey
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getCachedIndex
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getRangeIntForIp
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::handleRangeScoped
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::remove
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::removeDecision
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::store
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::storeDecision
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::updateCacheItem
 * @covers \CrowdSec\RemediationEngine\Decision::__construct
 * @covers \CrowdSec\RemediationEngine\Decision::getDuration
 * @covers \CrowdSec\RemediationEngine\Decision::getIdentifier
 * @covers \CrowdSec\RemediationEngine\Decision::getPriority
 * @covers \CrowdSec\RemediationEngine\Decision::getScope
 * @covers \CrowdSec\RemediationEngine\Decision::getType
 * @covers \CrowdSec\RemediationEngine\Decision::getValue
 * @covers \CrowdSec\RemediationEngine\Decision::handleIdentifier
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::comparePriorities
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::manageRange
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::saveDeferred
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::handleBadIpDuration
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getTags
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::getItem
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::retrieveDecisionsForIp
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::convertRawDecision
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::convertRawDecisionsToDecisions
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::validateRawDecision
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::clearCache
 * @covers \CrowdSec\RemediationEngine\AbstractRemediation::pruneCache
 * @covers \CrowdSec\RemediationEngine\CacheStorage\AbstractCache::prune
 *
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
        $mockedMethods = ['retrieveDecisionsForIp', 'setStreamMode'];
        $this->phpFileStorage = $this->getCacheMock('PhpFilesAdapter', $cachePhpfilesConfigs, $this->logger, $mockedMethods);
        $cacheMemcachedConfigs = [
            'memcached_dsn' => getenv('memcached_dsn') ?: 'memcached://memcached:11211',
        ];
        $this->memcachedStorage = $this->getCacheMock('MemcachedAdapter', $cacheMemcachedConfigs, $this->logger, $mockedMethods);
        $cacheRedisConfigs = [
            'redis_dsn' => getenv('redis_dsn') ?: 'redis://redis:6379',
        ];
        $this->redisStorage = $this->getCacheMock('RedisAdapter', $cacheRedisConfigs, $this->logger, $mockedMethods);
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
    public function testCacheActions($cacheType){
        $this->setCache($cacheType);
        $remediationConfigs = [];
        $remediation = new CapiRemediation($remediationConfigs, $this->watcher, $this->cacheStorage, null);
        $result = $remediation->clearCache();
        $this->assertEquals(
            true,
            $result,
            'Should clear cache'
        );

        if ($cacheType === 'PhpFilesAdapter') {
            $result = $remediation->pruneCache();
            $this->assertEquals(
                true,
                $result,
                'Should prune cache'
            );
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

        // Test with null logger
        $remediation = new CapiRemediation($remediationConfigs, $this->watcher, $this->cacheStorage, null);
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
                MockedData::CAPI_DECISIONS['new_ip_v4'],          // Test 1 : new IP decision (ban)
                MockedData::CAPI_DECISIONS['new_ip_v4'],          // Test 2 : same IP decision (ban)
                MockedData::CAPI_DECISIONS['deleted_ip_v4'],      // Test 3 : deleted IP decision (existing one and not)
                MockedData::CAPI_DECISIONS['new_ip_v4_range'],    // Test 4 : new RANGE decision (ban)
                MockedData::CAPI_DECISIONS['delete_ip_v4_range'], // Test 5 : deleted RANGE decision
                MockedData::CAPI_DECISIONS['ip_v4_multiple'],     // Test 6 : retrieve multiple RANGE and IP decision
                MockedData::CAPI_DECISIONS['ip_v4_multiple_bis'],  // Test 7 : retrieve multiple new and delete
                MockedData::CAPI_DECISIONS['ip_v4_remove_unknown'], // Test 8 : delete unknown scope
                MockedData::CAPI_DECISIONS['ip_v4_store_unknown'], // Test 9 : store unknown scope
                MockedData::CAPI_DECISIONS['new_ip_v6_range'] // Test 10 : store IP V6 range
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

        // Test 8
        $this->assertEquals(
            false,
            file_exists($this->root->url() . '/' . $this->prodFile),
            'Prod File should not exist'
        );
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );
        $this->assertEquals(
            true,
            file_exists($this->root->url() . '/' . $this->prodFile),
            'Prod File should  exist'
        );

        PHPUnitUtil::assertRegExp(
            $this,
            '/.*300.*"type":"CACHE_REMOVE_NON_IMPLEMENTED_SCOPE.*CAPI-ban-do-not-know-delete-1.2.3.4"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // Test 9
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );

        PHPUnitUtil::assertRegExp(
            $this,
            '/.*300.*"type":"CACHE_STORE_NON_IMPLEMENTED_SCOPE.*CAPI-ban-do-not-know-store-1.2.3.4"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );
        // Test 10
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 0],
            $result,
            'Refresh count should be correct'
        );

        PHPUnitUtil::assertRegExp(
            $this,
            '/.*300.*"type":"IPV6_RANGE_NOT_IMPLEMENTED"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );

    }

    public function testFailedDeferred()
    {
        // Test failed deferred
        $this->watcher->method('getStreamDecisions')->will(
            $this->onConsecutiveCalls(
                MockedData::CAPI_DECISIONS['new_ip_v4_double'], // Test 1 : new IP decision (ban) (save ok)
                MockedData::CAPI_DECISIONS['new_ip_v4_other'],  // Test 2 : new IP decision (ban) (failed deferred)
                MockedData::CAPI_DECISIONS['deleted_ip_v4'] // Test 3 : deleted IP decision (failed deferred)
            )
        );
        $cachePhpfilesConfigs = ['fs_cache_path' => $this->root->url()];
        $mockedMethods = [];
        $this->cacheStorage = $this->getCacheMock('PhpFilesAdapter', $cachePhpfilesConfigs, $this->logger, $mockedMethods);
        $remediationConfigs = [];
        $remediation = new CapiRemediation($remediationConfigs, $this->watcher, $this->cacheStorage, $this->logger);

        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 2, 'deleted' => 0],
            $result,
            'Refresh count should be correct for 2 news'
        );

        // Test 2
        $mockedMethods = ['saveDeferred'];
        $this->cacheStorage = $this->getCacheMock('PhpFilesAdapter', $cachePhpfilesConfigs, $this->logger, $mockedMethods);
        $remediationConfigs = [];
        $remediation = new CapiRemediation($remediationConfigs, $this->watcher, $this->cacheStorage, $this->logger);

        $this->cacheStorage->method('saveDeferred')->will(
            $this->onConsecutiveCalls(
                false
            )
        );
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 0],
            $result,
            'Refresh count should be correct for failed deferred store'
        );
        // Test 3
        $mockedMethods = ['saveDeferred'];
        $this->cacheStorage = $this->getCacheMock('PhpFilesAdapter', $cachePhpfilesConfigs, $this->logger, $mockedMethods);
        $remediationConfigs = [];
        $remediation = new CapiRemediation($remediationConfigs, $this->watcher, $this->cacheStorage, $this->logger);
        $this->cacheStorage->method('saveDeferred')->will(
            $this->onConsecutiveCalls(
                false
            )
        );
        $result = $remediation->refreshDecisions();
        $this->assertEquals(
            ['new' => 0, 'deleted' => 0],
            $result,
            'Refresh count should be correct for failed deferred remove'
        );
    }

    public function testPrivateOrProtectedMethods()
    {

        $cachePhpfilesConfigs = ['fs_cache_path' => $this->root->url()];
        $mockedMethods = [];
        $this->cacheStorage = $this->getCacheMock('PhpFilesAdapter', $cachePhpfilesConfigs, $this->logger, $mockedMethods);
        $remediationConfigs = [];
        $remediation = new CapiRemediation($remediationConfigs, $this->watcher, $this->cacheStorage, $this->logger);
        // convertRawDecisionsToDecisions
        // Test 1 : ok
        $rawDecisions = [
            [
                'scope' => 'IP',
                'value' => '1.2.3.4',
                'type' => 'ban',
                'origin' => 'unit',
                'duration' => '147h',
                'scenario' => ''
            ]
        ];
        $result = PHPUnitUtil::callMethod(
            $remediation,
            'convertRawDecisionsToDecisions',
            [$rawDecisions]
        );

        $this->assertCount(
            1,
            $result,
            'Should return array'
        );

        $decision = $result[0];
        $this->assertEquals(
            'ban',
            $decision->getType(),
            'Should have created a correct decision'
        );
        $this->assertEquals(
            'ip',
            $decision->getScope(),
            'Should have created a correct decision'
        );
        // Test 2: bad raw decision
        $rawDecisions = [
            [
                'value' => '1.2.3.4',
                'origin' => 'unit',
                'duration' => '147h',
            ]
        ];
        $result = PHPUnitUtil::callMethod(
            $remediation,
            'convertRawDecisionsToDecisions',
            [$rawDecisions]
        );
        $this->assertCount(
            0,
            $result,
            'Should return empty array'
        );

        PHPUnitUtil::assertRegExp(
            $this,
            '/.*300.*"type":"RAW_DECISION_NOT_AS_EXPECTED"/',
            file_get_contents($this->root->url() . '/' . $this->prodFile),
            'Prod log content should be correct'
        );

        // comparePriorities
        $a = [
            'ban',
            1668577960,
            'CAPI-ban-range-52.3.230.0/24',
            0
        ];

        $b = [
            'ban',
            1668577960,
            'CAPI-ban-range-52.3.230.0/24',
            0
        ];
        $result = PHPUnitUtil::callMethod(
            $remediation,
            'comparePriorities',
            [$a, $b]
        );

        $this->assertEquals(
            0,
            $result,
            'Should return 0 if same priority'
        );

        $a = [
            'ban',
            1668577960,
            'CAPI-ban-range-52.3.230.0/24',
            0
        ];

        $b = [
            'bypass',
            1668577960,
            'CAPI-ban-range-52.3.230.0/24',
            1
        ];
        $result = PHPUnitUtil::callMethod(
            $remediation,
            'comparePriorities',
            [$a, $b]
        );

        $this->assertEquals(
            -1,
            $result,
            'Should return -1'
        );

        $result = PHPUnitUtil::callMethod(
            $remediation,
            'comparePriorities',
            [$b, $a]
        );

        $this->assertEquals(
            1,
            $result,
            'Should return 1'
        );


    }
}
