<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) define('ZBP_PATH', __DIR__ . '/');
if (!defined('XZ_VISIT_STATS_V4_EXPORT_TEST_DIR')) define('XZ_VISIT_STATS_V4_EXPORT_TEST_DIR', sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xzvs-v4-export-tests');
require_once dirname(__DIR__) . '/inc/query.php';
require_once dirname(__DIR__) . '/inc/helpers.php';
require_once dirname(__DIR__) . '/inc/install.php';
require_once dirname(__DIR__) . '/inc/query_v2.php';
require_once dirname(__DIR__) . '/inc/v3_query.php';
require_once dirname(__DIR__) . '/inc/rollup.php';
require_once dirname(__DIR__) . '/inc/settings.php';
require_once dirname(__DIR__) . '/inc/upgrade/migrate.php';
require_once dirname(__DIR__) . '/inc/v4_report_filters.php';
if (is_file(dirname(__DIR__) . '/inc/v4_export.php')) require_once dirname(__DIR__) . '/inc/v4_export.php';

if (!function_exists('CheckCSRFTokenValid')) {
    function CheckCSRFTokenValid($name, $method)
    {
        return !empty($GLOBALS['v4_export_csrf_valid']);
    }
}

final class V4ExportDb
{
    public $dbpre = 'zbp_';
    public $queries = array();
    public $insertId = 72;
    public $task = array();
    public $visitQueryCount = 0;
    public $cleanupRows = array();

    public function EscapeString($value)
    {
        return addslashes((string) $value);
    }

    public function GetInsertId()
    {
        return $this->insertId;
    }

    public function Query($sql)
    {
        $this->queries[] = $sql;
        if (strpos($sql, 'SELECT ex_ID,ex_UserID,ex_Status') === 0) {
            return $this->task ? array($this->task) : array();
        }
        if (strpos($sql, 'SELECT ex_ID,ex_Status,ex_FileName,ex_RequestedAt') === 0) return $this->cleanupRows;
        if (strpos($sql, 'SELECT vs_ID,vs_IP') === 0 || strpos($sql, 'SELECT * FROM `zbp_xz_visit_stats_log`') === 0) {
            $this->visitQueryCount++;
            if ($this->visitQueryCount === 1) return array(array(
                'vs_ID' => 91, 'vs_IP' => '192.0.2.9', 'vs_VisitorHash' => str_repeat('a', 64),
                'vs_Url' => 'https://site.test/=SUM(1+1)', 'vs_Path' => '=SUM(1+1)', 'vs_Referer' => '=SUM(1+1)',
                'vs_UserAgent' => '', 'vs_Browser' => '', 'vs_Os' => '', 'vs_Device' => 'desktop',
                'vs_IsBot' => 0, 'vs_BotName' => '', 'vs_StatusCode' => 200, 'vs_DurationMs' => 22,
                'vs_VisitedAt' => 1700000000, 'vs_SourceType' => 'direct', 'vs_SourceDomain' => '', 'vs_UtmCampaign' => '',
            ));
            return array();
        }
        if (strpos($sql, 'UPDATE `zbp_xz_visit_stats_export_tasks`') === 0) {
            if (preg_match("/SET ex_Status='([^']+)'/", $sql, $matches)) $this->task['ex_Status'] = $matches[1];
            if (preg_match('/ex_RowCount=([0-9]+)/', $sql, $matches)) $this->task['ex_RowCount'] = (int) $matches[1];
            if (preg_match("/ex_FileName='([^']*)'/", $sql, $matches)) $this->task['ex_FileName'] = $matches[1];
            if (preg_match("/ex_ErrorCode='([^']*)'/", $sql, $matches)) $this->task['ex_ErrorCode'] = $matches[1];
            return array();
        }
        if (strpos($sql, 'SELECT ex_Status,ex_FileName FROM') === 0) return array($this->task);
        if (strpos($sql, 'INSERT INTO `zbp_xz_visit_stats_export_tasks`') === 0) {
            $this->task = array(
                'ex_ID' => $this->insertId, 'ex_UserID' => 1, 'ex_Status' => 'pending',
                'ex_Filters' => '{"range":"7d","start":"","end":"","device":"desktop","new_returning":"all","country":"","province":"","cursor":""}',
                'ex_FileName' => '', 'ex_RequestedAt' => time(), 'ex_StartedAt' => null, 'ex_FinishedAt' => null,
                'ex_RowCount' => 0, 'ex_ErrorCode' => '', 'ex_UpdatedAt' => time(),
            );
            return array();
        }
        return array();
    }
}

final class V4ExportZbp
{
    public $db;
    public $user;
    public $option = array('ZC_TIME_ZONE' => 'UTC');
    public $config;

    public function Config($name)
    {
        return $this->config;
    }

    public function SaveConfig($name)
    {
    }
}

final class V4ExportTest extends TestCase
{
    protected function setUp(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/inc/v4_export.php');
        global $zbp;
        $GLOBALS['v4_export_csrf_valid'] = true;
        $GLOBALS['v4_admin_actions_csrf_valid'] = true;
        $zbp = new V4ExportZbp();
        $zbp->db = new V4ExportDb();
        $zbp->user = (object) array('ID' => 1, 'Level' => 1);
        $zbp->config = (object) array('ip_mode' => 'masked');
        if (is_dir(XZ_VISIT_STATS_V4_EXPORT_TEST_DIR)) {
            foreach (glob(XZ_VISIT_STATS_V4_EXPORT_TEST_DIR . DIRECTORY_SEPARATOR . '*') as $path) if (is_file($path)) unlink($path);
            rmdir(XZ_VISIT_STATS_V4_EXPORT_TEST_DIR);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir(XZ_VISIT_STATS_V4_EXPORT_TEST_DIR)) {
            foreach (glob(XZ_VISIT_STATS_V4_EXPORT_TEST_DIR . DIRECTORY_SEPARATOR . '*') as $path) if (is_file($path)) unlink($path);
            rmdir(XZ_VISIT_STATS_V4_EXPORT_TEST_DIR);
        }
    }

    public function testExportRequestRequiresTheCurrentRootOwnerAndCsrf(): void
    {
        global $zbp;
        $wrongOwner = xz_visit_stats_v4_export_request(2, array('range' => '7d'));
        $this->assertFalse($wrongOwner['ok']);
        $this->assertFalse(xz_visit_stats_v4_export_request(array(1), array('range' => '7d'))['ok']);
        $this->assertFalse(xz_visit_stats_v4_export_request(1, array('range' => array('7d')))['ok']);
        $GLOBALS['v4_export_csrf_valid'] = false;
        $GLOBALS['v4_admin_actions_csrf_valid'] = false;
        $csrfDenied = xz_visit_stats_v4_export_request(1, array('range' => '7d'));
        $this->assertFalse($csrfDenied['ok']);
        $this->assertStringNotContainsString('INSERT INTO', implode("\n", $zbp->db->queries));
    }

    public function testExportRequestBoundsRangeAndUsesAServerGeneratedPendingTask(): void
    {
        global $zbp;
        $tooWide = xz_visit_stats_v4_export_request(1, array('range' => 'all'));
        $this->assertFalse($tooWide['ok']);
        $invalidCustom = xz_visit_stats_v4_export_request(1, array('range' => 'custom', 'start' => 'not-a-date', 'end' => '2026-01-02T00:00'));
        $this->assertFalse($invalidCustom['ok']);
        $tooLongCustom = xz_visit_stats_v4_export_request(1, array('range' => 'custom', 'start' => '2026-01-01T00:00', 'end' => '2026-04-10T00:00'));
        $this->assertFalse($tooLongCustom['ok']);
        $queued = xz_visit_stats_v4_export_request(1, array('range' => '7d', 'device' => 'desktop'));
        $this->assertTrue($queued['ok']);
        $this->assertSame(72, $queued['task_id']);
        $this->assertStringContainsString("'pending'", implode("\n", $zbp->db->queries));
        $this->assertStringContainsString('device', implode("\n", $zbp->db->queries));
        $this->assertStringContainsString('desktop', implode("\n", $zbp->db->queries));
    }

    public function testExportRunnerCompletesWithFormulaSafeCsvAndOwnerOnlyStatus(): void
    {
        global $zbp;
        xz_visit_stats_v4_export_request(1, array('range' => '7d'));
        $result = xz_visit_stats_v4_export_run(72);
        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['row_count']);
        $this->assertSame('completed', $zbp->db->task['ex_Status']);
        $filePath = XZ_VISIT_STATS_V4_EXPORT_TEST_DIR . DIRECTORY_SEPARATOR . $zbp->db->task['ex_FileName'];
        $this->assertFileExists($filePath);
        $csv = file_get_contents($filePath);
        $this->assertStringContainsString("'=SUM(1+1)", $csv);
        $this->assertStringContainsString('"192.0.2.0"', $csv);
        $this->assertSame(realpath($filePath), xz_visit_stats_v4_export_download_path(1, 72));

        $zbp->user->ID = 2;
        $this->assertNull(xz_visit_stats_v4_export_status(2, 72));
        $this->assertNull(xz_visit_stats_v4_export_download_path(2, 72));
        $zbp->user->ID = 1;
        $this->assertSame('completed', xz_visit_stats_v4_export_status(1, 72)['status']);
        $this->assertSame('failed', xz_visit_stats_v4_export_run(72)['status']);
    }

    public function testAnotherRootCannotRunSomeoneElsesPendingTask(): void
    {
        global $zbp;
        xz_visit_stats_v4_export_request(1, array('range' => '7d'));
        $zbp->user->ID = 2;
        $result = xz_visit_stats_v4_export_run(72);
        $this->assertSame('forbidden', $result['error']);
        $this->assertSame('pending', $zbp->db->task['ex_Status']);
    }

    public function testInvalidStoredFiltersMoveTheTaskToFailedWithoutPublishingAFile(): void
    {
        global $zbp;
        xz_visit_stats_v4_export_request(1, array('range' => '7d'));
        $zbp->db->task['ex_Filters'] = 'invalid-json';
        $result = xz_visit_stats_v4_export_run(72);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('invalid_filters', $zbp->db->task['ex_ErrorCode']);
        $this->assertSame('failed', $zbp->db->task['ex_Status']);
        $this->assertSame(array(), is_dir(XZ_VISIT_STATS_V4_EXPORT_TEST_DIR) ? glob(XZ_VISIT_STATS_V4_EXPORT_TEST_DIR . DIRECTORY_SEPARATOR . '*.csv') : array());
    }

    public function testCleanupOnlyDeletesExactTaskOwnedCsvFiles(): void
    {
        global $zbp;
        mkdir(XZ_VISIT_STATS_V4_EXPORT_TEST_DIR, 0750, true);
        $owned = 'xzvs-v4-export-72-0123456789abcdef.csv';
        $unrelated = 'site-backup.csv';
        file_put_contents(XZ_VISIT_STATS_V4_EXPORT_TEST_DIR . DIRECTORY_SEPARATOR . $owned, 'owned');
        file_put_contents(XZ_VISIT_STATS_V4_EXPORT_TEST_DIR . DIRECTORY_SEPARATOR . $unrelated, 'keep');
        $zbp->db->cleanupRows = array(
            array('ex_ID' => 72, 'ex_Status' => 'completed', 'ex_FileName' => $owned, 'ex_FinishedAt' => 1),
            array('ex_ID' => 73, 'ex_Status' => 'failed', 'ex_FileName' => $unrelated, 'ex_FinishedAt' => 1),
        );
        $result = xz_visit_stats_v4_export_cleanup(86400, 200000);
        $this->assertSame(1, $result['files_removed']);
        $this->assertFileDoesNotExist(XZ_VISIT_STATS_V4_EXPORT_TEST_DIR . DIRECTORY_SEPARATOR . $owned);
        $this->assertFileExists(XZ_VISIT_STATS_V4_EXPORT_TEST_DIR . DIRECTORY_SEPARATOR . $unrelated);
        $this->assertStringContainsString('WHERE ex_ID=73', implode("\n", $zbp->db->queries));
    }
}
