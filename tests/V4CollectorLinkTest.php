<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) {
    define('ZBP_PATH', __DIR__ . '/');
}

require_once dirname(__DIR__) . '/inc/helpers.php';
require_once dirname(__DIR__) . '/inc/session.php';
require_once dirname(__DIR__) . '/inc/collector.php';

final class V4CollectorLinkDb
{
    public $dbpre = 'zbp_';
    public $queries = array();
    public $insertId = 4321;

    public function Query($sql)
    {
        $this->queries[] = $sql;
        if (strpos($sql, 'SELECT * FROM `zbp_xz_visit_stats_sessions`') === 0) {
            return array(array(
                'se_ID' => 7,
                'se_SessionKey' => str_repeat('c', 64),
                'se_VisitorHash' => str_repeat('a', 64),
                'se_StartedAt' => 1699999000,
                'se_LastSeenAt' => 1699999990,
                'se_PageCount' => 1,
            ));
        }

        return array();
    }

    public function GetInsertId()
    {
        return $this->insertId;
    }
}

final class V4CollectorLinkZbp
{
    public $db;
    public $config;

    public function __construct()
    {
        $this->db = new V4CollectorLinkDb();
        $this->config = (object) array('visitor_secret' => str_repeat('s', 32));
    }

    public function Config($name)
    {
        return $this->config;
    }

    public function SaveConfig($name)
    {
    }
}

final class V4CollectorLinkTest extends TestCase
{
    protected function setUp(): void
    {
        global $zbp;
        $zbp = new V4CollectorLinkZbp();
        $_COOKIE = array();
    }

    public function testInsertedPrimaryLogIdIsLinkedToTheSessionPage(): void
    {
        global $zbp;
        $visit = array(
            'visitor_hash' => str_repeat('a', 64),
            'path_key' => str_repeat('b', 64),
            'path' => '/article/next',
            'source_type' => 'direct',
            'source_domain' => '',
            'visited_at' => 1700000000,
        );

        $result = function_exists('xz_visit_stats_v4_persist_log_and_track_page')
            ? xz_visit_stats_v4_persist_log_and_track_page('INSERT INTO zbp_xz_visit_stats_log VALUES (...)', $visit)
            : null;

        $this->assertSame(2, isset($result['sequence']) ? $result['sequence'] : null);
        $pageInsert = '';
        foreach ($zbp->db->queries as $sql) {
            if (strpos($sql, 'INSERT INTO `zbp_xz_visit_stats_session_pages`') === 0) {
                $pageInsert = $sql;
                break;
            }
        }
        $this->assertStringContainsString('VALUES (7,4321,2,', $pageInsert);
    }
}
