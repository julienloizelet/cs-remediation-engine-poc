<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\CacheStorage;

use CrowdSec\RemediationEngine\CacheStorage\Memcached\TagAwareAdapter as MemcachedTagAwareAdapter;
use CrowdSec\RemediationEngine\Configuration\Cache\Memcached as MemcachedCacheConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Config\Definition\Processor;

class Memcached extends AbstractCache
{

    /**
     * @param array $configs
     * @param LoggerInterface|null $logger
     * @throws CacheException
     */
    public function __construct(array $configs, LoggerInterface $logger = null)
    {
        $this->configure($configs);
        try {
            $adapter = new MemcachedTagAwareAdapter(
                new MemcachedAdapter(MemcachedAdapter::createConnection($this->configs['memcached_dsn']))
            );
        } catch (\Exception $e) {
            throw new CacheException('Error when creating Memcached cache adapter:' . $e->getMessage());
        }
        parent::__construct($this->configs, $adapter, $logger);
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