<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\CacheStorage;

use CrowdSec\RemediationEngine\Configuration\Cache\Redis as RedisCacheConfig;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Config\Definition\Processor;

class Redis extends AbstractCache
{

    /**
     * @param array $configs
     */
    public function __construct(array $configs)
    {
        $this->configure($configs);

        try {
            $this->adapter = new RedisTagAwareAdapter((RedisAdapter::createConnection($this->configs['redis_dsn'])));
        } catch (Exception $e) {
            throw new CacheException('Error when creating Redis cache adapter:' . $e->getMessage());
        }
    }

    /**
     * Process and validate input configurations.
     */
    private function configure(array $configs): void
    {
        $configuration = new RedisCacheConfig();
        $processor = new Processor();
        $this->configs = $processor->processConfiguration($configuration, [$configs]);
    }
}