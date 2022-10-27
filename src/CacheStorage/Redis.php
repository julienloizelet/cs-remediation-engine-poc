<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\CacheStorage;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;

class Redis extends AbstractCache {

        public function __construct(array $configs)
        {
            parent::__construct($configs);
            $redisDsn = $this->configs['redis_dsn'];
            if (empty($redisDsn)) {
                throw new CacheException('Please set a Redis DSN.');
            }

            try {
                $this->adapter = new RedisTagAwareAdapter((RedisAdapter::createConnection($redisDsn)));
            } catch (Exception $e) {
                throw new CacheException('Error when connecting to Redis:'. $e->getMessage());
            }
        }
}