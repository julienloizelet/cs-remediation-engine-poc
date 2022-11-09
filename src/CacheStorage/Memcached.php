<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\CacheStorage;

use CrowdSec\RemediationEngine\Configuration\Cache\Memcached as MemcachedCacheConfig;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Config\Definition\Processor;

class Memcached extends AbstractCache
{
    /**
     * Using a MemcachedAdapter with a TagAwareAdapter for storing tags is discouraged.
     *
     * @see \Symfony\Component\Cache\Adapter\MemcachedAdapter::__construct comment
     *
     * @throws CacheException
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct(array $configs, LoggerInterface $logger = null)
    {
        $this->configure($configs);
        $this->setCustomErrorHandler();
        try {
            $adapter = new MemcachedAdapter(MemcachedAdapter::createConnection($this->configs['memcached_dsn']));
        } catch (\Exception $e) {
            throw new CacheException('Error when creating Memcached cache adapter:' . $e->getMessage());
        } finally {
            $this->unsetCustomErrorHandler();
        }
        parent::__construct($this->configs, $adapter, $logger);
    }

    /**
     * When Memcached connection fail, it throws an unhandled warning.
     * To catch this warning as a clean exception we have to temporarily change the error handler.
     *
     * @throws CacheException
     */
    protected function setCustomErrorHandler(): void
    {
        set_error_handler(function ($errno, $errstr) {
            $message = "Memcached error. (Error level: $errno) " .
                       "Original error was: $errstr";
            throw new CacheException($message);
        });
    }

    /**
     * When the selected cache adapter is MemcachedAdapter, revert to the previous error handler.
     * */
    protected function unsetCustomErrorHandler(): void
    {
        restore_error_handler();
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
