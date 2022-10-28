<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\CacheStorage;

use CrowdSec\RemediationEngine\CacheStorage\Memcached\TagAwareAdapter as MemcachedTagAwareAdapter;
use CrowdSec\RemediationEngine\Configuration\Cache\Memcached as MemcachedCacheConfig;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Config\Definition\Processor;

class Memcached extends AbstractCache
{

    /**
     * @param array $configs
     * @throws \ErrorException
     * @throws \Symfony\Component\Cache\Exception\CacheException
     */
    public function __construct(array $configs)
    {
        $this->configure($configs);
        try {
            $this->adapter = new MemcachedTagAwareAdapter(
                new MemcachedAdapter(MemcachedAdapter::createConnection($this->configs['memcached_dsn']))
            );
        } catch (Exception $e) {
            throw new CacheException('Error when creating Memcached cache adapter:' . $e->getMessage());
        }
    }

    /**
     * Process and validate input configurations.
     */
    private function configure(array $configs): void
    {
        $configuration = new MemcachedCacheConfig();
        $processor = new Processor();
        $this->configs = $processor->processConfiguration($configuration, [$configs]);
    }
}