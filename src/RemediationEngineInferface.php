<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine;

interface RemediationEngineInferface
{


    public function getIpRemediation(string $ip): string;

}