<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/inc/helpers.php';
require_once dirname(__DIR__) . '/inc/upgrade/migrate.php';
require_once dirname(__DIR__) . '/inc/session.php';
require_once dirname(__DIR__) . '/inc/page_lifecycle.php';
require_once dirname(__DIR__) . '/inc/events.php';
require_once dirname(__DIR__) . '/inc/ip_filter.php';
require_once dirname(__DIR__) . '/inc/v4_metrics.php';

final class V4FoundationTest extends TestCase
{
    public function testV4MigrationDefinitionsKeepHistoricalDurationOutOfVisitorDwell(): void
    {
        $definitions = xz_visit_stats_v4_schema_definitions();
        $this->assertSame(array('sessions', 'session_pages', 'events', 'directory_rules', 'export_tasks', 'ip_filters'), array_keys($definitions));
        $this->assertStringContainsString('se_DurationMs BIGINT UNSIGNED NULL', $definitions['sessions']['sql']);
        $this->assertStringNotContainsString('vs_DurationMs', implode("\n", array_column($definitions, 'sql')));
        $this->assertSame(array('sp_SessionID', 'sp_Sequence'), $definitions['session_pages']['indexes']['xzvs_page_sequence']);
    }

    public function testSessionKeyIsOpaqueAndTimeoutStartsNewSession(): void
    {
        $first = xz_visit_stats_v4_session_decide(array(), str_repeat('a', 64), str_repeat('b', 64), 1000, 1800);
        $this->assertSame(1, $first['sequence']);
        $this->assertSame(1, $first['page_count']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first['session_key']);
        $this->assertStringNotContainsString(str_repeat('a', 8), $first['session_key']);

        $continued = xz_visit_stats_v4_session_decide($first, str_repeat('a', 64), str_repeat('b', 64), 2000, 1800);
        $this->assertSame($first['session_key'], $continued['session_key']);
        $this->assertSame(2, $continued['sequence']);
        $this->assertSame(2, $continued['page_count']);

        $expired = xz_visit_stats_v4_session_decide($continued, str_repeat('a', 64), str_repeat('b', 64), 4001, 1800);
        $this->assertNotSame($first['session_key'], $expired['session_key']);
        $this->assertSame(1, $expired['sequence']);
    }

    public function testLifecycleOnlyAcceptsTrustworthyClientDwell(): void
    {
        $this->assertSame(1200, xz_visit_stats_v4_lifecycle_duration(array('entered_at' => 1000, 'left_at' => 2200), 3000));
        $this->assertNull(xz_visit_stats_v4_lifecycle_duration(array('entered_at' => 1000), 3000));
        $this->assertNull(xz_visit_stats_v4_lifecycle_duration(array('entered_at' => 2200, 'left_at' => 1000), 3000));
        $this->assertNull(xz_visit_stats_v4_lifecycle_duration(array('entered_at' => 1000, 'left_at' => 86401001), 3000));
        $this->assertNull(xz_visit_stats_v4_lifecycle_duration(array('entered_at' => 1000, 'left_at' => 2200, 'vs_DurationMs' => 1), 3000));
    }

    public function testBounceIsDecidedOnlyWhenTheSessionHasExpired(): void
    {
        $this->assertNull(xz_visit_stats_v4_session_bounce_value(1, 1000, 2799, 1800));
        $this->assertSame(1, xz_visit_stats_v4_session_bounce_value(1, 1000, 2801, 1800));
        $this->assertSame(0, xz_visit_stats_v4_session_bounce_value(2, 1000, 2801, 1800));
    }

    public function testEventPayloadAllowsOnlySafeBoundedParameters(): void
    {
        $event = xz_visit_stats_v4_event_normalize(array(
            'name' => 'checkout_complete',
            'path_key' => str_repeat('c', 64),
            'triggered_at' => 2000,
            'params' => array('plan' => 'pro', 'value' => 12, 'token' => 'secret', 'ip' => '127.0.0.1', 'unknown' => 'drop'),
        ));
        $this->assertSame('checkout_complete', $event['name']);
        $this->assertSame(array('plan' => 'pro', 'value' => 12), $event['params']);
        $this->assertNull(xz_visit_stats_v4_event_normalize(array('name' => 'bad name!', 'path_key' => str_repeat('c', 64), 'triggered_at' => 1)));
    }

    public function testEventOriginMustMatchTheCurrentSite(): void
    {
        $this->assertTrue(xz_visit_stats_v4_event_same_origin('http://127.0.0.1', '127.0.0.1', 'http'));
        $this->assertFalse(xz_visit_stats_v4_event_same_origin('https://127.0.0.1', '127.0.0.1', 'http'));
        $this->assertFalse(xz_visit_stats_v4_event_same_origin('http://example.test', '127.0.0.1', 'http'));
    }

    public function testIpRulesNormalizeEquivalentAddressesAndFailClosed(): void
    {
        $v4 = xz_visit_stats_v4_ip_rule_normalize('2001:0db8:0:0:0:0:0:1');
        $this->assertSame('2001:db8::1', $v4['value']);
        $this->assertSame($v4['hash'], xz_visit_stats_v4_ip_rule_normalize('2001:db8::1')['hash']);
        $this->assertTrue(xz_visit_stats_v4_ip_rule_matches('192.0.2.42', array('192.0.2.0/24')));
        $this->assertTrue(xz_visit_stats_v4_ip_rule_matches('2001:db8::42', array('2001:db8::/64')));
        $this->assertFalse(xz_visit_stats_v4_ip_rule_matches('192.0.2.42', array('not-a-rule')));
    }

    public function testMetricsUseLifecycleDwellAndCompletedBounceOnly(): void
    {
        $metrics = xz_visit_stats_v4_metrics(array(
            array('visitor_hash' => 'a', 'page_count' => 1, 'is_complete' => true, 'duration_ms' => 1000),
            array('visitor_hash' => 'b', 'page_count' => 2, 'is_complete' => true, 'duration_ms' => null),
            array('visitor_hash' => 'c', 'page_count' => 1, 'is_complete' => false, 'duration_ms' => 999999),
        ), array(
            array('visitor_hash' => 'a'), array('visitor_hash' => 'a'), array('visitor_hash' => 'b'),
        ));
        $this->assertSame(3, $metrics['session_count']);
        $this->assertSame(1, $metrics['bounce_sessions']);
        $this->assertSame(50.0, $metrics['bounce_rate']);
        $this->assertSame(1000.0, $metrics['average_dwell_ms']);
        $this->assertSame(3, $metrics['event_total']);
        $this->assertSame(2, $metrics['event_unique_visitors']);
        $this->assertSame(1.5, $metrics['event_average_per_user']);
    }
}
