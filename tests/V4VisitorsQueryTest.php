<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/inc/query.php';
require_once dirname(__DIR__) . '/inc/helpers.php';
require_once dirname(__DIR__) . '/inc/install.php';
require_once dirname(__DIR__) . '/inc/stats.php';
require_once dirname(__DIR__) . '/inc/rollup.php';
require_once dirname(__DIR__) . '/inc/query_v2.php';
require_once dirname(__DIR__) . '/inc/v3_query.php';
require_once dirname(__DIR__) . '/inc/settings.php';
require_once dirname(__DIR__) . '/inc/upgrade/migrate.php';
require_once dirname(__DIR__) . '/inc/session.php';
require_once dirname(__DIR__) . '/inc/v4_report_filters.php';
require_once dirname(__DIR__) . '/inc/v4_sessions_query.php';
require_once dirname(__DIR__) . '/inc/v4_visitors_query.php';

final class V4VisitorsQueryDb
{
    public $dbpre = 'zbp_';
    public $queries = array();

    public function ExistTable($table)
    {
        return strpos((string) $table, 'xz_visit_stats_rum') === false;
    }

    public function Query($sql)
    {
        $this->queries[] = $sql;
        if (strpos($sql, 'COUNT(*) AS session_count') !== false) {
            return array(array('session_count' => 3, 'unique_visitors' => 2, 'completed_sessions' => 2, 'bounce_sessions' => 1, 'dwell_samples' => 1, 'dwell_sum_ms' => 1250, 'average_page_depth' => 2.33));
        }
        if (strpos($sql, 'AS new_visitors') !== false) {
            return array(array('visitor_count' => 2, 'new_visitors' => 1, 'returning_visitors' => 1));
        }
        if (strpos($sql, 'AS page_count,COUNT(*) AS sessions') !== false) {
            return array(array('page_count' => 1, 'sessions' => 2, 'visitors' => 2), array('page_count' => 3, 'sessions' => 1, 'visitors' => 1));
        }
        if (strpos($sql, 'GROUP BY vs_IP') !== false) {
            return array(array('ip' => '192.0.2.42', 'geo_country' => 'HK', 'geo_region' => 'Hong Kong', 'visits' => 3, 'pages' => 2, 'first_visit' => 1000, 'last_visit' => 2000, 'avg_ms' => 35, 'not_found' => 0, 'error_4xx' => 0, 'error_5xx' => 0));
        }
        if (strpos($sql, 'GROUP BY vs_') !== false) {
            preg_match('/GROUP BY (vs_[A-Za-z]+)/', $sql, $matches);
            $name = isset($matches[1]) ? substr($matches[1], 3) : 'dimension';
            return array(array('name' => $name === 'GeoRegion' ? 'Hong Kong' : ucfirst(strtolower($name)), 'visits' => 3, 'human_pv' => 3, 'bot_pv' => 0, 'human_uv' => 2));
        }
        return array();
    }
}

final class V4VisitorsQueryConfig extends stdClass
{
    public $ip_mode = 'masked';
}

final class V4VisitorsQueryZbp
{
    public $db;
    public $option = array('ZC_TIME_ZONE' => 'Asia/Hong_Kong');
    private $config;

    public function __construct()
    {
        $this->db = new V4VisitorsQueryDb();
        $this->config = new V4VisitorsQueryConfig();
    }

    public function Config($name)
    {
        return $this->config;
    }

    public function SaveConfig($name)
    {
    }
}

final class V4VisitorsQueryTest extends TestCase
{
    protected function setUp(): void
    {
        global $zbp;
        $zbp = new V4VisitorsQueryZbp();
    }

    public function testVisitorReportKeepsPartialLifecycleAndAppliesCurrentIpPrivacy(): void
    {
        $report = xz_visit_stats_v4_visitor_report(array('range' => '7d'));

        $this->assertSame(1, $report['new_returning']['new_visitors']);
        $this->assertSame(1, $report['new_returning']['returning_visitors']);
        $this->assertSame(1, $report['depth'][0]['page_count']);
        $this->assertSame(2, $report['depth'][0]['sessions']);
        $this->assertSame(2.33, $report['average_page_depth']);
        $this->assertSame('partial', $report['lifecycle_availability']['status']);
        $this->assertSame(1250.0, $report['average_visitor_dwell_ms']);
        $this->assertSame('192.0.2.0', $report['ips'][0]['ip']);
        $this->assertSame(array(), $report['dimensions']['country']);
        $this->assertSame(array(), $report['dimensions']['region']);
        $this->assertSame('unavailable', $report['carrier_availability']);
        $sql = implode("\n", $GLOBALS['zbp']->db->queries);
        $this->assertStringContainsString('se_LastSeenAt<', $sql);
        $this->assertStringContainsString('AVG(se_PageCount) AS average_page_depth', $sql);
        $this->assertStringContainsString('CASE WHEN sessions.se_PageCount >= 20 THEN 20 ELSE sessions.se_PageCount END AS page_count', $sql);
        $this->assertStringNotContainsString('GROUP BY vs_GeoCountry', $sql);
        $this->assertStringNotContainsString('GROUP BY vs_GeoRegion', $sql);
        $this->assertStringNotContainsString('vs_DurationMs AS average_visitor_dwell_ms', $sql);
    }

