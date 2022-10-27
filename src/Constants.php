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
    /** @var array<string> The list of each known remediation, sorted by priority */
    public const ORDERED_REMEDIATIONS = [self::REMEDIATION_BAN, self::REMEDIATION_CAPTCHA, self::REMEDIATION_BYPASS];
    /** @var string The ban remediation */
    public const REMEDIATION_BAN = 'ban';
    /** @var string The bypass remediation */
    public const REMEDIATION_BYPASS = 'bypass';
    /** @var string The captcha remediation */
    public const REMEDIATION_CAPTCHA = 'captcha';
    /** @var string Cache tag for remediation */
    public const CACHE_TAG_REM = 'remediation';
    /** @var string origin for a decision from this lib */
    public const ORIGIN = 'remediation-engine';

}
