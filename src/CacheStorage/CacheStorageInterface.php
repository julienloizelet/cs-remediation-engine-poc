<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\CacheStorage;

use CrowdSec\RemediationEngine\Decision;
use Psr\Cache\CacheItemInterface;

interface CacheStorageInterface
{

    /**
     * @param string $scope
     * @param string $value
     * @return array
     */
    public function retrieveDecisions(string $scope, string $value): array;


    /**
     * @param Decision $decision
     * @return bool // true on success, false otherwise
     */
    public function removeDecision(Decision $decision): bool;

    /**
     * @param string $scope
     * @param string $value
     * @param Decision|null $decision
     * @return CacheItemInterface
     */
    public function storeDecision(Decision $decision): CacheItemInterface;

}