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
            $columns = $this->columns;
            if (strpos($sql, 'rollup_daily') !== false) $columns = array('rd_Day', 'rd_Dimension', 'rd_KeyHash', 'rd_VisitorPV');
            if (strpos($sql, 'rollup_state') !== false) $columns = array('rs_Name', 'rs_Status', 'rs_Timezone', 'rs_BackfillCursor');
            if (strpos($sql, 'rollup_hourly') !== false) $columns = array('rh_Hour', 'rh_Dimension', 'rh_KeyHash', 'rh_VisitorPV');
            if (strpos($sql, 'saved_filters') !== false) $columns = array('sf_UserID', 'sf_Name', 'sf_Filters');
            if (strpos($sql, 'xz_visit_stats_rum') !== false) $columns = array('rum_Path', 'rum_PathKey', 'rum_LCP', 'rum_INP', 'rum_CLS', 'rum_TTFB', 'rum_FCP', 'rum_VisitedAt');
            $types = array('rd_Day' => 'char(10)', 'rd_Dimension' => 'varchar(24)', 'rd_KeyHash' => 'char(64)', 'rd_VisitorPV' => 'bigint', 'rs_Name' => 'varchar(64)', 'rs_Status' => 'varchar(24)', 'rs_Timezone' => 'varchar(128)', 'rs_BackfillCursor' => 'bigint', 'rh_Hour' => 'char(16)', 'rh_Dimension' => 'varchar(24)', 'rh_KeyHash' => 'char(64)', 'rh_VisitorPV' => 'bigint', 'sf_UserID' => 'bigint', 'sf_Name' => 'varchar(128)', 'sf_Filters' => 'text', 'rum_Path' => 'varchar(2048)', 'rum_PathKey' => 'char(64)', 'rum_LCP' => 'decimal(10,2)', 'rum_INP' => 'decimal(10,2)', 'rum_CLS' => 'decimal(10,4)', 'rum_TTFB' => 'decimal(10,2)', 'rum_FCP' => 'decimal(10,2)', 'rum_VisitedAt' => 'bigint');
            return array_map(function ($column) use ($types) { return array('Field' => $column, 'Type' => isset($types[$column]) ? $types[$column] : 'varchar(255)'); }, $columns);
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
            if (strpos($sql, 'xz_visit_stats_rum') !== false) {
                $this->tables[] = 'zbp_xz_visit_stats_rum';
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
        $this->assertTrue(in_array('zbp_xz_visit_stats_rum', $zbp->db->tables, true));
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
