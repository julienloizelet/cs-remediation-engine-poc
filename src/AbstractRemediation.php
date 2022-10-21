<?php

class AbstractRemediation
{

    protected $cacheStorage;
    protected $client;
    protected $configs;

    public function __construct (array $configs, AbstractClient $client, CacheStorageInterface $cacheStorage){

        $this->configs = $configs;
        $this->client = $client;
        $this->cacheStorage = $cacheStorage;

    }

    /**
     * @param string $ip
     * @param array $rawDecisions
     * @return array
     *
     * if rawDecision is empty, we store a bypass for further call
     *
     *
     */
    public function storeDecisions(string $scope, string $value, array $rawDecisions): array
    {
        $formattedDecisions = [];
        if(!$rawDecisions){
            // Store a bypass decision
            $formattedDecision = $this->formatForCache(null);
            if($this->cacheStorage->storeDecision($scope, $value, $formattedDecision)) {
                $formattedDecisions[] = $formattedDecision;
            }
        }
        foreach ($rawDecisions as $rawDecision){
            $decision = new Decision($rawDecision['scope'], $rawDecision['value']);
            $formattedDecision = $this->formatForCache($decision);
            if($this->cacheStorage->storeDecision($scope, $value,$formattedDecision)){
                $formattedDecisions[] = $formattedDecision;
            }
        }

        return $formattedDecisions;
    }

    protected function sortRemediationByPriority($decisions){

        //@TODO
        return $decisions;
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

    private function formatForCache(?Decision $decision): array
    {
        $streamMode = $this->getConfig('stream_mode', false);
        if (!$decision) {
            $duration = time() + $this->getConfig('cache_expiration_for_clean_ip', 0);
            if ($streamMode) {
                /**
                 * In stream mode we consider a clean IP forever... until the next resync.
                 * in this case, forever is 10 years as PHP_INT_MAX will cause trouble with the Memcached Adapter
                 * (int to float unwanted conversion)
                 * */
                $duration = 315360000;
            }

            return [Constants::REMEDIATION_BYPASS, $duration, 0];
        }

        $duration = $this->parseDurationToSeconds($decision->getDuration());

        // Don't set a max duration in stream mode to avoid bugs. Only the stream update has to change the cache state.
        if (!$streamMode) {
            $duration = min($this->getConfig('cache_expiration_for_bad_ip'), $duration);
        }

        return $decision->formatForCache($duration);
    }

    private function parseDurationToSeconds(string $duration): int
    {
        $re = '/(-?)(?:(?:(\d+)h)?(\d+)m)?(\d+).\d+(m?)s/m';
        preg_match($re, $duration, $matches);
        if (!\count($matches)) {
            throw new \Exception("Unable to parse the following duration: ${$duration}.");
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
        if ('m' === ($matches[5])) { // units in milliseconds
            $seconds *= 0.001;
        }
        if ('-' === ($matches[1])) { // negative
            $seconds *= -1;
        }

        return (int)round($seconds);
    }



    //@TODO : pullUpdates

}