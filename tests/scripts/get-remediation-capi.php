<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use CrowdSec\CapiClient\Watcher;
use CrowdSec\CapiClient\Storage\FileStorage;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\CapiRemediation;

$ip = $argv[1] ?? null;

if (!$ip) {
    exit(
        'Usage: php get-remediation-capi.php <IP>' . \PHP_EOL .
        'Example: php get-remediation-capi.php 172.0.0.24' .
        \PHP_EOL
    );
}

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

echo $remediationEngine->getIpRemediation($ip) . PHP_EOL;