<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\CacheStorage;

use CrowdSec\RemediationEngine\CacheStorage\Memcached\TagAwareAdapter as MemcachedTagAwareAdapter;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use CrowdSec\RemediationEngine\Decision;
use CrowdSec\RemediationEngine\Constants;

abstract class AbstractCache implements CacheStorageInterface
{

    public const CACHE_SEP = '_';

    /** @var TagAwareAdapter|MemcachedTagAwareAdapter|RedisTagAwareAdapter */
    protected $adapter;
    protected $configs;

    /**
     * Wrap the cacheAdapter to catch warnings.
     *
     * @throws CacheException
     * */
    public function commit(): bool
    {
        $this->setCustomErrorHandler();
        try {
            $result = $this->adapter->commit();
        } finally {
            $this->unsetCustomErrorHandler();
        }

        return $result;
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

    public function removeDecision(Decision $decision): bool
    {
        // TODO: Implement removeDecision() method.
    }

    public function retrieveDecisions(string $scope, string $value): array
    {

        $cacheKey = $this->getCacheKey($scope, $value);
        $item = $this->adapter->getItem(base64_encode($cacheKey));

        // Merge with existing decisions (if any).
        $cachedDecisions = $item->isHit() ? $item->get() : [];

        return $cachedDecisions;
    }

    /**
     * @param Decision $decision
     * @return CacheItemInterface
     * @throws \Psr\Cache\CacheException
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function storeDecision(Decision $decision): CacheItemInterface
    {

        //@TODO manage Range scoped decision
        $cacheKey = $this->getCacheKey($decision->getScope(), $decision->getValue());
        $item = $this->adapter->getItem(base64_encode($cacheKey));

        // Retrieve cached decisions
        $cachedDecisions = $item->isHit() ? $item->get() : [];

        // Erase previous decision(s) with the same id
        foreach($cachedDecisions as $itemKey => $itemValue){
            if($itemValue[2] === $decision->getIdentifier()){
                unset($cachedDecisions[$itemKey]);
            }
        }
        // Merge current decision with cached decisions (if any).
        $decisionsToCache = array_merge($cachedDecisions, [$this->formatForCache($decision)]);

        // Build the item lifetime in cache and sort remediations by priority
        $maxLifetime = max(array_column($decisionsToCache, 'duration'));
        $prioritizedDecisions = $this->sortDecisionsByRemediationPriority($decisionsToCache);

        $item->set($prioritizedDecisions);
        $item->expiresAt(new \DateTime('@' . $maxLifetime));
        $item->tag(Constants::CACHE_TAG_REM);

        // Save the cache without committing it to the cache system.
        // Useful to improve performance when updating the cache.
        if (!$this->adapter->saveDeferred($item)) {
            $message = 'cacheKey:'.$cacheKey.'. Unable to save this deferred item in cache: ' .
                       $decision->getType(). 'for' . $decision->getDuration() .
                       ', (decision: ' .$decision->getIdentifier().')';
            throw new CacheException($message);
        }

        return $item;
    }

    /**
     * Sort the decision array of a cache item, by remediation priorities.
     */
    private function sortDecisionsByRemediationPriority(array $decisions): array
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
        $a = $a['priority'];
        $b = $b['priority'];
        if ($a == $b) {
            return 0;
        }

        return ($a < $b) ? -1 : 1;
    }

    protected function formatForCache(Decision $decision): array
    {
        $streamMode = $this->getConfig('stream_mode', false);
        if (Constants::REMEDIATION_BYPASS === $decision->getType()) {
            /**
             * In stream mode we consider a clean IP forever... until the next resync.
             * in this case, forever is 10 years as PHP_INT_MAX will cause trouble with the Memcached Adapter
             * (int to float unwanted conversion)
             */
            $duration = $streamMode ? 315360000 : time() + $this->getConfig('clean_ip_cache_duration', 0);

            return [
                'type' => Constants::REMEDIATION_BYPASS,
                'duration' => $duration,
                'identifier' => $decision->getIdentifier(),
                'priority' => $decision->getPriority()
                ];
        }

        $duration = $this->parseDurationToSeconds($decision->getDuration());

        // Don't set a max duration in stream mode to avoid bugs. Only the stream update has to change the cache state.
        if (!$streamMode) {
            // @TODO : avec CAPI, on considère qu'on est toujours en stream mode ? i.e force config stream_mode  true ?
            $duration = min($this->getConfig('bad_ip_cache_duration'), $duration);
        }

        return [
            'type' => $decision->getType(),
            'duration' => time() + $duration,
            'identifier' => $decision->getIdentifier(),
            'priority' => $decision->getPriority()
        ];
    }

    protected function parseDurationToSeconds(string $duration): int
    {
        /**
         * 3h24m59.5565s or 3h24m5957ms or 149h, etc.
         */
        $re = '/(-?)(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)(?:.\d+)(m?)s)?/m';
        preg_match($re, $duration, $matches);
        if (!\count($matches)) {
            throw new \Exception('Unable to parse the following duration:' . $duration);
        }
        $seconds = 0;
        if (isset($matches[2])) {
            $seconds += ((int)$matches[2]) * 3600; // hours
        }
        if (isset($matches[3])) {
            $seconds += ((int)$matches[3]) * 60; // minutes
        }
        if (isset($matches[4])) {
            $seconds += ((int)$matches[4]); // seconds
        }
        if (isset($matches[5]) && 'm' === ($matches[5])) { // units in milliseconds
            $seconds *= 0.001;
        }
        if ('-' === ($matches[1])) { // negative
            $seconds *= -1;
        }

        return (int)round($seconds);
    }

    public function getCacheKey(string $scope, string $value): string
    {

        // @TODO replace unauthorized chars
        // @TODO handle RANGE SCOPE as IP
        return $scope . self::CACHE_SEP . $value;
    }

    /**
     * When Memcached connection fail, it throws an unhandled warning.
     * To catch this warning as a clean exception we have to temporarily change the error handler.
     * @throws CacheException
     */
    private function setCustomErrorHandler(): void
    {
        if ($this->adapter instanceof MemcachedTagAwareAdapter) {
            set_error_handler(function ($errno, $errstr) {
                $message = "Error when connecting to Memcached. (Error level: $errno)" .
                           "Original error was: $errstr";
                throw new CacheException($message);
            });
        }
    }

    /**
     * When the selected cache adapter is MemcachedAdapter, revert to the previous error handler.
     * */
    private function unsetCustomErrorHandler(): void
    {
        if ($this->adapter instanceof MemcachedTagAwareAdapter) {
            restore_error_handler();
        }
    }
}