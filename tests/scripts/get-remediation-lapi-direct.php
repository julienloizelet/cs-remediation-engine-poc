<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use CrowdSec\RemediationEngine\Client\RequestHandler\Curl;
use CrowdSec\RemediationEngine\Client\LapiClient;
use CrowdSec\RemediationEngine\CacheStorage\PhpFiles;
use CrowdSec\RemediationEngine\LapiRemediation;

$ip = $argv[1] ?? null;

if (!$ip) {
    exit(
        'Usage: php get-remediation-lapi-direct.php <IP>' . \PHP_EOL .
        'Example: php get-remediation-lapi-direct.php 172.0.0.24' .
        \PHP_EOL
    );
}

// Init Client
$clientConfigs = [
    'api_url' => 'https://crowdsec:8080/',
    'api_key' => '28093fad6922290237788aeec8927034',
    'stream_mode' => false,
];
$requestHandler = new Curl();
$lapiClient = new LapiClient($clientConfigs, $requestHandler);

// Init Cache storage
$cacheConfigs = [
    'fs_cache_path' => __DIR__ . '/.cache',
    'cache_expiration_for_clean_ip' => 10,
    'cache_expiration_for_bad_ip' => 20
];
$phpFileCache = new PhpFiles($cacheConfigs);


// Init Remediation Engine
$remediationConfigs = [
    'geolocation_enabled' => false
];


$remediationEngine = new LapiRemediation($remediationConfigs, $lapiClient, $phpFileCache);

echo $remediationEngine->getIpRemediation($ip);