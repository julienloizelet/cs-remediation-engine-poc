<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine;


class Decision
{
    private $duration;
    private $identifier;
    private $origin;
    private $scenario;
    private $scope;
    private $type;
    private $value;

    private const ID_SEP = '-';

    public function __construct(
        string $scope,
        string $value,
        string $type,
        string $origin,
        string $duration,
        string $scenario,
        int    $id = 0)
    {
        $this->scope = $scope;
        $this->value = $value;
        $this->type = $type;
        $this->origin = $origin;
        $this->duration = $duration;
        $this->scenario = $scenario;
        $this->identifier = $id > 0 ? (string)$id : $this->type . self::ID_SEP . $this->origin;
    }

    public function getDuration(): string
    {
        return $this->duration;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Sort the decision array of a cache item, by remediation priorities.
     */
    public static function sortDecisionsByRemediationPriority(array $decisions): array
    {
        // Add priorities.
        $decisionsWithPriorities = [];
        foreach ($decisions as $key => $decision) {
            $decisionsWithPriorities[$key] = self::addRemediationPriority($decision);
        }

        // Sort by priorities.
        /** @var callable $compareFunction */
        $compareFunction = self::class . '::comparePriorities';
        usort($decisionsWithPriorities, $compareFunction);

        return $decisionsWithPriorities;
    }

    /**
     * Add numerical priority allowing easy sorting.
     */
    private static function addRemediationPriority(array $decision): array
    {
        $prio = array_search($decision[0], Constants::ORDERED_REMEDIATIONS);

        // Consider every unknown type as a top priority
        $decision[3] = false !== $prio ? $prio : 0;

        return $decision;
    }

    /**
     * Compare two priorities.
     * @noinspection PhpUnusedPrivateMethodInspection
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private static function comparePriorities(array $a, array $b): int
    {
        $a = $a[3];
        $b = $b[3];
        if ($a == $b) {
            return 0;
        }

        return ($a < $b) ? -1 : 1;
    }

}