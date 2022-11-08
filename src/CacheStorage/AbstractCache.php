<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\CacheStorage;

use ArithmeticError;
use CrowdSec\RemediationEngine\CacheStorage\Memcached\TagAwareAdapter as MemcachedTagAwareAdapter;
use DivisionByZeroError;
use Exception;
use IPLib\Address\Type;
use IPLib\Factory;
use IPLib\Range\RangeInterface;
use IPLib\Range\Subnet;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\PruneableInterface;
use CrowdSec\RemediationEngine\Decision;
use CrowdSec\RemediationEngine\Constants;

abstract class AbstractCache
{
    /** @var int Cached array is cached index */
    public const CACHED_FLAG = 'is_cached';
    /** @var string Cache symbol */
    public const CACHE_SEP = '_';
    /** @var string The cache key to retrieve an empty cache item */
    private const EMPTY_ITEM = 'empty';
    /** @var int Maximum duration for a cache item
     * Forever is 10 years as PHP_INT_MAX will cause trouble with the Memcached Adapter
     * (int to float unwanted conversion)
     */
    private const FOREVER = 315360000;
    /** @var int Cache item content array expiration index */
    private const INDEX_EXP = 1;
    /** @var int Cache item content array identifier index */
    private const INDEX_ID = 2;
    /** @var int Cache item content array priority index */
    public const INDEX_PRIO = 3;
    /** @var int Cache item content array value index */
    public const INDEX_VALUE = 0;
    /** @var string The cache key prefix for a IPV4 range bucket */
    private const IPV4_BUCKET_KEY = 'RANGE_BUCKET_IPV4';
    /** @var int The size of ipv4 range cache bucket */
    private const IPV4_BUCKET_SIZE = 256;
    /** @var string The cache tag for range bucket cache item */
    private const RANGE_BUCKET_TAG = 'RANGE_BUCKET';
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

    /**
     * @throws CacheException
     */
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
            $this->cacheKeys[$scope][$value] = preg_replace('/[^A-Za-z0-9_.]/', self::CACHE_SEP, $result);
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

    /**
     * Prune (delete) of all expired cache items.
     *
     * @throws CacheException
     */
    public function prune(): bool
    {
        if ($this->adapter instanceof PruneableInterface) {
            return $this->adapter->prune();
        }

        throw new CacheException('Cache Adapter ' . \get_class($this->adapter) . ' is not pruneable.');
    }

    /**
     * @param Decision $decision
     * @return CacheItemInterface
     * @throws ArithmeticError
     * @throws CacheException
     * @throws DivisionByZeroError
     * @throws InvalidArgumentException
     */
    public function removeDecision(Decision $decision): CacheItemInterface
    {
        switch ($decision->getScope()) {
            case Constants::SCOPE_IP:
                $item = $this->remove($decision);
                break;
            case Constants::SCOPE_RANGE:
                $item = $this->removeRangeScoped($decision);
                break;
            default:
                $this->logger->warning('', [
                    'type' => 'CACHE_REMOVE_NON_IMPLEMENTED_SCOPE',
                    'decision' => $decision->toArray()
                ]);
                $item = $this->getEmptyItem();
                break;
        }

        return $item;
    }

