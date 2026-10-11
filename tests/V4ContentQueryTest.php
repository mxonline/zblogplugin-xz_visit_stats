<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/inc/query.php';
require_once dirname(__DIR__) . '/inc/install.php';
require_once dirname(__DIR__) . '/inc/stats.php';
require_once dirname(__DIR__) . '/inc/rollup.php';
require_once dirname(__DIR__) . '/inc/query_v2.php';
require_once dirname(__DIR__) . '/inc/v3_query.php';
require_once dirname(__DIR__) . '/inc/source_stats.php';
require_once dirname(__DIR__) . '/inc/upgrade/migrate.php';
require_once dirname(__DIR__) . '/inc/settings.php';
require_once dirname(__DIR__) . '/inc/session.php';
require_once dirname(__DIR__) . '/inc/v4_report_filters.php';
require_once dirname(__DIR__) . '/inc/v4_sessions_query.php';
require_once dirname(__DIR__) . '/inc/v4_content_query.php';

final class V4ContentQueryDb
{
    public $dbpre = 'zbp_';
    public $queries = array();

    public function ExistTable($table)
    {
        return strpos((string) $table, 'directory_rules') !== false;
    }

    public function Query($sql)
    {
        $this->queries[] = $sql;
        if (strpos($sql, 'SELECT current.sp_LogID AS log_id') === 0) {
            return array(array('log_id' => 9, 'session_id' => 42, 'page_count' => 2, 'visitor_dwell_ms' => null, 'entry_path' => '/start', 'current_path' => '/next'));
        }
        if (strpos($sql, 'SELECT dr_ID,dr_Name,dr_MatchType,dr_Pattern,dr_Action') === 0) {
            return array(array('dr_ID' => 3, 'dr_Name' => '文章', 'dr_MatchType' => 'prefix', 'dr_Pattern' => '/articles/', 'dr_Action' => 'include', 'dr_SortOrder' => 10));
        }
        if (strpos($sql, 'SELECT l.vs_PathKey AS path_key') === 0) {
            return array(array('path_key' => str_repeat('a', 64), 'path' => '/articles/one', 'page_title' => 'One', 'entries' => 4));
        }
        if (strpos($sql, 'SELECT vs_PathKey AS path_key') === 0) {
            return array(
                array('path_key' => str_repeat('a', 64), 'path' => '/articles/one', 'visitor_pv' => 7, 'visitor_uv' => 5, 'visitor_ip' => 4, 'bot_pv' => 1, 'error_4xx' => 0, 'error_5xx' => 0, 'avg_ms' => 23.5, 'last_visit' => 1700000000),
                array('path_key' => str_repeat('b', 64), 'path' => '/about', 'visitor_pv' => 2, 'visitor_uv' => 2, 'visitor_ip' => 2, 'bot_pv' => 0, 'error_4xx' => 0, 'error_5xx' => 0, 'avg_ms' => 8, 'last_visit' => 1699990000),
            );
        }
        if (strpos($sql, ' AS domain, COUNT(*) visits') !== false) {
            return array(array('domain' => 'search.example', 'visits' => 6, 'human_pv' => 5));
        }
        return array();
    }
}

final class V4ContentQueryTest extends TestCase
{
    protected function setUp(): void
    {
        global $zbp;
        $zbp = new stdClass();
        $zbp->db = new V4ContentQueryDb();
        $zbp->option = array('ZC_TIME_ZONE' => 'Asia/Hong_Kong');
        $zbp->host = 'https://site.example/';
    }

    public function testContentReportComposesPagesEntriesDirectoriesAndHosts(): void
    {
        $report = xz_visit_stats_v4_content_report(array('range' => '7d'));

        $this->assertSame('/articles/one', $report['pages'][0]['path']);
        $this->assertSame(4, $report['entry_pages'][0]['entries']);
        $this->assertSame('文章', $report['directories'][0]['directory']);
        $this->assertSame(7, $report['directories'][0]['visitor_pv']);
        $this->assertSame('search.example', $report['hosts'][0]['domain']);
        $this->assertStringContainsString('ORDER BY dr_SortOrder ASC,dr_ID ASC LIMIT 100', implode("\n", $GLOBALS['zbp']->db->queries));
    }

    public function testVisitDetailMasksIdentityAndSeparatesLifecycleFromServerTiming(): void
    {
        $rows = xz_visit_stats_v4_enrich_visit_rows(array(array(
            'vs_ID' => 9,
            'vs_VisitorHash' => str_repeat('f', 64),
            'vs_IP' => '192.0.2.42',
            'vs_GeoCountry' => 'HK',
            'vs_GeoRegion' => 'Hong Kong',
            'vs_SourceType' => 'search',
            'vs_SourceDomain' => 'google.com',
            'vs_Path' => '/next',
            'vs_DurationMs' => 31,
            'vs_Referer' => 'https://www.google.com/search?q=red+shoes',
        )), 'masked');

        $this->assertSame('ffffffff…ffffffff', $rows[0]['visitor_id']);
        $this->assertSame('192.0.2.0', $rows[0]['vs_IP']);
        $this->assertSame('', $rows[0]['country']);
        $this->assertSame('', $rows[0]['region']);
        $this->assertSame(42, $rows[0]['session_id']);
        $this->assertSame('/start', $rows[0]['entry_path']);
        $this->assertSame(2, $rows[0]['session_page_count']);
        $this->assertNull($rows[0]['visitor_dwell_ms']);
        $this->assertSame(31, $rows[0]['server_response_ms']);
        $this->assertSame('Google', $rows[0]['search_engine']);
        $this->assertSame('red shoes', $rows[0]['keyword']);
        $sql = $GLOBALS['zbp']->db->queries[count($GLOBALS['zbp']->db->queries) - 1];
        $this->assertStringContainsString('WHERE current.sp_LogID IN (9)', $sql);
    }

    public function testVisitTableUsesAllowlistedColumnPreferencesAndSessionDrilldown(): void
    {
        $main = file_get_contents(dirname(__DIR__) . '/main.php');
        $this->assertFileExists(dirname(__DIR__) . '/assets/v4_admin.js');
        $script = file_get_contents(dirname(__DIR__) . '/assets/v4_admin.js');

        $this->assertNotFalse($main);
        $this->assertNotFalse($script);
        $this->assertStringContainsString('xzvsColumnChooser()', $main);
        $this->assertStringContainsString('$filters = xz_visit_stats_v3_filters($source);', $main);
        $this->assertStringContainsString('localStorage', $script);
        $this->assertStringContainsString('setItem', $script);
        foreach (array('visit_time', 'visitor_id', 'ip', 'geo', 'visit_type', 'bot_name', 'browser', 'os', 'device', 'source', 'source_domain', 'entry_path', 'current_path', 'search_engine', 'keyword', 'session', 'dwell', 'status', 'server_response') as $column) {
            $this->assertStringContainsString("'" . $column . "'", $script);
            $this->assertStringContainsString('data-v4-column="' . $column . '"', $main);
        }
        $this->assertStringContainsString("'session_id'=>(int)\$r['session_id']", $main);
        $this->assertStringContainsString("array('device', 'new_returning', 'country', 'province')", $main);
        $this->assertStringNotContainsString('visitor_dwell_ms', $script);
        $this->assertStringNotContainsString('vs_VisitorHash', $script);
    }
}
