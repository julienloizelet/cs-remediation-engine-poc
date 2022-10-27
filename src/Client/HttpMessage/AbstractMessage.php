<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Client\HttpMessage;


abstract class AbstractMessage
{
    /**
     * @var array
     */
    protected $headers = [];

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
