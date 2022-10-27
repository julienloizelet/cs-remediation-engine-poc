<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine;

use CrowdSec\RemediationEngine\Client\AbstractClient;
use CrowdSec\RemediationEngine\CacheStorage\CacheStorageInterface;

class AbstractRemediation
{

    /**
     * @var CacheStorageInterface
     */
    protected $cacheStorage;
    protected $client;
    protected $configs;

    public function __construct (array $configs, AbstractClient $client, CacheStorageInterface $cacheStorage){

        // @TODO validate configs
        $this->configs = $configs;
        $this->client = $client;
        $this->cacheStorage = $cacheStorage;

    }

    /**
     * @param string $scope
     * @param string $value
     * @param array $remoteDecisions
     * @return array // array of cache item value
     */
    public function storeDecisions(string $scope, string $value, array $remoteDecisions): array
    {
        $storedDecisions = [];
        if(!$remoteDecisions){
            // Store a bypass decision
            $decision = new Decision( $scope, $value, Constants::REMEDIATION_BYPASS, Constants::ORIGIN, '', '');
            $cacheItem = $this->cacheStorage->storeDecision($decision);
            $storedDecisions[] = $cacheItem->get();
        }
        foreach ($remoteDecisions as $remoteDecision){
            $decision = new Decision(
                $remoteDecision['scope'],
                $remoteDecision['value'],
                $remoteDecision['type'],
                $remoteDecision['origin'],
                $remoteDecision['duration'],
                $remoteDecision['scenario'],
                $remoteDecision['id'],

            );
            $cacheItem = $this->cacheStorage->storeDecision($decision);
            $storedDecisions[] = $cacheItem->get();
        }

        if($this->cacheStorage->commit()){
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




    //@TODO : pullUpdates

}