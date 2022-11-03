<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use CrowdSec\CapiClient\Watcher;
use CrowdSec\CapiClient\Storage\FileStorage;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\CapiRemediation;

// Init Client
$clientConfigs = [
    'machine_id_prefix' => 'remediationtest',
    'scenarios' => ['crowdsecurity/http-sensitive-files']];
$capiClient = new Watcher($clientConfigs, new FileStorage());

// Init Cache storage
$cacheConfigs = [
    'fs_cache_path' => __DIR__ . '/.cache',
    'clean_ip_cache_duration' => 120,
];
$phpFileCache = new PhpFiles($cacheConfigs);

$remediationConfigs = [];
$remediationEngine = new CapiRemediation($remediationConfigs, $capiClient, $phpFileCache);

echo json_encode($remediationEngine->refreshDecisions()) . PHP_EOL;