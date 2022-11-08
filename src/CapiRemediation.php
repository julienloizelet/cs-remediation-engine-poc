<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine;

use CrowdSec\CapiClient\Watcher;
use CrowdSec\RemediationEngine\CacheStorage\AbstractCache;
use CrowdSec\RemediationEngine\CacheStorage\CacheException;
use CrowdSec\RemediationEngine\Configuration\Capi as CapiRemediationConfig;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Processor;

class CapiRemediation extends AbstractRemediation
{
    /**
     * @var Watcher
     */
    private $client;

    /** @var array The list of each known CAPI remediation, sorted by priority */
    public const ORDERED_REMEDIATIONS = [Constants::REMEDIATION_BAN, Constants::REMEDIATION_BYPASS];

    public function __construct(
        array $configs,
        Watcher $client,
        AbstractCache $cacheStorage,
        LoggerInterface $logger = null
    ) {
        $this->configure($configs);
        // Force stream mode for CAPI remediation cache
        $cacheStorage->setStreamMode(true);
        $this->client = $client;
        parent::__construct($this->configs, $cacheStorage, $logger);
    }

    /**
     * {@inheritdoc}
     *
     * @throws CacheException
     * @throws InvalidArgumentException
     */
    public function getIpRemediation(string $ip): string
    {
        // Ask cache for Ip scoped decision
        $ipDecisions = $this->cacheStorage->retrieveDecisionsForIp(Constants::SCOPE_IP, $ip);
        // Ask cache for Range scoped decision
        $rangeDecisions = $this->cacheStorage->retrieveDecisionsForIp(Constants::SCOPE_RANGE, $ip);

        $allDecisions = array_merge(
            $ipDecisions ? $ipDecisions[0] : [],
            $rangeDecisions ? $rangeDecisions[0] : []
        );

        if (!$allDecisions) {
            // Store a bypass remediation if no cached decision found
            $decision = $this->createInternalDecision(Constants::SCOPE_IP, $ip);
            $this->storeDecisions([$decision]);

            return Constants::REMEDIATION_BYPASS;
        }

        $allDecisions = $this->sortDecisionsByRemediationPriority($allDecisions);

        // Return only a remediation with the highest priority
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

    /**
     * {@inheritdoc}
     *
     * @throws CacheException
     * @throws InvalidArgumentException
     * @throws RemediationException
     */
    public function refreshDecisions(): array
    {
        $rawDecisions = $this->client->getStreamDecisions();
        /* $rawDecisions = [
             'deleted' => [
                 ["duration" => "147h",
                     "origin" => "CAPI12",
                     "scenario" => "manual",
                     "scope" => "range",
                     "type" => "ban",
                     "value" => "52.3.230.0/24"],
             ],
             'new' => [
                 ["duration" => "147h",
                     "origin" => "CAPI",
                     "scenario" => "manual",
                     "scope" => "range",
                     "type" => "ban",
                     "value" => "1.2.3.4/24"]
             ]
         ];*/
        $newDecisions = $this->convertRawDecisionsToDecisions($rawDecisions['new'] ?? []);
        $deletedDecisions = $this->convertRawDecisionsToDecisions($rawDecisions['deleted'] ?? []);

        return [
            'new' => $this->storeDecisions($newDecisions),
            'deleted' => $this->removeDecisions($deletedDecisions),
        ];
    }
}
