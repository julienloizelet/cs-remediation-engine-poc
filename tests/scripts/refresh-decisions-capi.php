<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use CrowdSec\CapiClient\Watcher;
use CrowdSec\CapiClient\Storage\FileStorage;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\CapiRemediation;

// Init Client
$clientConfigs = ['scenarios' => ['crowdsecurity/http-sensitive-files']];
$capiClient = new Watcher($clientConfigs, new FileStorage());

// Init Cache storage
$cacheConfigs = [
    'fs_cache_path' => __DIR__ . '/.cache',
];
$phpFileCache = new PhpFiles($cacheConfigs);


$remediationEngine = new CapiRemediation([], $capiClient, $phpFileCache);

echo json_encode($remediationEngine->refreshDecisions()) . PHP_EOL;