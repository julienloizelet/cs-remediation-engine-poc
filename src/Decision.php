<?php

class Decision
{
    private $scenario;

    private $scope;

    private $value;

    private $origin;

    private $duration;

    private $type;

    private $identifier;

    public function __construct(
        string $scope,
        string $value,
        string $duration,
        string $type,
        string $scenario,
        string $origin,
        int $id = 0)
    {
        $this->scope = $scope;
        $this->value = $value;
        $this->duration = $duration;
        $this->type = $type;
        $this->scenario = $scenario;
        $this->origin = $origin;
        $this->identifier = $id;
    }

    public function getDuration()
    {
        return $this->duration;
    }


    public function formatForCache($duration): array
    {
        return [
            $this->type,  // ex: ban, captcha
            time() + $duration, // expiration timestamp
            $this->identifier,
        ];
    }

}