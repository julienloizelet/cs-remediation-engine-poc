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
    /** @var string The CrowdSec name for new decisions */
    public const CS_NEW = 'new';

    /** @var string The CrowdSec name for deleted decisions */
    public const CS_DEL = 'deleted';

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
     */
    public function clearCache(): bool
    {
        return $this->cacheStorage->clear();
    }

    /**
     * Retrieve a config by name.
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
     * Return the total of added and removed records. // ['new' => x, 'deleted' => y].
     */
    abstract public function refreshDecisions(): array;

    /**
     * Remove decisions from cache.
     *
     * @throws CacheException
     * @throws InvalidArgumentException|\Psr\Cache\CacheException
     */
    public function removeDecisions(array $decisions): int
    {
        if (!$decisions) {
            return 0;
        }
        $deferCount = 0;
        $doneCount = 0;
        foreach ($decisions as $decision) {
            $removeResult = $this->cacheStorage->removeDecision($decision);
            $deferCount += $removeResult[AbstractCache::DEFER];
            $doneCount += $removeResult[AbstractCache::DONE];
        }

        return $doneCount + ($this->cacheStorage->commit() ? $deferCount : 0);
    }

    /**
     * Add decisions in cache.
     *
     * @throws CacheException
     * @throws InvalidArgumentException|\Psr\Cache\CacheException
     */
    public function storeDecisions(array $decisions): int
    {
        $result = 0;
        if (!$decisions) {
            return $result;
        }
        $deferCount = 0;
        $doneCount = 0;
        foreach ($decisions as $decision) {
            $storeResult = $this->cacheStorage->storeDecision($decision);
            $deferCount += $storeResult[AbstractCache::DEFER];
            $doneCount += $storeResult[AbstractCache::DONE];
        }

        return $doneCount + ($this->cacheStorage->commit() ? $deferCount : 0);
    }

    protected function convertRawDecisionsToDecisions(array $rawDecisions): array
    {
        $decisions = [];
        foreach ($rawDecisions as $rawDecision) {
            if ($this->validateRawDecision($rawDecision)) {
                $decisions[] = $this->convertRawDecision($rawDecision);
            }
        }

        return $decisions;
    }

    protected function createInternalDecision(
        string $scope,
        string $value,
        string $type = Constants::REMEDIATION_BYPASS
    ): Decision {
        return new Decision(
            $this,
            $scope,
            $value,
            $type,
            Constants::ORIGIN,
            '',
            Constants::VERSION,
            0
        );
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
     *
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

    private function convertRawDecision(array $rawDecision): Decision
    {
        return new Decision(
            $this,
            $rawDecision['scope'],
            $rawDecision['value'],
            $rawDecision['type'],
            $rawDecision['origin'],
            $rawDecision['duration'],
            $rawDecision['scenario'],
            $rawDecision['id'] ?? 0
        );
    }

    private function validateRawDecision(array $rawDecision): bool
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
            return true;
        }

        $this->logger->warning('', [
            'type' => 'RAW_DECISION_NOT_AS_EXPECTED',
            'raw_decision' => json_encode($rawDecision),
        ]);

        return false;
    }
}
