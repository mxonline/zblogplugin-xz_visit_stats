<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/inc/bot.php';

class BotDetectTest extends TestCase
{
    public function testGoogleBotCanBeDetected(): void
    {
        $result = xz_visit_stats_detect_bot('Mozilla/5.0 Googlebot/2.1');

        $this->assertTrue($result['is_bot']);
        $this->assertSame('Googlebot', $result['name']);
    }

    public function testNormalBrowserIsNotBot(): void
    {
        $result = xz_visit_stats_detect_bot('Mozilla/5.0 Chrome/151.0');

        $this->assertFalse($result['is_bot']);
        $this->assertSame('', $result['name']);
    }
}
