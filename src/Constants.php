<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine;

/**
 * Every constant of the library are set here.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2020+ CrowdSec
 * @license   MIT License
 */
class Constants
{
    /** @var string The ban remediation */
    public const REMEDIATION_BAN = 'ban';
    /** @var string The bypass remediation */
    public const REMEDIATION_BYPASS = 'bypass';
    /** @var string Cache tag for remediation */
    public const CACHE_TAG_REM = 'remediation';
    /** @var string origin for a decision from this lib */
    public const ORIGIN = 'remediation-engine';
    /** @var int The duration we keep a bad IP in cache */
    public const CACHE_EXPIRATION_FOR_BAD_IP = 120;
    /** @var int The duration we keep a clean IP in cache */
    public const CACHE_EXPIRATION_FOR_CLEAN_IP = 60;
    /** @var string The CrowdSec Ip scope for decisions */
    public const SCOPE_IP = 'ip';
    /** @var string The CrowdSec Range scope for decisions */
    public const SCOPE_RANGE = 'range';
}
