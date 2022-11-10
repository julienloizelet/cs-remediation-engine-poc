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

    public const CAPI_DECISIONS = [
        'new_ip_v4' => [
            'new' => [
                ['duration' => '147h',
                    'origin' => 'CAPI',
                    'scenario' => 'manual',
                    'scope' => 'ip',
                    'type' => 'ban',
                    'value' => self::IP_V4_2],
            ],
            'deleted' => [],
        ],
        'deleted_ip_v4' => [
            'deleted' => [
                ['duration' => '147h',
                    'origin' => 'CAPI',
                    'scenario' => 'manual',
                    'scope' => 'ip',
                    'type' => 'ban',
                    'value' => self::IP_V4_2],
            ],
            'new' => [],
        ],
        'new_ip_v4_range' => [
            'new' => [
                ['duration' => '147h',
                    'origin' => 'CAPI',
                    'scenario' => 'manual',
                    'scope' => 'range',
                    'type' => 'ban',
                    'value' => self::IP_V4 . '/' . self::IP_V4_RANGE],
            ],
            'deleted' => [],
        ],
        'delete_ip_v4_range' => [
            'deleted' => [
                ['duration' => '147h',
                    'origin' => 'CAPI',
                    'scenario' => 'manual',
                    'scope' => 'range',
                    'type' => 'ban',
                    'value' => self::IP_V4 . '/' . self::IP_V4_RANGE],
            ],
            'new' => [],
        ],
        'ip_v4_multiple' => [
            'new' => [
                [
                    'duration' => '147h',
                    'origin' => 'CAPI',
                    'scenario' => 'manual',
                    'scope' => 'ip',
                    'type' => 'ban',
                    'value' => self::IP_V4
                ],
                [
                    'duration' => '147h',
                    'origin' => 'CAPI2',
                    'scenario' => 'manual',
                    'scope' => 'ip',
                    'type' => 'ban',
                    'value' => self::IP_V4
                ],
                [
                    'duration' => '147h',
                    'origin' => 'CAPI3',
                    'scenario' => 'manual',
                    'scope' => 'range',
                    'type' => 'ban',
                    'value' => self::IP_V4 . '/' . self::IP_V4_RANGE
                ],
                [
                    'duration' => '147h',
                    'origin' => 'CAPI4',
                    'scenario' => 'manual',
                    'scope' => 'range',
                    'type' => 'ban',
                    'value' => self::IP_V4_2 . '/' . self::IP_V4_RANGE
                ],
                [
                    'duration' => '147h',
                    'origin' => 'CAPI5',
                    'scenario' => 'manual',
                    'scope' => 'ip',
                    'type' => 'ban',
                    'value' => self::IP_V4_2
                ],

            ],
            'deleted' => [],
        ],
        'ip_v4_multiple_bis' => [
            'deleted' => [
                [
                    'duration' => '147h',
                    'origin' => 'CAPI2',
                    'scenario' => 'manual',
                    'scope' => 'ip',
                    'type' => 'ban',
                    'value' => self::IP_V4
                ],

            ],
            'new' => [
                [
                    'duration' => '147h',
                    'origin' => 'CAPI5',
                    'scenario' => 'manual',
                    'scope' => 'ip',
                    'type' => 'ban',
                    'value' => self::IP_V4_2
                ],
                [
                    'duration' => '147h',
                    'origin' => 'CAPI6',
                    'scenario' => 'manual',
                    'scope' => 'ip',
                    'type' => 'ban',
                    'value' => self::IP_V4_2
                ],
            ],
        ]
    ];

}
