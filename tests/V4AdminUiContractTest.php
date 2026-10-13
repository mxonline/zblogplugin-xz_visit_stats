<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

require_once dirname(__DIR__) . '/inc/admin.php';

final class V4AdminUiContractTest extends TestCase
{
    public function testPrimaryViewContractIncludesTrendAndKeepsLegacyViewsAvailable(): void
    {
        $primary = array(
            'overview', 'realtime', 'trend', 'records', 'source',
            'content', 'visitors', 'spider', 'events', 'settings',
        );
        $legacy = array('pages', 'ip', 'environment', 'campaign', 'errors', 'performance');

        $this->assertTrue(function_exists('xz_visit_stats_admin_primary_views'));
        $this->assertTrue(function_exists('xz_visit_stats_admin_legacy_views'));
        $this->assertSame($primary, xz_visit_stats_admin_primary_views());
        $this->assertSame($legacy, xz_visit_stats_admin_legacy_views());

        $main = file_get_contents(dirname(__DIR__) . '/main.php');
        $this->assertIsString($main);
        $this->assertStringContainsString(
            '$views = array_merge(xz_visit_stats_admin_primary_views(), xz_visit_stats_admin_legacy_views());',
            $main
        );
    }

    public function testPrimaryNavigationLinksEveryPrimaryViewAndNoForbiddenModule(): void
    {
        ob_start();
        xz_visit_stats_admin_submenu('overview');
        $html = ob_get_clean();

        foreach (array(
            'overview', 'realtime', 'trend', 'records', 'source',
            'content', 'visitors', 'spider', 'events', 'settings',
        ) as $view) {
            $this->assertStringContainsString('view=' . $view, $html);
        }
        foreach (array('渠道追踪', '热力图', '屏幕录制', '可视化事件编辑器') as $forbiddenModule) {
            $this->assertStringNotContainsString($forbiddenModule, $html);
        }
    }

    public function testExternalTextEscapingRemainsHtmlSafe(): void
    {
        $hostileValue = '" onmouseover="alert(1)<script>alert(2)</script>';

        $this->assertSame(
            '&quot; onmouseover=&quot;alert(1)&lt;script&gt;alert(2)&lt;/script&gt;',
            xz_visit_stats_admin_escape($hostileValue)
        );
    }

    public function testTableOverflowAndUnavailableLifecycleStateHaveDistinctStyles(): void
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/admin.css');
        $main = file_get_contents(dirname(__DIR__) . '/main.php');

        $this->assertIsString($css);
        $this->assertSame(1, preg_match('/^\.xz-table-wrap\s*\{([^}]*)\}/m', $css, $wrapperRule));
        $this->assertStringContainsString('overflow-x: auto', $wrapperRule[1]);
        $this->assertStringContainsString('.xzvs-availability-unavailable', $css);
        $this->assertStringContainsString('.xzvs-availability-partial', $css);
        $this->assertStringContainsString('xzvs-empty-state', $main);
        $this->assertStringContainsString('xzvs-availability ', $main);
    }

    public function testLifecycleAvailabilityUsesClearChineseLabels(): void
    {
        $this->assertTrue(function_exists('xz_visit_stats_admin_availability_label'));
        $this->assertSame('部分可用', xz_visit_stats_admin_availability_label('partial'));
        $this->assertSame('不可用（历史覆盖不足或无生命周期数据）', xz_visit_stats_admin_availability_label('unavailable'));
    }

    public function testTrendTableRendersMetricsAndDoesNotInventMissingDwell(): void
    {
        $this->assertTrue(function_exists('xz_visit_stats_admin_trend_table'));
        ob_start();
        xz_visit_stats_admin_trend_table(array(
            array('bucket' => '<script>', 'pv' => 3, 'uv' => 2, 'ip' => 1, 'session_count' => 2, 'bounce_sessions' => 1, 'average_visitor_dwell_ms' => null),
            array('bucket' => '2026-10-12', 'pv' => 4, 'uv' => 3, 'ip' => 2, 'session_count' => 3, 'bounce_sessions' => 0, 'average_visitor_dwell_ms' => 1250),
        ), 'partial');
        $html = ob_get_clean();
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<th>访客停留</th>', $html);
        $this->assertStringContainsString('不可用', $html);
        $this->assertStringContainsString('1,250 ms', $html);
        $this->assertStringContainsString('部分可用', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testUpdatedAdminStylesheetUsesFreshCacheKey(): void
    {
        $GLOBALS['zbp'] = (object) array('host' => 'https://example.test/');
        ob_start();
        xz_visit_stats_admin_head();
        $html = ob_get_clean();

        $this->assertStringContainsString('admin.css?v=4.0.0-task8', $html);
    }

    public function testEventJsonEncodingGuardsThePhp72OnlySubstitutionFlag(): void
    {
        $main = file_get_contents(dirname(__DIR__) . '/main.php');
        $this->assertStringContainsString("defined('JSON_INVALID_UTF8_SUBSTITUTE')?constant('JSON_INVALID_UTF8_SUBSTITUTE'):0", $main);
        $this->assertStringNotContainsString('|JSON_INVALID_UTF8_SUBSTITUTE', $main);
    }
}
