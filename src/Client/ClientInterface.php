<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Client;

interface ClientInterface
{

    /**
     * Performs an HTTP request (POST, GET, ...) and returns its response body as an array.
     */
    public function request(string $method, string $endpoint, array $parameters = [], array $headers = []): array;

}