<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine;

use CrowdSec\RemediationEngine\Client\ClientInterface;
use CrowdSec\RemediationEngine\CacheStorage\AbstractCache;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

abstract class AbstractRemediation
{
    /**
     * @var AbstractCache
     */
    protected $cacheStorage;
    /**
     * @var array
     */
    protected $configs;
    /**
     * @var LoggerInterface
     */
    protected $logger;


    public function __construct(array $configs, AbstractCache $cacheStorage, LoggerInterface $logger = null)
    {
        $this->configs = $configs;
        $this->cacheStorage = $cacheStorage;
        if (!$logger) {
            $logger = new Logger('null');
            $logger->pushHandler(new NullHandler());
        }
        $this->logger = $logger;
    }

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
    public function removeDecisions(array $decisions): bool
    {
        foreach ($decisions as $decision) {
            // Save the cache without committing it to improve performance.
            $this->cacheStorage->removeDecision($decision);
        }

        return $decisions ? $this->cacheStorage->commit() : true;
    }

    /**
     * @param array $decisions
     * @return bool
     */
    public function storeDecisions(array $decisions): bool
    {
        /** @var Decision $decision */
        foreach ($decisions as $decision) {
            // Save the cache without committing it to improve performance.
            $this->cacheStorage->storeDecision($decision);
        }

        return $decisions ? $this->cacheStorage->commit() : true;
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

    private function validateRawDecision(array $rawDecision): void
    {
        if (isset(
            $rawDecision['scope'],
            $rawDecision['value'],
            $rawDecision['type'],
            $rawDecision['origin'],
            $rawDecision['duration'],
            $rawDecision['scenario']
        )
        ) {
            return;
        }

        throw new RemediationException('Raw decision is not as expected: ' . json_encode($rawDecision));
    }


    /**
     * Sort the decision array of a cache item, by remediation priorities.
     */
    protected function sortDecisionsByRemediationPriority(array $decisions): array
    {
        // Sort by priorities.
        /** @var callable $compareFunction */
        $compareFunction = self::class . '::comparePriorities';
        usort($decisions, $compareFunction);

        return $decisions;
    }

    /**
     * Compare two priorities.
     */
    private static function comparePriorities(array $a, array $b): int
    {
        $a = $a[AbstractCache::INDEX_PRIO];
        $b = $b[AbstractCache::INDEX_PRIO];
        if ($a == $b) {
            return 0;
        }

        return ($a < $b) ? -1 : 1;
    }
}