    /**
     * @param string $scope
     * @param string $ip
     * @return array
     * @throws ArithmeticError
     * @throws CacheException
     * @throws DivisionByZeroError
     * @throws InvalidArgumentException
     */
    public function retrieveDecisionsForIp(string $scope, string $ip): array
    {
        $cachedDecisions = [];
        switch ($scope) {
            case Constants::SCOPE_IP:
                $cacheKey = $this->getCacheKey($scope, $ip);
                $item = $this->adapter->getItem(base64_encode($cacheKey));
                if ($item->isHit()) {
                    $cachedDecisions[] = $item->get();
                }
                break;
            case Constants::SCOPE_RANGE:
                $bucketInt = $this->getRangeIntForIp($ip);
                $bucketCacheKey = $this->getCacheKey(self::IPV4_BUCKET_KEY, (string)$bucketInt);
                $bucketItem = $this->adapter->getItem(base64_encode($bucketCacheKey));
                $cachedBuckets = $bucketItem->isHit() ? $bucketItem->get() : [];
                foreach ($cachedBuckets as $cachedBucket) {
                    $rangeString = $cachedBucket[self::INDEX_VALUE];
                    $address = Factory::parseAddressString($ip);
                    $range = Factory::parseRangeString($rangeString);
                    if ($range->contains($address)) {
                        $cacheKey = $this->getCacheKey(Constants::SCOPE_RANGE, $rangeString);
                        $item = $this->adapter->getItem(base64_encode($cacheKey));
                        if ($item->isHit()) {
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
                break;
        }

        return $cachedDecisions;
    }

    public function setStreamMode(bool $value): void
    {
        $this->configs['stream_mode'] = $value;
    }

    /**
     * @param Decision $decision
     * @return CacheItemInterface
     * @throws ArithmeticError
     * @throws CacheException
     * @throws DivisionByZeroError
     * @throws InvalidArgumentException
     */
    public function storeDecision(Decision $decision): CacheItemInterface
    {
        switch ($decision->getScope()) {
            case Constants::SCOPE_IP:
                $item = $this->store($decision);
                break;
            case Constants::SCOPE_RANGE:
                $item = $this->storeRangeScoped($decision);
                break;
            default:
                $this->logger->warning('', [
                    'type' => 'CACHE_STORE_NON_IMPLEMENTED_SCOPE',
                    'decision' => $decision->toArray()
                ]);
                $item = $this->getEmptyItem();
        }

        return $item;
    }

    /**
     * @param string $duration
     * @return int
     * @throws CacheException
     */
    protected function parseDurationToSeconds(string $duration): int
    {
        /**
         * 3h24m59.5565s or 3h24m5957ms or 149h, etc.
         */
        $re = '/(-?)(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)(?:\.\d+)?(m?)s)?/m';
        preg_match($re, $duration, $matches);
        if (!\count($matches)) {
            throw new CacheException('Unable to parse the following duration:' . $duration);
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

    private function cleanCachedValues(array $cachedValues, string $identifier, bool $flagIsCached = false): array
    {
        foreach ($cachedValues as $key => $cachedValue) {
            // Remove value with the same identifier
            if ($identifier === $cachedValue[self::INDEX_ID]) {
                // Flag to know that value was in cached
                if ($flagIsCached) {
                    $cachedValues[self::CACHED_FLAG] = true;
                }
                unset($cachedValues[$key]);
                continue;
            }
            // Remove expired value
            $currentTime = time();
            if ($currentTime > $cachedValue[self::INDEX_EXP]) {
                unset($cachedValues[$key]);
            }
        }

        return $cachedValues;
    }

    /**
     * Format decision to use a minimal amount of data (less cache data consumption)
     *
     * @param Decision $decision
     * @return array
     * @throws Exception
     *
     */
    private function format(Decision $decision): array
    {
        $streamMode = $this->getConfig('stream_mode', false);
        if (Constants::REMEDIATION_BYPASS === $decision->getType()) {
            /**
             * In stream mode we consider a clean IP forever (until the next cache refresh).
             */
            /** @var int $duration */
            $duration = $streamMode ? self::FOREVER : $this->getConfig('clean_ip_cache_duration', 0);

            return [
                self::INDEX_VALUE => Constants::REMEDIATION_BYPASS,
                self::INDEX_EXP => time() + $duration,
                self::INDEX_ID => $decision->getIdentifier(),
                self::INDEX_PRIO => $decision->getPriority()
            ];
        }

        return [
            self::INDEX_VALUE => $decision->getType(),
            self::INDEX_EXP => time() + $this->handleBadIpDuration($decision, $streamMode),
            self::INDEX_ID => $decision->getIdentifier(),
            self::INDEX_PRIO => $decision->getPriority()
        ];
    }

    /**
     * Format range to use a minimal amount of data (less cache data consumption)
     *
     * @param Decision $decision
     * @return array
     * @throws CacheException
     */
    private function formatIpV4Range(Decision $decision): array
    {
        $streamMode = $this->getConfig('stream_mode', false);

        return [
            self::INDEX_VALUE => $decision->getValue(),
            self::INDEX_EXP => time() + $this->handleBadIpDuration($decision, $streamMode),
            self::INDEX_ID => $decision->getIdentifier()
        ];
    }

    /**
     * @return CacheItemInterface
     * @throws InvalidArgumentException
     */
    private function getEmptyItem(): CacheItemInterface
    {
        return $this->adapter->getItem(base64_encode(self::EMPTY_ITEM));
    }

    /**
     * @param array $itemsToCache
     * @return int
     */
    private function getMaxExpiration(array $itemsToCache): int
    {
        return max(array_column($itemsToCache, self::INDEX_EXP));
    }

    /**
     * @param string $ip
     * @return int
     * @throws ArithmeticError
     * @throws DivisionByZeroError
     */
    private function getRangeIntForIp(string $ip): int
    {
        return intdiv(ip2long($ip), self::IPV4_BUCKET_SIZE);
    }

    /**
     * @param Decision $decision
     * @param int|null $bucketInt
     * @return array|string[]
     */
    private function getTags(Decision $decision, ?int $bucketInt = null): array
    {
        return $bucketInt ? [self::RANGE_BUCKET_TAG] : [Constants::CACHE_TAG_REM, $decision->getScope()];
    }

    /**
     * @param Decision $decision
     * @param bool $streamMode
     * @return int
     * @throws CacheException
     */
    private function handleBadIpDuration(Decision $decision, bool $streamMode): int
    {
        $duration = $this->parseDurationToSeconds($decision->getDuration());
        /**
         * Don't set a custom duration in stream mode to avoid bugs.
         * Only the stream update has to change the cache state.
         */
        if (!$streamMode) {
            $duration = min($this->getConfig('bad_ip_cache_duration', 0), $duration);
        }

        return $duration;
    }

    /**
     * @param Decision $decision
     * @return RangeInterface|null
     */
    private function manageRange(Decision $decision): ?RangeInterface
    {
        $rangeString = $decision->getValue();
        $range = Subnet::parseString($rangeString);
        if (null === $range) {
            $this->logger->warning('', [
                'type' => 'INVALID_RANGE',
                'decision' => $decision->toArray(),
            ]);

            return null;
        }
        $addressType = $range->getAddressType();
        if (Type::T_IPv6 === $addressType) {
            $this->logger->warning('', [
                'type' => 'IPV6_RANGE_NOT_IMPLEMENTED',
                'decision' => $decision->toArray(),
            ]);

            return null;
        }

        return $range;
    }

    /**
     * @param Decision $decision
     * @param int|null $bucketInt
     * @return CacheItemInterface
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function remove(Decision $decision, ?int $bucketInt = null): CacheItemInterface
    {
        $cacheKey = $bucketInt ? $this->getCacheKey(self::IPV4_BUCKET_KEY, (string)$bucketInt) :
            $this->getCacheKey($decision->getScope(), $decision->getValue());
        $item = $this->adapter->getItem(base64_encode($cacheKey));

        if ($item->isHit()) {
            $cachedValues = $this->cleanCachedValues($item->get(), $decision->getIdentifier(), true);
            if (!isset($cachedValues[self::CACHED_FLAG])) {
                return $item;
            }
            unset($cachedValues[self::CACHED_FLAG]);
            if (!$cachedValues) {
                $this->adapter->deleteItem(base64_encode($cacheKey));

                return $this->getEmptyItem();
            }
            $tags = $this->getTags($decision, $bucketInt);
            $item = $this->updateCacheItem($item, $cachedValues, $tags);
            if (!$this->adapter->saveDeferred($item)) {
                $this->logger->warning('', [
                    'type' => 'CACHE_STORE_DEFERRED_FAILED_FOR_REMOVE_DECISION',
                    'decision' => $decision->toArray(),
                    'bucket_int' => $bucketInt
                ]);
            }
        }

        return $item;
    }

    /**
     * @param Decision $decision
     * @return CacheItemInterface
     * @throws ArithmeticError
     * @throws CacheException
     * @throws DivisionByZeroError
     * @throws InvalidArgumentException
     */
    private function removeRangeScoped(Decision $decision): CacheItemInterface
    {
        $range = $this->manageRange($decision);
        if (!$range) {
            return $this->getEmptyItem();
        }

        $startAddress = $range->getStartAddress();
        $endAddress = $range->getEndAddress();

        $startInt = $this->getRangeIntForIp($startAddress->toString());
        $endInt = $this->getRangeIntForIp($endAddress->toString());
        for ($i = $startInt; $i <= $endInt; $i++) {
            $this->remove($decision, $i);
        }

        return $this->remove($decision);
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
     * @param Decision $decision
     * @param int|null $bucketInt
     * @return CacheItemInterface
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws Exception
     */
    private function store(Decision $decision, ?int $bucketInt = null): CacheItemInterface
    {
        $cacheKey = $bucketInt ? $this->getCacheKey(self::IPV4_BUCKET_KEY, (string)$bucketInt) :
            $this->getCacheKey($decision->getScope(), $decision->getValue());
        $item = $this->adapter->getItem(base64_encode($cacheKey));

        $cachedValues = $item->isHit() ? $this->cleanCachedValues($item->get(), $decision->getIdentifier()) : [];
        // Merge current value with cached values (if any).
        $currentValue = $bucketInt ? $this->formatIpV4Range($decision) : $this->format($decision);
        $decisionsToCache = array_merge($cachedValues, [$currentValue]);
        // Rebuild cache item
        $item = $this->updateCacheItem($item, $decisionsToCache, $this->getTags($decision, $bucketInt));

        if (!$this->adapter->saveDeferred($item)) {
            $this->logger->warning('', [
                'type' => 'CACHE_STORE_DEFERRED_FAILED',
                'decision' => $decision->toArray(),
                'bucket_int' => $bucketInt
            ]);
        }

        return $item;
    }

    /**
     * @param Decision $decision
     * @return CacheItemInterface
     * @throws ArithmeticError
     * @throws CacheException
     * @throws DivisionByZeroError
     * @throws InvalidArgumentException
     */
    private function storeRangeScoped(Decision $decision): CacheItemInterface
    {
        $range = $this->manageRange($decision);
        if (!$range) {
            return $this->getEmptyItem();
        }
        $startAddress = $range->getStartAddress();
        $endAddress = $range->getEndAddress();

        $startInt = $this->getRangeIntForIp($startAddress->toString());
        $endInt = $this->getRangeIntForIp($endAddress->toString());
        for ($i = $startInt; $i <= $endInt; $i++) {
            $this->store($decision, $i);
        }

        return $this->store($decision);
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

    /**
     * @param CacheItemInterface $item
     * @param array $valuesToCache
     * @param array $tags
     * @return CacheItemInterface
     * @throws Exception
     */
    private function updateCacheItem(CacheItemInterface $item, array $valuesToCache, array $tags): CacheItemInterface
    {
        $maxExpiration = $this->getMaxExpiration($valuesToCache);
        $item->set($valuesToCache);
        $item->expiresAt(new \DateTime('@' . $maxExpiration));
        $item->tag($tags);

        return $item;
    }
}