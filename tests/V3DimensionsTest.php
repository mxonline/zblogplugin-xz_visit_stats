<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/inc/helpers.php';
require_once dirname(__DIR__) . '/inc/settings.php';
require_once dirname(__DIR__) . '/inc/v3_dimensions.php';

class V3DimensionsTest extends TestCase
{
    public function testTrustedProxyUsesOnlyTheFirstUntrustedAddressInTheChain(): void
    {
        $this->assertTrue(xz_visit_stats_ip_in_cidr('192.0.2.10', '192.0.2.0/24'));
        $this->assertTrue(xz_visit_stats_ip_in_cidr('2001:db8::10', '2001:db8::/32'));
        $this->assertFalse(xz_visit_stats_ip_in_cidr('198.51.100.10', '192.0.2.0/24'));
    }

    public function testSourceDimensionsKeepAiSourceAndUtmValues(): void
    {
        $GLOBALS['zbp'] = (object) array('host' => 'https://example.test');
        $result = xz_visit_stats_source_dimensions('https://chatgpt.com/', '/?utm_source=ai&utm_campaign=launch');
        $this->assertSame('campaign', $result['type']);
        $this->assertSame('ChatGPT', $result['ai']);
        $this->assertSame('ai', $result['utm']['source']);
    }
}
