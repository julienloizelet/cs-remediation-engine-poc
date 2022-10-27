<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine;

use CrowdSec\RemediationEngine\CacheStorage\CacheStorageInterface;

class Geolocation
{

    protected $configs;

    protected $cacheStorage;


    public function __construct (array $configs, CacheStorageInterface $cacheStorage){

        $this->configs = $configs;
        $this->cacheStorage = $cacheStorage;

    }

    public function getCountryResult(string $ip): array
    {
        return ['country' => 'FR'];
    }


}