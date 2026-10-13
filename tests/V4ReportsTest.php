<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/inc/query.php';
require_once dirname(__DIR__) . '/inc/install.php';
require_once dirname(__DIR__) . '/inc/upgrade/migrate.php';
require_once dirname(__DIR__) . '/inc/rollup.php';
require_once dirname(__DIR__) . '/inc/query_v2.php';
require_once dirname(__DIR__) . '/inc/v3_query.php';
require_once dirname(__DIR__) . '/inc/v4_report_filters.php';
require_once dirname(__DIR__) . '/inc/session.php';
require_once dirname(__DIR__) . '/inc/v4_sessions_query.php';
require_once dirname(__DIR__) . '/inc/v4_visitors_query.php';
require_once dirname(__DIR__) . '/inc/v4_reports.php';

final class V4ReportsDb
{
    public $dbpre = 'zbp_';
    public $queries = array();

    public function ExistTable($table)
    {
        return true;
    }

    public function Query($sql)
    {
        $this->queries[] = $sql;
        if (strpos($sql, 'COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_VisitorHash END) AS uv') !== false) {
            return array(array('trend_bucket' => '2026-W35', 'pv' => 9, 'uv' => 3, 'ip' => 2, 'human_pv' => 8, 'bot_pv' => 1));
        }
        if (strpos($sql, 'AS new_visitors') !== false) {
            return array(array('visitor_count' => 4, 'new_visitors' => 3, 'returning_visitors' => 1));
        }
        if (strpos($sql, 'GROUP BY trend_bucket') !== false) {
            return array(array('trend_bucket' => '2026-W35', 'session_count' => 3, 'unique_visitors' => 2, 'bounce_sessions' => 1, 'dwell_samples' => 1, 'dwell_sum_ms' => 1250));
        }
        if (strpos($sql, 'COUNT(*) AS session_count') !== false) {
            return array(array('session_count' => 3, 'unique_visitors' => 2, 'completed_sessions' => 2, 'bounce_sessions' => 1, 'dwell_samples' => 1, 'dwell_sum_ms' => 1250));
        }
        if (strpos($sql, 'COUNT(*) AS total_pv') !== false) {
            return array(array('total_pv' => 12, 'visitor_pv' => 10, 'visitor_uv' => 8, 'visitor_ip' => 7, 'bot_pv' => 2, 'error_4xx' => 1, 'error_5xx' => 0, 'not_found' => 1, 'duration_sum' => 450, 'duration_count' => 12, 'avg_ms' => 37.5));
        }
        if (strpos($sql, 'AS source_type') !== false) {
            return array(array('source_type' => 'search', 'total_pv' => 6, 'visitor_pv' => 6, 'bot_pv' => 0, 'visitor_uv' => 5, 'visitor_ip' => 5, 'last_visit' => 100));
        }
        if (strpos($sql, 'MIN(vs_Path) AS path') !== false) {
            return array(array('path_key' => str_repeat('a', 64), 'path' => '/landing', 'visitor_pv' => 5, 'visitor_uv' => 4, 'visitor_ip' => 4, 'bot_pv' => 0, 'error_4xx' => 0, 'error_5xx' => 0, 'last_visit' => 100, 'avg_ms' => 10));
        }
        if (strpos($sql, ' AS name, COUNT(*) AS visits') !== false) {
            return array(array('name' => 'Chrome', 'visits' => 7, 'human_pv' => 7, 'bot_pv' => 0, 'human_uv' => 6));
        }
        return array();
    }
}

final class V4ReportsTest extends TestCase
{
    protected function setUp(): void
    {
        global $zbp;
        $zbp = new stdClass();
        $zbp->db = new V4ReportsDb();
        $zbp->host = 'http://127.0.0.1/';
        $zbp->option = array('ZC_TIME_ZONE' => 'Asia/Shanghai');
    }

