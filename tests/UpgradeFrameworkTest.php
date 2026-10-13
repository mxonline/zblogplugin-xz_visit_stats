<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

class XzVisitStatsTestDb
{
    public $type = 'mysql';
    public $dbpre = 'zbp_';
    public $tables = array('zbp_xz_visit_stats_log');
    public $columns = array('vs_ID', 'vs_Path', 'vs_PathKey', 'vs_VisitedAt', 'vs_SourceType', 'vs_SourceDomain', 'vs_AiSource', 'vs_UtmCampaign', 'vs_PageTitle', 'vs_PostID', 'vs_GeoCountry', 'vs_AiCrawler');
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
            $v4 = array('sessions' => array('se_SessionKey', 'se_VisitorHash', 'se_StartedAt', 'se_LastSeenAt', 'se_DurationMs'), 'session_pages' => array('sp_SessionID', 'sp_Sequence', 'sp_PathKey', 'sp_DurationMs'), 'events' => array('ev_Name', 'ev_VisitorHash', 'ev_Params', 'ev_TriggeredAt'), 'directory_rules' => array('dr_Name', 'dr_Pattern', 'dr_Enabled'), 'export_tasks' => array('ex_UserID', 'ex_Status', 'ex_Filters', 'ex_RequestedAt'), 'ip_filters' => array('if_RuleType', 'if_Value', 'if_ValueHash', 'if_Enabled'));
            foreach ($v4 as $suffix => $fields) if (strpos($sql, 'xz_visit_stats_' . $suffix) !== false) $columns = $fields;
            $types = array('vs_ID' => 'bigint unsigned', 'vs_Path' => 'varchar(2048)', 'vs_PathKey' => 'char(64)', 'vs_VisitedAt' => 'bigint unsigned', 'vs_SourceType' => 'varchar(24)', 'vs_SourceDomain' => 'varchar(253)', 'vs_AiSource' => 'varchar(32)', 'vs_UtmCampaign' => 'varchar(255)', 'vs_PageTitle' => 'varchar(512)', 'vs_PostID' => 'bigint', 'vs_GeoCountry' => 'varchar(64)', 'vs_AiCrawler' => 'varchar(32)', 'rd_Day' => 'char(10)', 'rd_Dimension' => 'varchar(24)', 'rd_KeyHash' => 'char(64)', 'rd_VisitorPV' => 'bigint', 'rs_Name' => 'varchar(64)', 'rs_Status' => 'varchar(24)', 'rs_Timezone' => 'varchar(128)', 'rs_BackfillCursor' => 'bigint', 'rh_Hour' => 'char(16)', 'rh_Dimension' => 'varchar(24)', 'rh_KeyHash' => 'char(64)', 'rh_VisitorPV' => 'bigint', 'sf_UserID' => 'bigint', 'sf_Name' => 'varchar(128)', 'sf_Filters' => 'text', 'rum_Path' => 'varchar(2048)', 'rum_PathKey' => 'char(64)', 'rum_LCP' => 'decimal(10,2)', 'rum_INP' => 'decimal(10,2)', 'rum_CLS' => 'decimal(10,4)', 'rum_TTFB' => 'decimal(10,2)', 'rum_FCP' => 'decimal(10,2)', 'rum_VisitedAt' => 'bigint', 'se_SessionKey' => 'char(64)', 'se_VisitorHash' => 'char(64)', 'se_StartedAt' => 'bigint', 'se_LastSeenAt' => 'bigint', 'se_DurationMs' => 'bigint', 'sp_SessionID' => 'bigint', 'sp_Sequence' => 'bigint', 'sp_PathKey' => 'char(64)', 'sp_DurationMs' => 'bigint', 'ev_Name' => 'varchar(128)', 'ev_VisitorHash' => 'char(64)', 'ev_Params' => 'text', 'ev_TriggeredAt' => 'bigint', 'dr_Name' => 'varchar(128)', 'dr_Pattern' => 'varchar(2048)', 'dr_Enabled' => 'tinyint', 'ex_UserID' => 'bigint', 'ex_Status' => 'varchar(24)', 'ex_Filters' => 'text', 'ex_RequestedAt' => 'bigint', 'if_RuleType' => 'varchar(8)', 'if_Value' => 'varchar(128)', 'if_ValueHash' => 'char(64)', 'if_Enabled' => 'tinyint');
            return array_map(function ($column) use ($types) { return array('Field' => $column, 'Type' => isset($types[$column]) ? $types[$column] : 'varchar(255)'); }, $columns);
        }
        if (stripos($sql, 'SHOW INDEX') === 0) return array();
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
            foreach (array('sessions', 'session_pages', 'events', 'directory_rules', 'export_tasks', 'ip_filters') as $suffix) {
                if (strpos($sql, 'xz_visit_stats_' . $suffix) !== false) $this->tables[] = 'zbp_xz_visit_stats_' . $suffix;
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
    public function testDatabaseDoubleDeclaresMysqlDriverType(): void
    {
        $db = new XzVisitStatsTestDb();

        $this->assertSame('mysql', $db->type);
    }

    public function testUpgradeCreatesFormalSchemaAndIsIdempotent(): void
    {
        global $zbp;

        $zbp = new XzVisitStatsTestZbp();
        require_once dirname(__DIR__) . '/inc/install.php';
        require_once dirname(__DIR__) . '/inc/upgrade/runner.php';

        $this->assertTrue(xz_visit_stats_upgrade_run());
        $this->assertSame('4.0.0', $zbp->Config('xz_visit_stats')->db_version);
        $this->assertTrue(in_array('zbp_xz_visit_stats_rollup_daily', $zbp->db->tables, true));
        $this->assertTrue(in_array('zbp_xz_visit_stats_rollup_state', $zbp->db->tables, true));
        $this->assertTrue(in_array('zbp_xz_visit_stats_rollup_hourly', $zbp->db->tables, true));
        $this->assertTrue(in_array('zbp_xz_visit_stats_saved_filters', $zbp->db->tables, true));
        $this->assertTrue(in_array('zbp_xz_visit_stats_rum', $zbp->db->tables, true));
        foreach (array('sessions', 'session_pages', 'events', 'directory_rules', 'export_tasks', 'ip_filters') as $suffix) {
            $this->assertTrue(in_array('zbp_xz_visit_stats_' . $suffix, $zbp->db->tables, true));
        }
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
