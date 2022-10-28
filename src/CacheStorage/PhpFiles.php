<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\CacheStorage;

use CrowdSec\RemediationEngine\Configuration\Cache\PhpFiles as PhpFilesCacheConfig;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Config\Definition\Processor;

class PhpFiles extends AbstractCache
{

    /**
     * @param array $configs
     * @throws \Symfony\Component\Cache\Exception\CacheException
     */
    public function __construct(array $configs)
    {
        $this->configure($configs);
        try {
            $this->adapter = new TagAwareAdapter(
                new PhpFilesAdapter('', 0, $this->configs['fs_cache_path'])
            );
        } catch (Exception $e) {
            throw new CacheException('Error when creating to PhpFiles cache adapter:' . $e->getMessage());
        }
    }

    /**
     * Process and validate input configurations.
     */
    private function configure(array $configs): void
    {
        $configuration = new PhpFilesCacheConfig();
        $processor = new Processor();
        $this->configs = $processor->processConfiguration($configuration, [$configs]);
    }
}