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

    public function clearCache(): bool
    {
        return $this->cacheStorage->clear();
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

    abstract public function getIpRemediation(string $ip): string;

    public function pruneCache(): bool
    {
        return $this->cacheStorage->prune();
    }

    abstract public function refreshDecisions(): bool;

    /**
     * @param array $decisions
     * @return bool
     */
    public function storeDecisions(array $decisions): bool
    {
        foreach ($decisions as $decision) {
            // Save the cache without committing it to improve performance.
            $this->cacheStorage->storeDecision($decision);
        }

        return $this->cacheStorage->commit();
    }

    /**
     * @param array $decisions
     * @return bool
     */
    public function removeDecisions(array $decisions): bool
    {
        foreach ($decisions as $decision) {
            // Save the cache without committing it to improve performance.
            $this->cacheStorage->removeDecision($decision);
        }

        return $this->cacheStorage->commit();
    }

    protected function convertRawDecisionsToDecisions(array $rawDecisions)
    {
        $decisions = [];
        foreach ($rawDecisions as $rawDecision) {
            $decisions[] = $this->convertRawDecision($rawDecision);
        }

        return $decisions;
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

    private function validateRawDecision(array $rawDecision): void
    {
        if (isset(
            $rawDecision['scope'],
            $rawDecision['value'],
            $rawDecision['type'],
            $rawDecision['origin'],
            $rawDecision['duration'],
            $rawDecision['scenario'],
        )
        ) {
            return;
        }

        throw new RemediationException('Raw decision is not as expected: ' . json_encode($rawDecision));
    }

    private function convertRawDecision(array $rawDecision): Decision
    {
        $this->validateRawDecision($rawDecision);
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
}