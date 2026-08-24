<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

class XzVisitStatsTestSql
{
    public function CreateTable($table, $schema)
    {
        return array('table' => $table, 'schema' => $schema);
    }
}

class XzVisitStatsTestDb
{
    public $dbpre = 'zbp_';
    public $sql;
    public $tables = array();
    public $createCount = 0;

    public function __construct()
    {
        $this->sql = new XzVisitStatsTestSql();
    }

    public function ExistTable($table)
    {
        return in_array($table, $this->tables, true);
    }

    public function QueryMulti($sql)
    {
        $this->tables[] = $sql['table'];
        $this->createCount++;
    }
}

class XzVisitStatsTestConfig
{
    public $db_version = '1.3.0';
}

class XzVisitStatsTestZbp
{
    public $db;
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
    public function testUpgradeMarksVersionAndIsIdempotent(): void
    {
        global $zbp;

        $zbp = new XzVisitStatsTestZbp();
        require_once dirname(__DIR__) . '/inc/upgrade/runner.php';

        $this->assertSame('1.3.0', xz_visit_stats_upgrade_current_version());
        $this->assertTrue(xz_visit_stats_upgrade_run());
        $this->assertSame('2.0.0', $zbp->Config('xz_visit_stats')->db_version);
        $this->assertCount(3, $zbp->db->tables);
        $this->assertSame(3, $zbp->db->createCount);

        $this->assertTrue(xz_visit_stats_upgrade_run());
        $this->assertSame(3, $zbp->db->createCount);
    }
}
