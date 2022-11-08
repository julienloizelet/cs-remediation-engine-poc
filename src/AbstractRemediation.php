<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine;

use CrowdSec\RemediationEngine\CacheStorage\AbstractCache;
use CrowdSec\RemediationEngine\CacheStorage\CacheException;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Psr\Cache\InvalidArgumentException;
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

    /**
     * Clear cache.
     *
     * @throws CacheStorage\CacheException
     */
    public function clearCache(): bool
    {
        return $this->cacheStorage->clear();
    }

    /**
     * Retrieve a flat config value by name.
     *
     * @return mixed|null
     */
    public function getConfig(string $name, $default = null)
    {
        return (isset($this->configs[$name])) ? $this->configs[$name] : $default;
    }

    /**
     * Retrieve remediation for some IP.
     */
    abstract public function getIpRemediation(string $ip): string;

    /**
     * Prune cache.
     *
     * @throws CacheStorage\CacheException
     */
    public function pruneCache(): bool
    {
        return $this->cacheStorage->prune();
    }

    /**
     * Pull fresh decisions and update the cache.
     */
    abstract public function refreshDecisions(): bool;

    /**
     * @throws CacheException
     * @throws InvalidArgumentException
     */
    public function removeDecisions(array $decisions): bool
    {
        foreach ($decisions as $decision) {
            $this->cacheStorage->removeDecision($decision);
        }

        return !$decisions || $this->cacheStorage->commit();
    }

    /**
     * @throws CacheException
     * @throws InvalidArgumentException
     */
    public function storeDecisions(array $decisions): bool
    {
        /** @var Decision $decision */
        foreach ($decisions as $decision) {
            $this->cacheStorage->storeDecision($decision);
        }

        return !$decisions || $this->cacheStorage->commit();
    }

    /**
     * @throws RemediationException
     */
    protected function convertRawDecisionsToDecisions(array $rawDecisions): array
    {
        $decisions = [];
        foreach ($rawDecisions as $rawDecision) {
            $decisions[] = $this->convertRawDecision($rawDecision);
        }

        return $decisions;
    }

    protected function createInternalDecision(
        string $scope,
        string $value,
        string $type = Constants::REMEDIATION_BYPASS
    ): Decision {
        return new Decision($this, $scope, $value, $type, Constants::ORIGIN, '', '', 0);
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
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
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

    /**
     * @throws RemediationException
     */
    private function convertRawDecision(array $rawDecision): Decision
    {
        $this->validateRawDecision($rawDecision);

        return new Decision(
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

    /**
     * @throws RemediationException
     */
    private function validateRawDecision(array $rawDecision): void
    {
        if (
            isset(
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
}
