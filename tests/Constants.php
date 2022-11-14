<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Tests;

use CrowdSec\RemediationEngine\CacheStorage\AbstractCache;
use \CrowdSec\RemediationEngine\Constants as RemConstants;

/**
 * Every constant for testing.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */
class Constants
{

    public const TMP_DIR = '/tmp';

    public const IP_V4 = '1.2.3.4';

    public const IP_V4_CACHE_KEY = RemConstants::SCOPE_IP . AbstractCache::CACHE_SEP . self::IP_V4;

    public const IP_V4_2 = '5.6.7.8';

    public const IP_V4_2_CACHE_KEY = RemConstants::SCOPE_IP . AbstractCache::CACHE_SEP . self::IP_V4_2;

    public const IP_V4_RANGE = '24';

    public const IP_V4_RANGE_CACHE_KEY = RemConstants::SCOPE_RANGE . AbstractCache::CACHE_SEP . self::IP_V4 .
                                         AbstractCache::CACHE_SEP .
                                         self::IP_V4_RANGE;

    /*
     * 66051 = intdiv(ip2long(IP_V4),256)
     */
    public const IP_V4_BUCKET_CACHE_KEY = AbstractCache::IPV4_BUCKET_KEY . AbstractCache::CACHE_SEP .
                                          '66051';


}
