<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/inc/query.php';
require_once dirname(__DIR__) . '/inc/rollup.php';
require_once dirname(__DIR__) . '/inc/query_v2.php';
require_once dirname(__DIR__) . '/inc/v3_query.php';
require_once dirname(__DIR__) . '/inc/v4_report_filters.php';
require_once dirname(__DIR__) . '/inc/session.php';
require_once dirname(__DIR__) . '/inc/v4_sessions_query.php';

final class V4SessionsQueryDb
{
    public $dbpre = 'zbp_';
    public $queries = array();

    public function Query($sql)
    {
        $this->queries[] = $sql;
        if (stripos($sql, 'EXPLAIN ') === 0) {
            return array(array('key' => 'xzvs_session_time', 'type' => 'range'));
        }
        if (strpos($sql, 'GROUP BY trend_bucket') !== false) {
            return array(array('trend_bucket' => '2026-08-27', 'session_count' => 3, 'unique_visitors' => 2, 'bounce_sessions' => 1, 'dwell_samples' => 1, 'dwell_sum_ms' => 1250));
        }
        if (strpos($sql, 'COUNT(*) AS session_count') !== false) {
            return array(array(
                'session_count' => 3,
                'unique_visitors' => 2,
                'completed_sessions' => 2,
                'bounce_sessions' => 1,
                'dwell_samples' => 1,
                'dwell_sum_ms' => 1250,
            ));
        }
        if (strpos($sql, 'FROM `zbp_xz_visit_stats_session_pages`') !== false) {
            return array(
                array('sp_ID' => 11, 'sp_Sequence' => 1, 'sp_Path' => '/entry', 'sp_DurationMs' => null, 'sp_ExitReason' => ''),
                array('sp_ID' => 12, 'sp_Sequence' => 2, 'sp_Path' => '/next', 'sp_DurationMs' => 1250, 'sp_ExitReason' => 'pagehide'),
            );
        }
        if (strpos($sql, 'WHERE se_ID=7') !== false) {
            return array(array('se_ID' => 7, 'se_VisitorHash' => str_repeat('a', 64), 'se_StartedAt' => 1000, 'se_LastSeenAt' => 2000, 'se_PageCount' => 2, 'se_DurationMs' => 1250, 'se_IsBounce' => 0, 'se_SourceType' => 'search', 'se_SourceDomain' => 'example.test', 'se_EntryPathKey' => str_repeat('b', 64), 'se_ExitPathKey' => str_repeat('c', 64)));
        }
        if (strpos($sql, 'ORDER BY se_StartedAt DESC,se_ID DESC') !== false) {
            return array(array('se_ID' => 7, 'se_VisitorHash' => str_repeat('a', 64), 'se_StartedAt' => 2000, 'se_LastSeenAt' => 2000, 'se_PageCount' => 1, 'se_DurationMs' => null, 'se_IsBounce' => 1, 'se_SourceType' => 'direct', 'se_SourceDomain' => '', 'se_EntryPathKey' => str_repeat('b', 64), 'se_ExitPathKey' => str_repeat('b', 64)));
        }
        return array();
    }
}

final class V4SessionsQueryTest extends TestCase
{
    protected function setUp(): void
    {
        global $zbp;
        $zbp = new stdClass();
        $zbp->db = new V4SessionsQueryDb();
        $zbp->option = array('ZC_TIME_ZONE' => 'Asia/Shanghai');
    }

    public function testSummarySeparatesCompletedBounceAndLifecycleDwell(): void
    {
        $summary = xz_visit_stats_v4_session_summary(array('range' => 'today'));

        $this->assertSame(3, $summary['session_count']);
        $this->assertSame(2, $summary['unique_visitors']);
        $this->assertSame(1, $summary['bounce_sessions']);
        $this->assertSame(50.0, $summary['bounce_rate']);
        $this->assertSame(1250.0, $summary['average_dwell_ms']);
        $this->assertSame('partial', $summary['availability']['status']);
        $this->assertStringNotContainsString('vs_DurationMs', implode("\n", $GLOBALS['zbp']->db->queries));
    }

    public function testTrendAndRowsUseIndexedDeterministicKeysetContracts(): void
    {
        $trend = xz_visit_stats_v4_session_trend(array('range' => 'today'), 'day');
        $rows = xz_visit_stats_v4_session_rows(array('range' => 'today'), '1720000000:42', 500);
        $sql = implode("\n", $GLOBALS['zbp']->db->queries);

        $this->assertSame('2026-08-27', $trend[0]['bucket']);
        $this->assertSame(100, $rows['limit']);
        $this->assertSame(7, $rows['rows'][0]['id']);
        $this->assertSame('aaaaaaaa…aaaaaaaa', $rows['rows'][0]['visitor_hash']);
        $this->assertStringContainsString('se_StartedAt < 1720000000', $sql);
        $this->assertStringContainsString('ORDER BY se_StartedAt DESC,se_ID DESC', $sql);
        $this->assertStringNotContainsString('OFFSET', $sql);
        $this->assertStringNotContainsString('se_SessionKey', $sql);
    }

    public function testDetailReturnsMaskedHeaderAndOrderedPagesOnly(): void
    {
        $detail = xz_visit_stats_v4_session_detail(7);

        $this->assertSame(7, $detail['id']);
        $this->assertSame('aaaaaaaa…aaaaaaaa', $detail['visitor_hash']);
        $this->assertSame('/entry', $detail['pages'][0]['path']);
        $this->assertSame(1250, $detail['pages'][1]['duration_ms']);
        $this->assertArrayNotHasKey('session_key', $detail);
    }

    public function testExplainHelperKeepsTheRepresentativeTimeIndexVisible(): void
    {
        $explain = xz_visit_stats_v4_session_explain(array('range' => 'today'));

        $this->assertSame('xzvs_session_time', $explain[0]['key']);
        $this->assertStringStartsWith('EXPLAIN SELECT', $GLOBALS['zbp']->db->queries[count($GLOBALS['zbp']->db->queries) - 1]);
    }
}
