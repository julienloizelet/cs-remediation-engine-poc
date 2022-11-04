<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine;

use CrowdSec\RemediationEngine\CacheStorage\AbstractCache;
use CrowdSec\RemediationEngine\Configuration\Capi as CapiRemediationConfig;
use CrowdSec\CapiClient\Watcher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Processor;

class CapiRemediation extends AbstractRemediation
{
    /**
     * @var Watcher
     */
    private $client;

    /** @var array<string> The list of each known CAPI remediation, sorted by priority */
    public const ORDERED_REMEDIATIONS = [Constants::REMEDIATION_BAN, Constants::REMEDIATION_BYPASS];

    public function __construct(
        array           $configs,
        Watcher         $client,
        AbstractCache   $cacheStorage,
        LoggerInterface $logger = null
    )
    {
        $this->configure($configs);
        // Force stream mode for CAPI remediation
        $this->configs['stream_mode'] = true;
        $this->client = $client;
        parent::__construct($this->configs, $cacheStorage, $logger);
    }

    public function getIpRemediation(string $ip): string
    {
        // Ask cache for Ip scoped decision
        $ipDecisions = $this->cacheStorage->retrieveDecisions(Constants::SCOPE_IP, $ip);
        // Ask cache for Range scoped decision
        $rangeDecisions = $this->cacheStorage->retrieveDecisions(Constants::SCOPE_RANGE, $ip);


        if (!$ipDecisions) {
            // Store a bypass remediation if no cached decision found
            $decision = $this->createInternalDecision(Constants::SCOPE_IP, $ip);
            $this->storeDecisions([$decision]);

            return Constants::REMEDIATION_BYPASS;
        }

        //@TODO manage Range scoped decision

        return $ipDecisions[0][AbstractCache::INDEX_VALUE] ?? Constants::REMEDIATION_BYPASS;
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

    public function refreshDecisions(): bool
    {
        $rawDecisions = $this->client->getStreamDecisions();
        /*$rawDecisions = [
            'deleted' => [],
            'new' => [
                ["duration" => "147h",
                    "origin" => "CAPI",
                    "scenario" => "manual",
                    "scope" => "range",
                    "type" => "ban",
                    "value" => "52.3.230.0/24"],
                ["duration" => "150h",
                    "origin" => "CAPI",
                    "scenario" => "manual",
                    "scope" => "range",
                    "type" => "bypass",
                    "value" => "52.3.230.0/24"]
            ]
        ];*/
        $newDecisions = $this->convertRawDecisionsToDecisions($rawDecisions['new'] ?? []);
        $deletedDecisions = $this->convertRawDecisionsToDecisions($rawDecisions['deleted'] ?? []);

        return $this->storeDecisions($newDecisions) && $this->removeDecisions($deletedDecisions);
    }

}