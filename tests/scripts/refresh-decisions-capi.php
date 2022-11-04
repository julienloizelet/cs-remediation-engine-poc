<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use CrowdSec\CapiClient\Watcher;
use CrowdSec\CapiClient\Storage\FileStorage;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\CapiRemediation;
use CrowdSec\RemediationEngine\Logger\FileLog;

// Init a logger
$logger = new FileLog(['debug_mode' => true]);

// Init Client
$clientConfigs = [
    'machine_id_prefix' => 'remediationtest',
    'scenarios' => ['crowdsecurity/http-sensitive-files']];
$capiClient = new Watcher($clientConfigs, new FileStorage(), null, $logger);

// Init Cache storage
$cacheConfigs = [
    'fs_cache_path' => __DIR__ . '/.cache',
    'clean_ip_cache_duration' => 120,
];
$phpFileCache = new PhpFiles($cacheConfigs, $logger);

$remediationConfigs = [];
$remediationEngine = new CapiRemediation($remediationConfigs, $capiClient, $phpFileCache, $logger);

echo json_encode($remediationEngine->refreshDecisions()) . PHP_EOL;