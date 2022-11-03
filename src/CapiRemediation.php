<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine;

use CrowdSec\RemediationEngine\CacheStorage\AbstractCache;
use CrowdSec\RemediationEngine\Configuration\Capi as CapiRemediationConfig;
use CrowdSec\CapiClient\Watcher;
use Symfony\Component\Config\Definition\Processor;

class CapiRemediation extends AbstractRemediation implements RemediationEngineInferface
{
    /** @var array<string> The list of each known CAPI remediation, sorted by priority */
    public const ORDERED_REMEDIATIONS = [Constants::REMEDIATION_BAN, Constants::REMEDIATION_BYPASS];

    public function __construct (array $configs, Watcher $client, AbstractCache $cacheStorage){

        $this->configure($configs);
        // Force stream mode for CAPI remediation
        $this->configs['stream_mode'] = true;
        $this->client = $client;
        $this->cacheStorage = $cacheStorage;
    }

    public function getIpRemediation(string $ip): string
    {
        // Ask cache for Ip scoped decision
        $ipDecisions = $this->cacheStorage->retrieveDecisions(Constants::SCOPE_IP, $ip);
        if(!$ipDecisions){
            // Store a bypass remediation if no cached decision found
            $decision = $this->createInternalDecision(Constants::SCOPE_IP, $ip);
            $this->storeDecisions([$decision]);
            return Constants::REMEDIATION_BYPASS;
        }

        return $ipDecisions[0]['type'] ?? Constants::REMEDIATION_BYPASS;
    }

    /**
     * Process and validate input configurations.
     */
    private function configure(array $configs): void
    {
        $configuration = new CapiRemediationConfig();
        $processor = new Processor();
        $this->configs = $processor->processConfiguration($configuration, [$configs]);
    }


    public function refreshDecisions(): array
    {

        $rawDecisions = $this->client->getStreamDecisions();
        $newDecisions = $this->convertRawDecisionsToDecisions($rawDecisions['new']);
        return $this->storeDecisions($newDecisions);
        // @TODO delete deleted Decision

    }


}