    public function testOverviewComposesTrafficAndLifecycleMetricsWithoutServerDurationAsDwell(): void
    {
        $overview = xz_visit_stats_v4_overview(array('range' => 'today'));

        $this->assertSame(10, $overview['kpis']['pv']);
        $this->assertSame(8, $overview['kpis']['uv']);
        $this->assertSame(7, $overview['kpis']['ip']);
        $this->assertSame(3, $overview['kpis']['sessions']);
        $this->assertSame(50.0, $overview['kpis']['bounce_rate']);
        $this->assertSame(1250.0, $overview['kpis']['average_visitor_dwell_ms']);
        $this->assertSame('partial', $overview['availability']['status']);
        $this->assertArrayHasKey('new_returning', $overview);
        $this->assertArrayHasKey('sources', $overview);
        $this->assertArrayHasKey('popular_pages', $overview);
        $this->assertArrayHasKey('entry_pages', $overview);
        $this->assertArrayHasKey('geo', $overview);
        $this->assertArrayHasKey('device', $overview);
        $this->assertSame(4, $overview['new_returning']['visitor_count']);
        $this->assertSame(3, $overview['new_returning']['new_visitors']);
        $this->assertSame(1, $overview['new_returning']['returning_visitors']);
        $this->assertSame('partial', $overview['new_returning']['availability']['status']);
        $this->assertStringNotContainsString('vs_DurationMs', json_encode($overview['kpis']));
    }

    public function testTrendValidatesGrainAndMergesV3TrafficWithV4LifecycleMetrics(): void
    {
        $report = xz_visit_stats_v4_trend_report(array('range' => 'today'), 'not-a-grain');

        $this->assertSame('day', $report['grain']);
        $this->assertNotEmpty($report['rows']);
        $this->assertArrayHasKey('pv', $report['rows'][0]);
        $this->assertArrayHasKey('session_count', $report['rows'][0]);
        $this->assertArrayHasKey('average_visitor_dwell_ms', $report['rows'][0]);
        $this->assertSame('partial', $report['availability']['status']);
        $this->assertStringNotContainsString('OFFSET', implode("\n", $GLOBALS['zbp']->db->queries));
    }

    public function testTrendUsesExactRequestedGrainDistinctCountsAndAllVisibleFilters(): void
    {
        $filters = array('range' => '30d', 'device' => 'mobile', 'new_returning' => 'returning', 'source_type' => 'search', 'province' => 'Hong Kong');
        $report = xz_visit_stats_v4_trend_report($filters, 'week');
        $rows = $report['rows'];
        $this->assertSame('2026-W35', $rows[0]['bucket']);
        $this->assertSame(9, $rows[0]['pv']);
        $this->assertSame(3, $rows[0]['uv']);
        $this->assertSame(2, $rows[0]['ip']);
        $trafficSql = '';
        foreach ($GLOBALS['zbp']->db->queries as $sql) {
            if (strpos($sql, 'COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_VisitorHash END) AS uv') !== false) $trafficSql = $sql;
        }
        $this->assertStringContainsString("DATE_FORMAT(FROM_UNIXTIME(vs_VisitedAt),'%x-W%v')", $trafficSql);
        $this->assertStringContainsString("vs_Device = 'mobile'", $trafficSql);
        $this->assertStringContainsString("vs_GeoRegion = 'Hong Kong'", $trafficSql);
        $this->assertStringContainsString(") = 'search'", $trafficSql);
        $this->assertStringContainsString("se_StartedAt", $trafficSql);
    }

    public function testOverviewRendersRequiredNewReturningSummaryAndTrendHasDimensionControls(): void
    {
        $main = file_get_contents(dirname(__DIR__) . '/main.php');
        $this->assertStringContainsString('新访客（v4 首次观测）</h3>', $main);
        $this->assertStringContainsString('回访访客（v4 已观测）</h3>', $main);
        $trendStart = strpos($main, "if (\$view === 'trend')");
        $overviewStart = strpos($main, "if (\$view === 'overview')");
        $trendForm = substr($main, $trendStart, $overviewStart - $trendStart);
        foreach (array('name="device"', 'name="new_returning"', 'name="source_type"', 'name="source_domain"', 'name="country"', 'name="province"') as $name) {
            $this->assertStringContainsString($name, $trendForm);
        }
    }
}
