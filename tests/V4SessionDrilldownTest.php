<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/inc/query.php';
require_once dirname(__DIR__) . '/inc/rollup.php';
require_once dirname(__DIR__) . '/inc/query_v2.php';
require_once dirname(__DIR__) . '/inc/install.php';
require_once dirname(__DIR__) . '/inc/stats.php';
require_once dirname(__DIR__) . '/inc/session.php';
require_once dirname(__DIR__) . '/inc/v4_sessions_query.php';
require_once dirname(__DIR__) . '/inc/realtime.php';
require_once dirname(__DIR__) . '/inc/upgrade/migrate.php';

final class V4SessionDrilldownDb
{
    public $dbpre = 'zbp_';
    public $queries = array();
    public $sessionIndexes = array(
        'xzvs_session_key' => array('se_SessionKey'),
        'xzvs_session_visitor_time' => array('se_VisitorHash', 'se_LastSeenAt'),
        'xzvs_session_time' => array('se_StartedAt', 'se_LastSeenAt'),
        'xzvs_session_entry_time' => array('se_EntryPathKey', 'se_StartedAt'),
    );

    public function Query($sql)
    {
        $this->queries[] = $sql;
        if (strpos($sql, 'SHOW COLUMNS FROM `zbp_xz_visit_stats_sessions`') === 0) {
            return array_map(function ($field, $type) { return array('Field' => $field, 'Type' => $type); }, array('se_ID', 'se_SessionKey', 'se_VisitorHash', 'se_StartedAt', 'se_LastSeenAt', 'se_DurationMs'), array('bigint unsigned', 'char(64)', 'char(64)', 'bigint unsigned', 'bigint unsigned', 'bigint unsigned'));
        }
        if (strpos($sql, 'SHOW INDEX FROM `zbp_xz_visit_stats_sessions`') === 0) {
            $rows = array();
            foreach ($this->sessionIndexes as $name => $columns) foreach ($columns as $offset => $column) $rows[] = array('Key_name' => $name, 'Column_name' => $column, 'Seq_in_index' => $offset + 1, 'Sub_part' => null);
            return $rows;
        }
        if (preg_match('/^ALTER TABLE `zbp_xz_visit_stats_sessions` ADD INDEX `([^`]+)` \(`([^`]+)`,`([^`]+)`\)$/', $sql, $matches) === 1) {
            $this->sessionIndexes[$matches[1]] = array($matches[2], $matches[3]);
        }
        if (strpos($sql, 'FROM `zbp_xz_visit_stats_sessions` s') !== false) {
            return array(array(
                'session_id' => 42,
                'visitor_hash' => str_repeat('f', 64),
                'started_at' => 1700000000,
                'last_seen_at' => 1700000000,
                'page_count' => 2,
                'visitor_dwell_ms' => 1250,
                'is_bounce' => 0,
                'source_type' => 'search',
                'source_domain' => 'example.test',
                'entry_path' => '/entry',
                'latest_path' => '/latest',
                'browser' => 'Firefox',
                'device' => 'desktop',
                'country' => 'HK',
                'region' => 'Hong Kong',
            ));
        }
        if (strpos($sql, 'WHERE se_ID=42') !== false) {
            return array(array(
                'se_ID' => 42,
                'se_VisitorHash' => str_repeat('f', 64),
                'se_StartedAt' => 1700000000,
                'se_LastSeenAt' => 1700000000,
                'se_PageCount' => 2,
                'se_DurationMs' => 1250,
                'se_IsBounce' => 0,
                'se_SourceType' => 'search',
                'se_SourceDomain' => 'example.test',
                'se_EntryPathKey' => str_repeat('a', 64),
                'se_ExitPathKey' => str_repeat('b', 64),
            ));
        }
        if (strpos($sql, 'FROM `zbp_xz_visit_stats_session_pages`') !== false) {
            $pages = array(
                array('sp_ID' => 1, 'sp_Sequence' => 1, 'sp_Path' => '/entry', 'sp_DurationMs' => null, 'sp_ExitReason' => ''),
                array('sp_ID' => 2, 'sp_Sequence' => 2, 'sp_Path' => '/latest', 'sp_DurationMs' => 1250, 'sp_ExitReason' => 'pagehide'),
            );
            if (strpos($sql, 'ORDER BY sp_Sequence ASC,sp_ID ASC') === false) {
                return array_reverse($pages);
            }
            return $pages;
        }
        return array();
    }

