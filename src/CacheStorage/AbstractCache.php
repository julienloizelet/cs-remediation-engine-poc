<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\CacheStorage;

use CrowdSec\RemediationEngine\CacheStorage\Memcached\TagAwareAdapter as MemcachedTagAwareAdapter;
use IPLib\Address\Type;
use IPLib\Factory;
use IPLib\Range\Subnet;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\PruneableInterface;
use CrowdSec\RemediationEngine\Decision;
use CrowdSec\RemediationEngine\Constants;

abstract class AbstractCache
{
    /** @var string Cache symbol */
    public const CACHE_SEP = '_';
    /** @var string The cache key to retrieve an empty cache item */
    private const EMPTY_ITEM = 'empty';
    /** @var string The cache key prefix for a IPV4 range bucket */
    private const IPV4_BUCKET_KEY = 'RANGE_BUCKET_IPV4';
    /** @var int The size of ipv4 range cache bucket */
    private const IPV4_BUCKET_SIZE = 256;
    /** @var string The cache tag for range bucket cache item*/
    private const RANGE_BUCKET_TAG = 'RANGE_BUCKET';
    /** @var int Maximum duration for a cache item */
    private const FOREVER = 315360000;


    public const INDEX_VALUE = 0;
    private const INDEX_EXP = 1;
    private const INDEX_ID = 2;
    private const INDEX_PRIO = 3;



    /** @var TagAwareAdapter|MemcachedTagAwareAdapter|RedisTagAwareAdapter */
    protected $adapter;
    /**
     * @var array
     */
    protected $configs;
    /**
     * @var LoggerInterface
     *
     */
    protected $logger;
    /**
     * @var array
     */
    private $cacheKeys = [];

    public function __construct(array $configs, TagAwareAdapterInterface $adapter, LoggerInterface $logger = null)
    {
        $this->configs = $configs;
        $this->adapter = $adapter;
        if (!$logger) {
            $logger = new Logger('null');
            $logger->pushHandler(new NullHandler());
        }
        $this->logger = $logger;
    }

