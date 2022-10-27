<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\CacheStorage;

use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

class PhpFiles extends AbstractCache {

        public function __construct(array $configs)
        {
            parent::__construct($configs);
            $this->adapter = new TagAwareAdapter(
                new PhpFilesAdapter('', 0, $this->configs['fs_cache_path'])
            );
        }
}