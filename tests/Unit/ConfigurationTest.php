<?php /** @noinspection PhpRedundantCatchClauseInspection */

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Tests\Unit;

/**
 * Test for configurations.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */

use CrowdSec\RemediationEngine\CapiRemediation;
use CrowdSec\RemediationEngine\Constants;
use CrowdSec\RemediationEngine\Tests\PHPUnitUtil;
use CrowdSec\RemediationEngine\Configuration\Capi as CapiRemediationConfig;
use CrowdSec\RemediationEngine\Configuration\Cache\Memcached as MemcachedConfig;
use CrowdSec\RemediationEngine\Configuration\Cache\Redis as RedisConfig;
use CrowdSec\RemediationEngine\Configuration\Cache\PhpFiles as PhpFilesConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

final class ConfigurationTest extends TestCase
{

    public function testCapiConfiguration()
    {
        $configuration = new CapiRemediationConfig();
        $processor = new Processor();

        // Test default config
        $configs = [];
        $result = $processor->processConfiguration($configuration, [$configs]);
        $this->assertEquals(
            [
                'fallback_remediation' => 'bypass',
                'ordered_remediations' => CapiRemediation::ORDERED_REMEDIATIONS
            ],
            $result,
            'Should set default config'
        );
        // Test array unique
        $configs = ['ordered_remediations' => ['ban', 'test' => 'ban', 'captcha', 'bypass']];
        $result = $processor->processConfiguration($configuration, [$configs]);
        $this->assertEquals(
            [
                'fallback_remediation' => 'bypass',
                'ordered_remediations' => ['ban', 'captcha', 'bypass']
            ],
            $result,
            'Should normalize config'
        );
        // Test missing bypass
        $error = '';
        $configs = ['ordered_remediations' => ['ban', 'captcha'], 'fallback_remediation' => 'captcha'];
        try {
            $processor->processConfiguration($configuration, [$configs]);
        } catch (InvalidConfigurationException $e) {
            $error = $e->getMessage();
        }

        PHPUnitUtil::assertRegExp(
            $this,
            '/Bypass remediation must belong to ordered remediations./',
            $error,
            'Should throw error if bypass is missing'
        );
        // Test fallback is not in ordered remediations
        $error = '';
        $configs = ['ordered_remediations' => ['ban', 'captcha', 'bypass'], 'fallback_remediation' => 'm2a'];
        try {
            $processor->processConfiguration($configuration, [$configs]);
        } catch (InvalidConfigurationException $e) {
            $error = $e->getMessage();
        }

        PHPUnitUtil::assertRegExp(
            $this,
            '/Fallback remediation must belong to ordered remediations./',
            $error,
            'Should throw error if fallback does not belong to ordered remediations'
        );
    }

    public function testMemcachedConfiguration()
    {
        $configuration = new MemcachedConfig();
        $processor = new Processor();
        // Test default config
        $configs = ['memcached_dsn' => 'memcached_dsn_test'];
        $result = $processor->processConfiguration($configuration, [$configs]);
        $this->assertEquals(
            [
                'stream_mode' => false,
                'clean_ip_cache_duration' => Constants::CACHE_EXPIRATION_FOR_CLEAN_IP,
                'bad_ip_cache_duration' => Constants::CACHE_EXPIRATION_FOR_BAD_IP,
                'memcached_dsn' => 'memcached_dsn_test'
            ],
            $result,
            'Should set default config'
        );

        // Test missing dsn
        $error = '';
        $configs = [];
        try {
            $processor->processConfiguration($configuration, [$configs]);
        } catch (InvalidConfigurationException $e) {
            $error = $e->getMessage();
        }

        PHPUnitUtil::assertRegExp(
            $this,
            '/memcached_dsn.*must be configured/',
            $error,
            'Should throw error if dsn is missing'
        );
        // Test empty dsn
        $error = '';
        $configs = ['memcached_dsn' => ''];
        try {
            $processor->processConfiguration($configuration, [$configs]);
        } catch (InvalidConfigurationException $e) {
            $error = $e->getMessage();
        }

        PHPUnitUtil::assertRegExp(
            $this,
            '/memcached_dsn.*cannot contain an empty value/',
            $error,
            'Should throw error if dsn is empty'
        );
    }

    public function testRedisConfiguration()
    {
        $configuration = new RedisConfig();
        $processor = new Processor();
        // Test default config
        $configs = ['redis_dsn' => 'redis_dsn_test'];
        $result = $processor->processConfiguration($configuration, [$configs]);
        $this->assertEquals(
            [
                'stream_mode' => false,
                'clean_ip_cache_duration' => Constants::CACHE_EXPIRATION_FOR_CLEAN_IP,
                'bad_ip_cache_duration' => Constants::CACHE_EXPIRATION_FOR_BAD_IP,
                'redis_dsn' => 'redis_dsn_test'
            ],
            $result,
            'Should set default config'
        );

        // Test missing dsn
        $error = '';
        $configs = [];
        try {
            $processor->processConfiguration($configuration, [$configs]);
        } catch (InvalidConfigurationException $e) {
            $error = $e->getMessage();
        }

        PHPUnitUtil::assertRegExp(
            $this,
            '/redis_dsn.*must be configured/',
            $error,
            'Should throw error if dsn is missing'
        );
        // Test empty dsn
        $error = '';
        $configs = ['redis_dsn' => ''];
        try {
            $processor->processConfiguration($configuration, [$configs]);
        } catch (InvalidConfigurationException $e) {
            $error = $e->getMessage();
        }

        PHPUnitUtil::assertRegExp(
            $this,
            '/redis_dsn.*cannot contain an empty value/',
            $error,
            'Should throw error if dsn is empty'
        );
    }

    public function testPhpFilesConfiguration()
    {
        $configuration = new PhpFilesConfig();
        $processor = new Processor();
        // Test default config
        $configs = ['fs_cache_path' => 'fs_cache_path_test'];
        $result = $processor->processConfiguration($configuration, [$configs]);
        $this->assertEquals(
            [
                'stream_mode' => false,
                'clean_ip_cache_duration' => Constants::CACHE_EXPIRATION_FOR_CLEAN_IP,
                'bad_ip_cache_duration' => Constants::CACHE_EXPIRATION_FOR_BAD_IP,
                'fs_cache_path' => 'fs_cache_path_test'
            ],
            $result,
            'Should set default config'
        );

        // Test missing path
        $error = '';
        $configs = [];
        try {
            $processor->processConfiguration($configuration, [$configs]);
        } catch (InvalidConfigurationException $e) {
            $error = $e->getMessage();
        }

        PHPUnitUtil::assertRegExp(
            $this,
            '/fs_cache_path.*must be configured/',
            $error,
            'Should throw error if dsn is missing'
        );
        // Test empty path
        $error = '';
        $configs = ['fs_cache_path' => ''];
        try {
            $processor->processConfiguration($configuration, [$configs]);
        } catch (InvalidConfigurationException $e) {
            $error = $e->getMessage();
        }

        PHPUnitUtil::assertRegExp(
            $this,
            '/fs_cache_path.*cannot contain an empty value/',
            $error,
            'Should throw error if dsn is empty'
        );
    }
}
