<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine;

class Decision
{
    private const ID_SEP = '-';
    private $duration;
    private $identifier;
    private $origin;
    private $priority;
    private $scenario;
    private $scope;
    private $type;
    private $value;

    public function __construct(
        AbstractRemediation $remediation,
        string $scope,
        string $value,
        string $type,
        string $origin,
        string $duration,
        string $scenario,
        int $id = 0)
    {
        $this->scope = $scope;
        $this->value = $value;
        $this->origin = $origin;
        $this->duration = $duration;
        $this->scenario = $scenario;

        $orderedRemediation = $remediation->getConfig('ordered_remediations');
        $fallbackRemediation = $remediation->getConfig('fallback_remediation');
        $this->type = in_array($type, $orderedRemediation) ? $type : $fallbackRemediation;
        $this->identifier = $this->handleIdentifier($id, $this);

        // Add numerical priority allowing easy sorting.
        $this->priority = array_search($this->type, $orderedRemediation);
    }

    private function handleIdentifier(int $id, Decision $decision): string
    {
        return $id > 0 ? (string) $id :
            $decision->origin . self::ID_SEP .
            $decision->type . self::ID_SEP .
            $decision->scope . self::ID_SEP .
            $decision->value;
    }

    public function getDuration(): string
    {
        return $this->duration;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getPriority(): int
    {
        return $this->priority;
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

    public function toArray(): array
    {
        return [
            'identifier' => $this->getIdentifier(),
            'scope' => $this->getScope(),
            'value' => $this->getValue(),
            'type' => $this->getType(),
            'priority' => $this->getPriority(),
            'duration' => $this->getDuration(),
        ];
    }
}
