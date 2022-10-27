<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\CacheStorage;

use  CrowdSec\RemediationEngine\CacheStorage\Memcached\TagAwareAdapter as MemcachedTagAwareAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;

class Memcached extends AbstractCache {

        public function __construct(array $configs)
        {
            parent::__construct($configs);
            $memcachedDsn = $this->configs['memcached_dsn'];
            if (empty($memcachedDsn)) {
                throw new CacheException('Please set a Memcached DSN.');
            }

            $this->adapter = new MemcachedTagAwareAdapter(
                new MemcachedAdapter(MemcachedAdapter::createConnection($memcachedDsn))
            );

        }
}