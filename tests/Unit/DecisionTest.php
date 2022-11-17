<?php

declare(strict_types=1);

namespace CrowdSec\RemediationEngine\Tests\Unit;

/**
 * Test for decision.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */

use CrowdSec\RemediationEngine\AbstractRemediation;
use CrowdSec\RemediationEngine\CapiRemediation;
use CrowdSec\RemediationEngine\Constants;
use CrowdSec\RemediationEngine\Decision;
use CrowdSec\RemediationEngine\Tests\Constants as TestConstants;
use PHPUnit\Framework\TestCase;

/**
 * @covers \CrowdSec\RemediationEngine\Decision::toArray
 * @covers \CrowdSec\RemediationEngine\Decision::__construct
 * @covers \CrowdSec\RemediationEngine\Decision::getDuration
 * @covers \CrowdSec\RemediationEngine\Decision::getIdentifier
 * @covers \CrowdSec\RemediationEngine\Decision::getOrigin
 * @covers \CrowdSec\RemediationEngine\Decision::getPriority
 * @covers \CrowdSec\RemediationEngine\Decision::getScope
 * @covers \CrowdSec\RemediationEngine\Decision::getType
 * @covers \CrowdSec\RemediationEngine\Decision::getValue
 * @covers \CrowdSec\RemediationEngine\Decision::handleIdentifier
 */
final class DecisionTest extends TestCase
{
    /**
     * @var AbstractRemediation
     */
    private $remediation;

    /**
     * set up test environment.
     */
    public function setUp(): void
    {
        $this->remediation = $this->getRemediationMock();
    }

    public function testConstruct()
    {
        $this->remediation
        ->method('getConfig')
        ->will(
            $this->returnValueMap(
                [
                    ['ordered_remediations', [], CapiRemediation::ORDERED_REMEDIATIONS],
                    ['fallback_remediation', null, Constants::REMEDIATION_BYPASS],
                ]
            )
        );

        // Test basic
        $decision = new Decision($this->remediation, 'Ip', TestConstants::IP_V4, 'ban', 'Unit', '147h', '');

        $this->assertEquals(
            [
                'identifier' => 'Unit-ban-ip-' . TestConstants::IP_V4,
                'origin' => 'Unit',
                'scope' => 'ip',
                'value' => TestConstants::IP_V4,
                'type' => 'ban',
                'priority' => 0,
                'duration' => '147h',
            ],
            $decision->toArray(),
            'Decision should be as expected'
        );
        // Test with id
        $decision = new Decision($this->remediation, 'Ip', TestConstants::IP_V4, 'ban', 'Unit', '147h', '', 12345);

        $this->assertEquals(
            [
                'identifier' => '12345',
                'origin' => 'Unit',
                'scope' => 'ip',
                'value' => TestConstants::IP_V4,
                'type' => 'ban',
                'priority' => 0,
                'duration' => '147h',
            ],
            $decision->toArray(),
            'Decision should be as expected'
        );

        // Test fallback
        $decision = new Decision($this->remediation, 'Ip', TestConstants::IP_V4, 'unknown', 'Unit', '147h', '');

        $this->assertEquals(
            [
                'identifier' => 'Unit-bypass-ip-' . TestConstants::IP_V4,
                'origin' => 'Unit',
                'scope' => 'ip',
                'value' => TestConstants::IP_V4,
                'type' => 'bypass',
                'priority' => 1,
                'duration' => '147h',
            ],
            $decision->toArray(),
            'Decision should be as expected'
        );
    }

    public function testCustomOrderedRemediations()
    {
        $this->remediation
            ->method('getConfig')
            ->will(
                $this->returnValueMap(
                    [
                        ['ordered_remediations', [], ['ban', 'captcha', 'bypass']],
                        ['fallback_remediation', null, Constants::REMEDIATION_BYPASS],
                    ]
                )
            );

        // Test basic
        $decision = new Decision($this->remediation, 'Ip', TestConstants::IP_V4, 'ban', 'Unit', '147h', '');

        $this->assertEquals(
            [
                'identifier' => 'Unit-ban-ip-' . TestConstants::IP_V4,
                'origin' => 'Unit',
                'scope' => 'ip',
                'value' => TestConstants::IP_V4,
                'type' => 'ban',
                'priority' => 0,
                'duration' => '147h',
            ],
            $decision->toArray(),
            'Decision should be as expected'
        );

        // Test fallback
        $decision = new Decision($this->remediation, 'Ip', TestConstants::IP_V4, 'unknown', 'Unit', '147h', '');

        $this->assertEquals(
            [
                'identifier' => 'Unit-bypass-ip-' . TestConstants::IP_V4,
                'origin' => 'Unit',
                'scope' => 'ip',
                'value' => TestConstants::IP_V4,
                'type' => 'bypass',
                'priority' => 2,
                'duration' => '147h',
            ],
            $decision->toArray(),
            'Decision should be as expected'
        );

        // Test priority
        $decision = new Decision($this->remediation, 'Ip', TestConstants::IP_V4, 'captcha', 'Unit', '147h', '');

        $this->assertEquals(
            [
                'identifier' => 'Unit-captcha-ip-' . TestConstants::IP_V4,
                'origin' => 'Unit',
                'scope' => 'ip',
                'value' => TestConstants::IP_V4,
                'type' => 'captcha',
                'priority' => 1,
                'duration' => '147h',
            ],
            $decision->toArray(),
            'Decision should be as expected'
        );
    }

    protected function getRemediationMock()
    {
        return $this->getMockBuilder('CrowdSec\RemediationEngine\CapiRemediation')
            ->disableOriginalConstructor()
            ->onlyMethods(['getConfig'])
            ->getMock();
    }
}
