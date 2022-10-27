<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Client\RequestHandler;

use CrowdSec\RemediationEngine\Client\HttpMessage\Request;
use CrowdSec\RemediationEngine\Client\HttpMessage\Response;

/**
 * Request handler interface.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */
interface RequestHandlerInterface
{
    /**
     * Performs an HTTP request and returns a response.
     */
    public function handle(Request $request): Response;
}
