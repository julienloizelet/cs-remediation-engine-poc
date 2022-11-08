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
        // Force stream mode for CAPI remediation cache
        $cacheStorage->setStreamMode(true);
        $this->client = $client;
        parent::__construct($this->configs, $cacheStorage, $logger);
    }

    public function getIpRemediation(string $ip): string
    {
        // Ask cache for Ip scoped decision
        $ipDecisions = $this->cacheStorage->retrieveDecisionsForIp(Constants::SCOPE_IP, $ip);
        // Ask cache for Range scoped decision
        $rangeDecisions = $this->cacheStorage->retrieveDecisionsForIp(Constants::SCOPE_RANGE, $ip);

        $allDecisions = array_merge($ipDecisions ? $ipDecisions[0] : [], $rangeDecisions ? $rangeDecisions[0] : []);

        if (!$allDecisions) {
            // Store a bypass remediation if no cached decision found
            $decision = $this->createInternalDecision(Constants::SCOPE_IP, $ip);
            $this->storeDecisions([$decision]);

            return Constants::REMEDIATION_BYPASS;
        }

        $allDecisions = $this->sortDecisionsByRemediationPriority($allDecisions);
        // Return only a remediation with hightest priority
        return $allDecisions[0][AbstractCache::INDEX_VALUE] ?? Constants::REMEDIATION_BYPASS;
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
            'deleted' => [
                ["duration" => "147h",
                    "origin" => "CAPI2",
                    "scenario" => "manual",
                    "scope" => "range",
                    "type" => "ban",
                    "value" => "52.3.230.1/24"],
                ["duration" => "147h",
                    "origin" => "CAPI",
                    "scenario" => "manual",
                    "scope" => "range",
                    "type" => "ban",
                    "value" => "52.3.230.0/24"]
            ],
            'new' => [
            ]
        ];*/
        $newDecisions = $this->convertRawDecisionsToDecisions($rawDecisions['new'] ?? []);
        $deletedDecisions = $this->convertRawDecisionsToDecisions($rawDecisions['deleted'] ?? []);

        return $this->storeDecisions($newDecisions) && $this->removeDecisions($deletedDecisions);
    }

}