    public function testGeoFiltersAreAppliedToIndexedVisitDimensions(): void
    {
        $filters = xz_visit_stats_v4_report_filters(array('range' => '7d', 'country' => 'HK', 'province' => 'Hong Kong'));
        $range = xz_visit_stats_v2_range($filters);
        $where = implode(' AND ', xz_visit_stats_v3_where($filters, $range));

        $this->assertStringContainsString("vs_GeoCountry = 'HK'", $where);
        $this->assertStringContainsString("vs_GeoRegion = 'Hong Kong'", $where);
    }

    public function testNewReturningSelectionFiltersSessionAndVisitDimensions(): void
    {
        $filters = xz_visit_stats_v4_report_filters(array('range' => '7d', 'new_returning' => 'new'));
        $range = xz_visit_stats_v4_session_query_range($filters);
        $sessionWhere = implode(' AND ', xz_visit_stats_v4_session_where($filters, $range));
        $visitWhere = implode(' AND ', xz_visit_stats_v3_where($filters, xz_visit_stats_v2_range($filters)));

        $this->assertStringContainsString('NOT EXISTS (SELECT 1 FROM `zbp_xz_visit_stats_sessions`', $sessionWhere);
        $this->assertStringContainsString('vs_VisitorHash IN (SELECT cur.se_VisitorHash FROM `zbp_xz_visit_stats_sessions`', $visitWhere);
        $this->assertStringContainsString('cur.se_StartedAt >=', $visitWhere);
    }

    public function testSessionReportDimensionsMatchTheirRecordedVisitRows(): void
    {
        $filters = xz_visit_stats_v4_report_filters(array('range' => '7d', 'device' => 'mobile', 'country' => 'HK', 'province' => 'Hong Kong'));
        $where = implode(' AND ', xz_visit_stats_v4_session_where($filters, xz_visit_stats_v4_session_query_range($filters)));
        $this->assertStringContainsString('session_pages.sp_SessionID=sessions.se_ID', $where);
        $this->assertStringContainsString('visit_logs.vs_ID=session_pages.sp_LogID', $where);
        $this->assertStringContainsString("vs_Device = 'mobile'", $where);
        $this->assertStringContainsString("vs_GeoCountry = 'HK'", $where);
        $this->assertStringContainsString("vs_GeoRegion = 'Hong Kong'", $where);
    }

    public function testMaskedIpRowsMergePrefixCountsAndClearPreciseGeo(): void
    {
        $rows = xz_visit_stats_v4_mask_visitor_ip_rows(array(
            array('ip' => '192.0.2.3', 'visits' => 2, 'pages' => 1, 'first_visit' => 100, 'last_visit' => 200, 'avg_ms' => 10, 'geo_country' => 'HK', 'geo_region' => 'Central', 'not_found' => 0, 'error_4xx' => 1, 'error_5xx' => 0),
            array('ip' => '192.0.2.88', 'visits' => 1, 'pages' => 1, 'first_visit' => 50, 'last_visit' => 300, 'avg_ms' => 20, 'geo_country' => 'HK', 'geo_region' => 'Kowloon', 'not_found' => 1, 'error_4xx' => 1, 'error_5xx' => 0),
        ), 'masked');

        $this->assertCount(1, $rows);
        $this->assertSame('192.0.2.0', $rows[0]['ip']);
        $this->assertSame(3, $rows[0]['visits']);
        $this->assertEqualsWithDelta(13.333, $rows[0]['avg_ms'], 0.001);
        $this->assertSame('', $rows[0]['geo_country']);
        $this->assertSame('', $rows[0]['geo_region']);
        $this->assertSame(1, $rows[0]['not_found']);
    }
}
