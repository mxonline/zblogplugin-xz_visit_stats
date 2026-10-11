<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/inc/query.php';
require_once dirname(__DIR__) . '/inc/install.php';
require_once dirname(__DIR__) . '/inc/upgrade/migrate.php';
require_once dirname(__DIR__) . '/inc/stats.php';
require_once dirname(__DIR__) . '/inc/rollup.php';
require_once dirname(__DIR__) . '/inc/query_v2.php';
require_once dirname(__DIR__) . '/inc/v3_query.php';
require_once dirname(__DIR__) . '/inc/settings.php';
require_once dirname(__DIR__) . '/inc/v4_events_query.php';
require_once dirname(__DIR__) . '/inc/v4_spider_query.php';

final class V4AdminEventsDb
{
    public $dbpre = 'zbp_';
    public $queries = array();

    public function ExistTable($table)
    {
        return false;
    }

    public function Query($sql)
    {
        $this->queries[] = $sql;
        if (strpos($sql, 'GROUP BY bot_name') !== false) {
            if (strpos($sql, "vs_BotName = 'Googlebot'") !== false) {
                return array(array('bot_name' => 'Googlebot', 'bot_pv' => 7, 'bot_ip' => 2, 'paths' => 4, 'error_4xx' => 1, 'error_5xx' => 0, 'avg_ms' => 16.5, 'last_visit' => 1700000000));
            }
            return array(
                array('bot_name' => 'Googlebot', 'bot_pv' => 7, 'bot_ip' => 2, 'paths' => 4, 'error_4xx' => 1, 'error_5xx' => 0, 'avg_ms' => 16.5, 'last_visit' => 1700000000),
                array('bot_name' => 'Baiduspider', 'bot_pv' => 5, 'bot_ip' => 1, 'paths' => 3, 'error_4xx' => 0, 'error_5xx' => 0, 'avg_ms' => 20, 'last_visit' => 1699990000),
                array('bot_name' => 'Bingbot', 'bot_pv' => 2, 'bot_ip' => 1, 'paths' => 2, 'error_4xx' => 0, 'error_5xx' => 0, 'avg_ms' => 11, 'last_visit' => 1699980000),
            );
        }
        if (strpos($sql, 'ORDER BY vs_ID DESC LIMIT') !== false) {
            return array(array('vs_ID' => 90, 'vs_IP' => '192.0.2.91', 'vs_Path' => '/robots.txt', 'vs_Url' => 'https://site.example/robots.txt', 'vs_BotName' => 'Googlebot', 'vs_StatusCode' => 404, 'vs_DurationMs' => 25, 'vs_VisitedAt' => 1700000000, 'vs_IsBot' => 1));
        }
        return array();
    }
}

final class V4AdminEventsTest extends TestCase
{
    protected function setUp(): void
    {
        global $zbp;
        $zbp = new stdClass();
        $zbp->db = new V4AdminEventsDb();
        $zbp->option = array('ZC_TIME_ZONE' => 'Asia/Hong_Kong');
        $zbp->host = 'https://site.example/';
    }

    public function testSpiderReportPreservesLegacyCrawlerNamesFiltersAndMaskedIps(): void
    {
        $report = xz_visit_stats_v4_spider_report(array('range' => '7d', 'status_code' => '404'), '', 30, 'masked');

        $this->assertSame(array('Googlebot', 'Baiduspider', 'Bingbot'), array_column($report['rows'], 'bot_name'));
        $this->assertSame(7, $report['rows'][0]['bot_pv']);
        $this->assertSame(1700000000, $report['rows'][0]['last_visit']);
        $this->assertSame('192.0.2.0', $report['records'][0]['vs_IP']);
        $this->assertSame('/robots.txt', $report['records'][0]['vs_Path']);
        $this->assertSame('https://site.example/robots.txt', $report['records'][0]['vs_Url']);
        $this->assertSame(404, (int) $report['records'][0]['vs_StatusCode']);
        $sql = implode("\n", $GLOBALS['zbp']->db->queries);
        $this->assertStringContainsString('vs_IsBot = 1', $sql);
        $this->assertStringContainsString('vs_StatusCode = 404', $sql);
        $this->assertStringNotContainsString('OFFSET', $sql);
    }

    public function testSpiderCrawlerAndCursorFiltersAreAppliedToQueries(): void
    {
        $report = xz_visit_stats_v4_spider_report(array('range' => '7d', 'bot_name' => 'Googlebot', 'status_code' => '404'), '100', 30, 'full');
        $sql = implode("\n", $GLOBALS['zbp']->db->queries);

        $this->assertSame('Googlebot', $report['rows'][0]['bot_name']);
        $this->assertSame('192.0.2.91', $report['records'][0]['vs_IP']);
        $this->assertStringContainsString("vs_BotName = 'Googlebot'", $sql);
        $this->assertStringContainsString('vs_ID < 100', $sql);
        $this->assertStringContainsString('vs_StatusCode = 404', $sql);
    }

    public function testAdminExposesEventSummaryKeysetDetailAndSupportedCodeApi(): void
    {
        $main = file_get_contents(dirname(__DIR__) . '/main.php');
        $this->assertFileExists(dirname(__DIR__) . '/assets/v4_events.js');
        $script = file_get_contents(dirname(__DIR__) . '/assets/v4_events.js');

        $this->assertStringContainsString("if (\$view === 'events')", $main);
        $this->assertStringContainsString('xz_visit_stats_v4_event_summary', $main);
        $this->assertStringContainsString('xz_visit_stats_v4_event_rows', $main);
        $this->assertStringContainsString('xz_visit_stats_v4_event_detail', $main);
        $this->assertStringContainsString('window.xzVisitStatsEvent(name, params)', $main);
        $this->assertStringContainsString('clipboard.writeText', $script);
        $this->assertStringContainsString('data-xzvs-copy-event-api', $main);
        $this->assertStringContainsString('session_id', $main);
        $this->assertStringContainsString('path_key', $main);
    }
}
