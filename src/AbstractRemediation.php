<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine;

use CrowdSec\RemediationEngine\Client\ClientInterface;
use CrowdSec\RemediationEngine\CacheStorage\AbstractCache;

abstract class AbstractRemediation
{

    /**
     * @var AbstractCache
     */
    protected $cacheStorage;
    /**
     * @var ClientInterface
     */
    protected $client;
    protected $configs;

    /**
     * @param string $scope
     * @param string $value
     * @param array $decisions
     * @return array // array of cache item value
     */
    public function storeDecisions(array $decisions): array
    {
        $storedDecisions = [];
        foreach ($decisions as $decision) {
            $cacheKey = $this->cacheStorage->getCacheKey($decision->getScope(), $decision->getValue());
            $cacheItem = $this->cacheStorage->storeDecision($decision);
            $storedDecisions[$cacheKey] = $cacheItem->get();
        }

        if ($this->cacheStorage->commit()) {
            return $storedDecisions;
        }

        return [];
    }

    /**
     * Retrieve a config value by name.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function getConfig(string $name, $default = null)
    {
        return (isset($this->configs[$name])) ? $this->configs[$name] : $default;
    }

    /**
     * @param $scope
     * @param $value
     * @param $type
     * @return Decision
     */
    protected function createInternalDecision($scope, $value, $type = Constants::REMEDIATION_BYPASS): Decision
    {
        return new Decision($this, $scope, $value, $type, Constants::ORIGIN, '', '', 0);
    }

    private function convertRawDecision(array $rawDecision): Decision
    {
        // @TODO check and validate $rawDecision
        return new Decision (
            $this,
            ucfirst($rawDecision['scope']),
            $rawDecision['value'],
            $rawDecision['type'],
            $rawDecision['origin'],
            $rawDecision['duration'],
            $rawDecision['scenario'],
            $rawDecision['id'] ?? 0
        );
    }

    protected function convertRawDecisionsToDecisions(array $rawDecisions)
    {
        $decisions = [];
        foreach ($rawDecisions as $rawDecision) {
            $decisions[] = $this->convertRawDecision($rawDecision);
        }

        return $decisions;
    }

    public function clearCache(): bool
    {
        return $this->cacheStorage->clear();
    }

    public function pruneCache(): bool
    {
        return $this->cacheStorage->prune();
    }

    abstract public function refreshDecisions(): array;
}