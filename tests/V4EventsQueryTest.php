<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/inc/query.php';
require_once dirname(__DIR__) . '/inc/rollup.php';
require_once dirname(__DIR__) . '/inc/query_v2.php';
require_once dirname(__DIR__) . '/inc/v4_events_query.php';

final class V4EventsQueryDb
{
    public $dbpre = 'zbp_';
    public $queries = array();

    public function Query($sql)
    {
        $this->queries[] = $sql;
        if (stripos($sql, 'EXPLAIN ') === 0) return array(array('key' => 'xzvs_event_name_time', 'type' => 'range'));
        if (strpos($sql, 'COUNT(*) AS event_total') !== false) return array(array('event_total' => 3, 'unique_visitors' => 2));
        if (strpos($sql, 'GROUP BY ev_Name') !== false) return array(array('name' => 'checkout_complete', 'triggers' => 3, 'unique_visitors' => 2));
        if (strpos($sql, 'WHERE ev_ID=9') !== false) return array(array('ev_ID' => 9, 'ev_SessionID' => 7, 'ev_VisitorHash' => str_repeat('d', 64), 'ev_Name' => 'checkout_complete', 'ev_Params' => '{"plan":"pro","value":12,"token":"drop"}', 'ev_PathKey' => str_repeat('e', 64), 'ev_TriggeredAt' => 3000));
        if (strpos($sql, 'ORDER BY ev_TriggeredAt DESC,ev_ID DESC') !== false) return array(array('ev_ID' => 9, 'ev_SessionID' => 7, 'ev_VisitorHash' => str_repeat('d', 64), 'ev_Name' => 'checkout_complete', 'ev_Params' => '{"plan":"pro","value":12,"token":"drop"}', 'ev_PathKey' => str_repeat('e', 64), 'ev_TriggeredAt' => 3000));
        return array();
    }
}

final class V4EventsQueryTest extends TestCase
{
    protected function setUp(): void
    {
        global $zbp;
        $zbp = new stdClass();
        $zbp->db = new V4EventsQueryDb();
        $zbp->option = array('ZC_TIME_ZONE' => 'Asia/Shanghai');
    }

    public function testSummaryCalculatesUniqueVisitorsAndAveragePerVisitor(): void
    {
        $summary = xz_visit_stats_v4_event_summary(array('range' => 'today'));

        $this->assertSame(3, $summary['event_total']);
        $this->assertSame(2, $summary['unique_visitors']);
        $this->assertSame(1.5, $summary['average_per_visitor']);
        $this->assertSame('checkout_complete', $summary['by_name'][0]['name']);
    }

    public function testRowsAndDetailUseKeysetAndStoredAllowlistOnly(): void
    {
        $rows = xz_visit_stats_v4_event_rows(array('range' => 'today'), '3000:10', 500);
        $detail = xz_visit_stats_v4_event_detail(9);
        $sql = implode("\n", $GLOBALS['zbp']->db->queries);

        $this->assertSame(100, $rows['limit']);
        $this->assertSame(9, $rows['rows'][0]['id']);
        $this->assertSame(array('plan' => 'pro', 'value' => 12), $rows['rows'][0]['params']);
        $this->assertSame('dddddddd…dddddddd', $detail['visitor_hash']);
        $this->assertSame(7, $detail['session_id']);
        $this->assertArrayNotHasKey('token', $detail['params']);
        $this->assertStringContainsString('ev_TriggeredAt < 3000', $sql);
        $this->assertStringNotContainsString('OFFSET', $sql);
    }

    public function testExplainHelperUsesNameAndTimeIndex(): void
    {
        $explain = xz_visit_stats_v4_event_explain(array('range' => 'today', 'event_name' => 'checkout_complete'));

        $this->assertSame('xzvs_event_name_time', $explain[0]['key']);
        $this->assertStringStartsWith('EXPLAIN SELECT', $GLOBALS['zbp']->db->queries[count($GLOBALS['zbp']->db->queries) - 1]);
    }
}
