<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use CrowdSec\CapiClient\Storage\FileStorage;
use CrowdSec\CapiClient\Watcher;
use CrowdSec\RemediationEngine\CacheStorage\Memcached;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\CacheStorage\Redis;
use CrowdSec\RemediationEngine\CapiRemediation;
use CrowdSec\RemediationEngine\Logger\FileLog;

$ip = $argv[1] ?? null;

if (!$ip) {
    exit(
        'Usage: php get-remediation-capi.php <IP>' . \PHP_EOL .
        'Example: php get-remediation-capi.php 172.0.0.24' .
        \PHP_EOL
    );
}

// Init a logger
$logger = new FileLog(['debug_mode' => true]);

// Init Client
$clientConfigs = [
    'machine_id_prefix' => 'remediationtest',
    'scenarios' => ['crowdsecurity/http-sensitive-files'], ];
$capiClient = new Watcher($clientConfigs, new FileStorage(), null, $logger);

// Init PhpFile Cache storage
$cacheFileConfigs = [
    'fs_cache_path' => __DIR__ . '/.cache',
];
$phpFileCache = new PhpFiles($cacheFileConfigs, $logger);
// Init Memcached Cache storage
$cacheMemcachedConfigs = [
    'memcached_dsn' => 'memcached://memcached:11211',
];
$memcachedCache = new Memcached($cacheMemcachedConfigs, $logger);
// Init Redis Cache storage
$cacheRedisConfigs = [
    'redis_dsn' => 'redis://redis:6379',
];
$redisCache = new Redis($cacheRedisConfigs, $logger);

$remediationConfigs = [];

$remediationEngine = new CapiRemediation($remediationConfigs, $capiClient, $phpFileCache, $logger);

echo $remediationEngine->getIpRemediation($ip) . \PHP_EOL;
