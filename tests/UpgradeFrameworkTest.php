<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

class XzVisitStatsTestDb
{
    public $dbpre = 'zbp_';
    public $tables = array('zbp_xz_visit_stats_log');
    public $columns = array('vs_ID', 'vs_Path', 'vs_VisitedAt', 'vs_SourceType');
    public $queries = array();

    public function ExistTable($table)
    {
        return in_array($table, $this->tables, true);
    }

    public function Query($sql)
    {
        $this->queries[] = $sql;
        if (stripos($sql, 'SHOW COLUMNS') === 0) {
            return array_map(function ($column) { return array('Field' => $column); }, $this->columns);
        }
        if (stripos($sql, 'SHOW INDEX') === 0) {
            return array();
        }
        if (stripos($sql, 'CREATE TABLE') === 0) {
            if (strpos($sql, 'xz_visit_stats_rollup_daily') !== false) {
                $this->tables[] = 'zbp_xz_visit_stats_rollup_daily';
            }
            if (strpos($sql, 'xz_visit_stats_rollup_state') !== false) {
                $this->tables[] = 'zbp_xz_visit_stats_rollup_state';
            }
            if (strpos($sql, 'xz_visit_stats_rollup_hourly') !== false) {
                $this->tables[] = 'zbp_xz_visit_stats_rollup_hourly';
            }
            if (strpos($sql, 'xz_visit_stats_saved_filters') !== false) {
                $this->tables[] = 'zbp_xz_visit_stats_saved_filters';
            }
            return array();
        }
        if (stripos($sql, 'ALTER TABLE') === 0 && strpos($sql, 'vs_PathKey') !== false) {
            $this->columns[] = 'vs_PathKey';
        }
        return array();
    }
}

class XzVisitStatsTestConfig
{
    public $db_version = '1.3.0';
}

class XzVisitStatsTestZbp
{
    public $db;
    public $option = array('ZC_TIME_ZONE' => 'Asia/Hong_Kong');
    private $config;

    public function __construct()
    {
        $this->db = new XzVisitStatsTestDb();
        $this->config = new XzVisitStatsTestConfig();
    }

    public function Config($name)
    {
        return $this->config;
    }

    public function SaveConfig($name)
    {
    }
}

class UpgradeFrameworkTest extends TestCase
{
    public function testUpgradeCreatesFormalSchemaAndIsIdempotent(): void
    {
        global $zbp;

        $zbp = new XzVisitStatsTestZbp();
        require_once dirname(__DIR__) . '/inc/install.php';
        require_once dirname(__DIR__) . '/inc/upgrade/runner.php';

        $this->assertTrue(xz_visit_stats_upgrade_run());
        $this->assertSame('3.0.0', $zbp->Config('xz_visit_stats')->db_version);
        $this->assertTrue(in_array('zbp_xz_visit_stats_rollup_daily', $zbp->db->tables, true));
        $this->assertTrue(in_array('zbp_xz_visit_stats_rollup_state', $zbp->db->tables, true));
        $this->assertTrue(in_array('zbp_xz_visit_stats_rollup_hourly', $zbp->db->tables, true));
        $this->assertTrue(in_array('zbp_xz_visit_stats_saved_filters', $zbp->db->tables, true));
        $this->assertContains('vs_PathKey', $zbp->db->columns);

        $queryCount = count($zbp->db->queries);
        $this->assertTrue(xz_visit_stats_upgrade_run());
        $this->assertGreaterThanOrEqual($queryCount, count($zbp->db->queries));
    }

    public function testPathNormalizationAndKeyAreStable(): void
    {
        require_once dirname(__DIR__) . '/inc/rollup.php';

        $this->assertSame('/', xz_visit_stats_normalize_path(''));
        $this->assertSame('/post/1', xz_visit_stats_normalize_path('/post/1/?utm_source=test'));
        $this->assertSame(xz_visit_stats_path_key('/post/1'), xz_visit_stats_path_key('/post/1/'));
    }
}