    public function ExistTable($table)
    {
        return strpos((string) $table, 'xz_visit_stats_sessions') !== false;
    }
}

final class V4SessionDrilldownTest extends TestCase
{
    protected function setUp(): void
    {
        global $zbp;
        $zbp = new stdClass();
        $zbp->db = new V4SessionDrilldownDb();
        $zbp->option = array('ZC_TIME_ZONE' => 'Asia/Shanghai');
    }

    public function testRealtimeSessionsHonorFiveFifteenAndThirtyMinuteWindows(): void
    {
        $expectedCutoffs = array(5 => 1699999700, 15 => 1699999100, 30 => 1699998200);
        foreach ($expectedCutoffs as $minutes => $cutoff) {
            $payload = xz_visit_stats_v4_realtime_session_rows($minutes, 50, 1700000000, 'masked');
            $sql = $GLOBALS['zbp']->db->queries[count($GLOBALS['zbp']->db->queries) - 1];

            $this->assertSame($minutes, $payload['window_minutes']);
            $this->assertSame(42, $payload['rows'][0]['id']);
            $this->assertSame('ffffffff…ffffffff', $payload['rows'][0]['visitor_hash']);
            $this->assertSame('/entry', $payload['rows'][0]['entry_path']);
            $this->assertSame('/latest', $payload['rows'][0]['latest_path']);
            $this->assertSame('Firefox', $payload['rows'][0]['browser']);
            $this->assertSame('desktop', $payload['rows'][0]['device']);
            $this->assertSame('', $payload['rows'][0]['country']);
            $this->assertSame('', $payload['rows'][0]['region']);
            $this->assertStringContainsString('se_LastSeenAt >= ' . $cutoff, $sql);
            $this->assertStringContainsString('ORDER BY s.se_LastSeenAt DESC,s.se_ID DESC', $sql);
            $this->assertStringNotContainsString('se_SessionKey', $sql);
            $this->assertStringNotContainsString('vs_DurationMs', $sql);
        }
    }

    public function testSessionDetailPagesAreReturnedInSequenceOrder(): void
    {
        $detail = xz_visit_stats_v4_session_detail(42);
        $pageQuery = $GLOBALS['zbp']->db->queries[count($GLOBALS['zbp']->db->queries) - 1];

        $this->assertSame(array('/entry', '/latest'), array_column($detail['pages'], 'path'));
        $this->assertStringContainsString('ORDER BY sp_Sequence ASC,sp_ID ASC', $pageQuery);
        $this->assertArrayNotHasKey('session_key', $detail);
    }

    public function testExistingSessionTableGetsTheMissingActiveWindowIndex(): void
    {
        $definition = xz_visit_stats_v4_schema_definitions()['sessions'];
        $this->assertArrayHasKey('xzvs_session_active', $definition['indexes']);

        xz_visit_stats_upgrade_create_v4_table($definition);
        $indexChanges = array_values(array_filter($GLOBALS['zbp']->db->queries, function ($sql) {
            return strpos($sql, 'ALTER TABLE `zbp_xz_visit_stats_sessions` ADD INDEX') === 0;
        }));

        $this->assertCount(1, $indexChanges);
        $this->assertStringContainsString('`xzvs_session_active` (`se_LastSeenAt`,`se_ID`)', $indexChanges[0]);

        xz_visit_stats_upgrade_create_v4_table($definition);
        $indexChanges = array_values(array_filter($GLOBALS['zbp']->db->queries, function ($sql) {
            return strpos($sql, 'ALTER TABLE `zbp_xz_visit_stats_sessions` ADD INDEX') === 0;
        }));
        $this->assertCount(1, $indexChanges);
    }
}
