<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) define('ZBP_PATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/inc/helpers.php';
require_once dirname(__DIR__) . '/inc/query.php';
require_once dirname(__DIR__) . '/inc/install.php';
require_once dirname(__DIR__) . '/inc/stats.php';
require_once dirname(__DIR__) . '/inc/upgrade/migrate.php';
require_once dirname(__DIR__) . '/inc/session.php';
require_once dirname(__DIR__) . '/inc/v4_sessions_query.php';
require_once dirname(__DIR__) . '/inc/maintenance.php';

final class V4MaintenanceRetentionDb
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
        return array();
    }
}

final class V4MaintenanceRetentionTest extends TestCase
{
    protected function setUp(): void
    {
        global $zbp;
        $zbp = (object) array('db' => new V4MaintenanceRetentionDb());
        $GLOBALS['table'] = array('xz_visit_stats_log' => '%pre%xz_visit_stats_log');
    }

    public function testRetentionDeletesV4EventsPagesAndSessionsByConfiguredAge(): void
    {
        $cutoff = 1700000000;
        xz_visit_stats_maintenance_cleanup_v4_retention($cutoff);
        $sql = implode("\n", $GLOBALS['zbp']->db->queries);
        $this->assertStringContainsString('DELETE FROM `zbp_xz_visit_stats_events`', $sql);
        $this->assertStringContainsString('ev_TriggeredAt < 1700000000', $sql);
        $this->assertStringContainsString('DELETE FROM `zbp_xz_visit_stats_session_pages`', $sql);
        $this->assertStringContainsString('sp_EnteredAt < 1700000000', $sql);
        $this->assertStringContainsString('DELETE FROM `zbp_xz_visit_stats_sessions` WHERE se_LastSeenAt < 1700000000', $sql);
    }

    public function testManualRangePurgeAlsoRemovesOnlyRelatedV4Rows(): void
    {
        xz_visit_stats_maintenance_cleanup_v4_range(array('mode' => 'custom', 'start' => 100, 'end' => 200));
        $sql = implode("\n", $GLOBALS['zbp']->db->queries);
        $this->assertStringContainsString('ev_TriggeredAt >= 100 AND ev_TriggeredAt < 200', $sql);
        $this->assertStringContainsString('sp_EnteredAt >= 100 AND sp_EnteredAt < 200', $sql);
        $this->assertStringContainsString('se_StartedAt >= 100 AND se_StartedAt < 200', $sql);
    }
}