    public function clear(): bool
    {
        $this->setCustomErrorHandler();
        try {
            $cleared = $this->adapter->clear();
        } finally {
            $this->unsetCustomErrorHandler();
        }

        return $cleared;
    }

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
     * Cache key convention.
     *
     * @param string $scope
     * @param string $value
     * @return string
     * @throws CacheException
     */
    public function getCacheKey(string $scope, string $value): string
    {
        if (!isset($this->cacheKeys[$scope][$value])) {
            switch ($scope) {
                case Constants::SCOPE_IP:
                case Constants::SCOPE_RANGE:
                case self::IPV4_BUCKET_KEY:
                    $result = $scope . self::CACHE_SEP . $value;
                    break;
                default:
                    throw new CacheException('Unknown scope:' . $scope);
            }

            /**
             * Replace unauthorized symbols
             * @see https://symfony.com/doc/current/components/cache/cache_items.html#cache-item-keys-and-values
             */
            $this->cacheKeys[$scope][$value] = preg_replace('/[^A-Za-z0-9_.]/', self::CACHE_SEP, $result);;
        }

        return $this->cacheKeys[$scope][$value];
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

    public function setStreamMode(bool $value): void
    {
        $this->configs['stream_mode'] = $value;
    }

    public function prune(): bool
    {
        if ($this->adapter instanceof PruneableInterface) {
            return $this->adapter->prune();
        }

        throw new CacheException('Cache Adapter ' . \get_class($this->adapter) . ' is not prunable.');
    }

    public function removeDecision(Decision $decision): CacheItemInterface
    {
        //@TODO manage Range scoped decision
        $cacheKey = $this->getCacheKey($decision->getScope(), $decision->getValue());
        $item = $this->adapter->getItem(base64_encode($cacheKey));

        // Retrieve cached decisions
        if ($item->isHit()) {
            $cachedDecisions = $item->get();
            // Remove decision with the same identifier
            $index = array_search($decision->getIdentifier(), array_column($cachedDecisions, 'identifier'));
            if (false === $index) {
                return $item;
            }
            unset($cachedDecisions[$index]);
            if (!$cachedDecisions) {
                $this->adapter->deleteItem(base64_encode($cacheKey));

                return $this->adapter->getItem(base64_encode($cacheKey));
            }
            $item = $this->updateCacheItem($item, $cachedDecisions, [Constants::CACHE_TAG_REM]);
            if (!$this->adapter->saveDeferred($item)) {
                $this->logger->warning('', [
                    'type' => 'CACHE_STORE_DEFERRED_FAILED_FOR_REMOVE_DECISION',
                    'decision' => $decision->toArray(),
                ]);
            }
        }

        return $item;
    }

    public function retrieveDecisionsForIp(string $scope, string $ip): array
    {
        $cachedDecisions = [];
        switch ($scope) {
            case Constants::SCOPE_IP:
                $cacheKey = $this->getCacheKey($scope, $ip);
                $item = $this->adapter->getItem(base64_encode($cacheKey));
                if($item->isHit()){
                    $cachedDecisions[] = $item->get();
                }
                break;
            case Constants::SCOPE_RANGE:
                $rangeInt = $this->getRangeIntForIp($ip);
                $bucketCacheKey = $this->getCacheKey(self::IPV4_BUCKET_KEY, (string) $rangeInt);
                $bucketItem = $this->adapter->getItem(base64_encode($bucketCacheKey));
                $cachedBuckets = $bucketItem->isHit() ? $bucketItem->get() : [];
                foreach ($cachedBuckets as $cachedBucket){
                    $rangeString = $cachedBucket[self::INDEX_VALUE];
                    $address = Factory::parseAddressString($ip);
                    $range = Factory::parseRangeString($rangeString);
                    if($range->contains($address)){
                        $cacheKey = $this->getCacheKey(Constants::SCOPE_RANGE, $rangeString);
                        $item = $this->adapter->getItem(base64_encode($cacheKey));
                        if($item->isHit()){
                            $cachedDecisions[] = $item->get();
                        }
                    }
                }
                break;
            default:
                $this->logger->warning('', [
                    'type' => 'CACHE_RETRIEVE_FOR_IP_NON_IMPLEMENTED_SCOPE',
                    'scope' => $scope
                ]);
        }
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
        switch ($decision->getScope()) {
            case Constants::SCOPE_IP:
                $item = $this->handleDecisionItemStorage($decision);
                break;
            case Constants::SCOPE_RANGE:
                $item = $this->handleRangeScopedStorage($decision);
                break;
            default:
                $this->logger->warning('', [
                    'type' => 'CACHE_STORE_NON_IMPLEMENTED_SCOPE',
                    'decision' => $decision->toArray()
                ]);
                $item = $this->getEmptyItem();
        }

        if (!$this->adapter->saveDeferred($item)) {
            $this->logger->warning('', [
                'type' => 'CACHE_STORE_DEFERRED_FAILED',
                'decision' => $decision->toArray()
            ]);
        }

        return $item;
    }

    protected function parseDurationToSeconds(string $duration): int
    {
        /**
         * 3h24m59.5565s or 3h24m5957ms or 149h, etc.
         */
        $re = '/(-?)(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)(?:\.\d+)?(m?)s)?/m';
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

    /**
     * Compare two priorities.
     */
    private static function comparePriorities(array $a, array $b): int
    {
        $a = $a[self::INDEX_PRIO];
        $b = $b[self::INDEX_PRIO];
        if ($a == $b) {
            return 0;
        }

        return ($a < $b) ? -1 : 1;
    }

    private function getEmptyItem(): CacheItemInterface
    {
        return $this->adapter->getItem(base64_encode(self::EMPTY_ITEM));
    }

    /**
     * Format decision to use a minimal amount of data (less cache data consumption)
     *
     * @param Decision $decision
     * @return array
     * @throws \Exception
     *
     */
    private function formatForCache(Decision $decision): array
    {
        $streamMode = $this->getConfig('stream_mode', false);
        if (Constants::REMEDIATION_BYPASS === $decision->getType()) {
            /**
             * In stream mode we consider a clean IP forever... until the next resync.
             * in this case, forever is 10 years as PHP_INT_MAX will cause trouble with the Memcached Adapter
             * (int to float unwanted conversion)
             */
            $duration = $streamMode ? self::FOREVER : $this->getConfig('clean_ip_cache_duration', 0);

            return [
                self::INDEX_VALUE => Constants::REMEDIATION_BYPASS,
                self::INDEX_EXP => time() + $duration,
                self::INDEX_ID => $decision->getIdentifier(),
                self::INDEX_PRIO => $decision->getPriority()
            ];
        }

        $duration = $this->parseDurationToSeconds($decision->getDuration());

        // Don't set a max duration in stream mode to avoid bugs. Only the stream update has to change the cache state.
        if (!$streamMode) {
            $duration = min($this->getConfig('bad_ip_cache_duration'), $duration);
        }

        return [
            self::INDEX_VALUE => $decision->getType(),
            self::INDEX_EXP => time() + $duration,
            self::INDEX_ID => $decision->getIdentifier(),
            self::INDEX_PRIO => $decision->getPriority()
        ];
    }

    /**
     * Format range to use a minimal amount of data (less cache data consumption)
     *
     * @param string $rangeString
     * @param string $duration
     * @return array
     * @throws \Exception
     */
    private function formatIpV4RangeBucketForCache(string $rangeString, string $duration): array
    {
        return [
            self::INDEX_VALUE => $rangeString,
            self::INDEX_EXP => time() + $this->parseDurationToSeconds($duration)
        ];

    }

    private function getMaxExpiration(array $itemsToCache): int
    {
        return max(array_column($itemsToCache, self::INDEX_EXP));
    }

    private function handleDecisionItemStorage(Decision $decision): CacheItemInterface
    {
        $cacheKey = $this->getCacheKey($decision->getScope(), $decision->getValue());
        $item = $this->adapter->getItem(base64_encode($cacheKey));

        // Retrieve cached decisions
        $cachedDecisions = $item->isHit() ? $item->get() : [];

        // Erase previous decision(s) with the same identifier
        foreach ($cachedDecisions as $itemKey => $itemValue) {
            if ($itemValue[self::INDEX_ID] === $decision->getIdentifier()) {
                unset($cachedDecisions[$itemKey]);
            }
        }
        // Merge current decision with cached decisions (if any).
        $decisionsToCache = array_merge($cachedDecisions, [$this->formatForCache($decision)]);

        // Rebuild cache item
        return $this->updateCacheItem($item, $decisionsToCache, [Constants::CACHE_TAG_REM, $decision->getScope()]);
    }

    private function handleRangeBucketItemStorage(
        int $bucketInt,
        string $rangeValue,
        string $duration): CacheItemInterface
    {
        $cacheKey = $this->getCacheKey(self::IPV4_BUCKET_KEY, (string) $bucketInt);
        $item = $this->adapter->getItem(base64_encode($cacheKey));
        // Retrieve cached ranges of the range bucket
        $cachedRanges = $item->isHit() ? $item->get() : [];
        // Erase previous range(s) with the same range value
        foreach ($cachedRanges as $itemKey => $itemValue) {
            // @TODO unset expired item ? compare time() to itemValue[duration]
            if ($itemValue[self::INDEX_VALUE] === $rangeValue) {
                unset($cachedRanges[$itemKey]);
            }
        }
        // Merge current range with cached ranges (if any).
        $rangesToCache = array_merge($cachedRanges, [$this->formatIpV4RangeBucketForCache($rangeValue, $duration)]);
        $maxExpiration = $this->getMaxExpiration($rangesToCache);
        $item->expiresAt(new \DateTime('@' . $maxExpiration));
        $item->set($rangesToCache);
        $item->tag(self::RANGE_BUCKET_TAG);

        if (!$this->adapter->saveDeferred($item)) {
            $this->logger->warning('', [
                'type' => 'CACHE_STORE_DEFERRED_FAILED_FOR_RANGE_BUCKET',
                'range' => $rangeValue,
                'bucket_int' => $bucketInt
            ]);
        }

        return $item;
    }

    private function handleRangeScopedStorage(Decision $decision): CacheItemInterface
    {
        // @TODO exclude 32 bits system
        $rangeString = $decision->getValue();
        $duration = $decision->getDuration();
        $range = Subnet::parseString($rangeString);
        if (null === $range) {
            $this->logger->warning('', [
                'type' => 'INVALID_RANGE_TO_ADD_FROM_DECISION',
                'decision' => $decision->toArray(),
            ]);
            return $this->getEmptyItem();
        }
        $addressType = $range->getAddressType();
        if (Type::T_IPv6 === $addressType) {
            $this->logger->warning('', [
                'type' => 'IPV6_RANGE_STORAGE_NOT_IMPLEMENTED',
                'decision' => $decision->toArray(),
            ]);
            return $this->getEmptyItem();
        }

        $startAddress = $range->getStartAddress();
        $endAddress = $range->getEndAddress();

        $startInt = $this->getRangeIntForIp($startAddress->toString());
        $endInt = $this->getRangeIntForIp($endAddress->toString());
        for ($i = $startInt; $i <= $endInt; $i++){
            $this->handleRangeBucketItemStorage($i, $rangeString, $duration);
        }
        return $this->handleDecisionItemStorage($decision);
    }


    private function getRangeIntForIp(string $ip): int
    {
        return intdiv(ip2long($ip), self::IPV4_BUCKET_SIZE);
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
     * Sort the decision array of a cache item, by remediation priorities.
     */
    public static function sortDecisionsByRemediationPriority(array $decisions): array
    {
        // Sort by priorities.
        /** @var callable $compareFunction */
        $compareFunction = self::class . '::comparePriorities';
        usort($decisions, $compareFunction);

        return $decisions;
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

    private function updateCacheItem(CacheItemInterface $item, array $decisionsToCache, array $tags): CacheItemInterface
    {
        $maxExpiration = $this->getMaxExpiration($decisionsToCache);
        // Sort decisions by remediation priority
        $prioritizedDecisions = $this->sortDecisionsByRemediationPriority($decisionsToCache);

        $item->set($prioritizedDecisions);
        $item->expiresAt(new \DateTime('@' . $maxExpiration));
        $item->tag($tags);

        return $item;
    }
}