<?php

use PHPUnit\Framework\TestCase;

if (!defined('ZBP_PATH')) define('ZBP_PATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/inc/helpers.php';
require_once dirname(__DIR__) . '/inc/query.php';
require_once dirname(__DIR__) . '/inc/query_v2.php';
require_once dirname(__DIR__) . '/inc/v3_query.php';
require_once dirname(__DIR__) . '/inc/settings.php';
require_once dirname(__DIR__) . '/inc/upgrade/migrate.php';
require_once dirname(__DIR__) . '/inc/ip_filter.php';
require_once dirname(__DIR__) . '/inc/v4_report_filters.php';
if (is_file(dirname(__DIR__) . '/inc/v4_admin_actions.php')) require_once dirname(__DIR__) . '/inc/v4_admin_actions.php';

if (!function_exists('CheckCSRFTokenValid')) {
    function CheckCSRFTokenValid($name, $method)
    {
        return !empty($GLOBALS['v4_admin_actions_csrf_valid']);
    }
}

final class V4AdminActionsDb
{
    public $dbpre = 'zbp_';
    public $queries = array();
    public $insertId = 31;
    public $duplicateIp = false;
    public $directorySort = 20;

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
        if (strpos($sql, 'SELECT if_ID FROM') === 0 && $this->duplicateIp) {
            return array(array('if_ID' => 7));
        }
        if (strpos($sql, 'SELECT MAX(dr_SortOrder)') === 0) {
            return array(array('sort_order' => $this->directorySort));
        }
        return array();
    }
}

final class V4AdminActionsZbp
{
    public $db;
    public $user;
    public $config;
    public $saved = array();

    public function Config($name)
    {
        return $this->config;
    }

    public function SaveConfig($name)
    {
        $this->saved[] = $name;
    }
}

final class V4AdminActionsTest extends TestCase
{
    protected function setUp(): void
    {
        $this->assertFileExists(dirname(__DIR__) . '/inc/v4_admin_actions.php');
        global $zbp;
        $GLOBALS['v4_admin_actions_csrf_valid'] = true;
        $zbp = new V4AdminActionsZbp();
        $zbp->db = new V4AdminActionsDb();
        $zbp->user = (object) array('ID' => 1, 'Level' => 1);
        $zbp->config = (object) array('retention_days' => 90, 'record_bots' => 1, 'record_googlebot' => 1);
    }

    public function testIpFilterChangesRequireRootAndValidCsrf(): void
    {
        global $zbp;
        $zbp->user->Level = 2;
        $denied = xz_visit_stats_v4_ip_filter_create(array('ip' => '192.0.2.1'));
        $this->assertFalse($denied['ok']);
        $this->assertStringNotContainsString('INSERT INTO', implode("\n", $zbp->db->queries));

        $zbp->user->Level = 1;
        $GLOBALS['v4_admin_actions_csrf_valid'] = false;
        $csrfDenied = xz_visit_stats_v4_ip_filter_create(array('ip' => '192.0.2.1'));
        $this->assertFalse($csrfDenied['ok']);
        $this->assertStringNotContainsString('INSERT INTO', implode("\n", $zbp->db->queries));
    }

    public function testIpFilterRejectsInvalidAndDuplicateNormalizedAddresses(): void
    {
        global $zbp;
        $invalid = xz_visit_stats_v4_ip_filter_create(array('ip' => '192.0.2.999/24'));
        $this->assertFalse($invalid['ok']);
        $this->assertFalse(xz_visit_stats_v4_ip_filter_create(array('ip' => array('192.0.2.1')))['ok']);
        $this->assertStringNotContainsString('INSERT INTO', implode("\n", $zbp->db->queries));

        $zbp->db->duplicateIp = true;
        $normalized = xz_visit_stats_v4_ip_rule_normalize('2001:0db8:0:0:0:0:0:1');
        $duplicate = xz_visit_stats_v4_ip_filter_create(array('ip' => '2001:0db8:0:0:0:0:0:1'));
        $this->assertFalse($duplicate['ok']);
        $this->assertStringContainsString($normalized['hash'], implode("\n", $zbp->db->queries));
        $this->assertStringNotContainsString('INSERT INTO', implode("\n", $zbp->db->queries));
    }

    public function testIpFilterCrudStoresNormalizedRuleAndNumericOwnershipIds(): void
    {
        global $zbp;
        $created = xz_visit_stats_v4_ip_filter_create(array('ip' => '192.0.2.1/24', 'note' => 'office'));
        $this->assertTrue($created['ok']);
        $this->assertSame(31, $created['id']);
        $this->assertStringContainsString("'192.0.2.0/24'", implode("\n", $zbp->db->queries));

        $updated = xz_visit_stats_v4_ip_filter_update(31, array('ip' => '2001:0db8::1', 'note' => 'ipv6', 'enabled' => '0'));
        $this->assertTrue($updated['ok']);
        $this->assertStringContainsString('if_Enabled=0', implode("\n", $zbp->db->queries));
        $this->assertFalse(xz_visit_stats_v4_ip_filter_update('31 OR 1=1', array('ip' => '192.0.2.1'))['ok']);
        $this->assertTrue(xz_visit_stats_v4_ip_filter_delete(31)['ok']);
        $this->assertStringContainsString('DELETE FROM `zbp_xz_visit_stats_ip_filters` WHERE if_ID=31', implode("\n", $zbp->db->queries));
    }

    public function testDirectoryRulesValidateTypeActionAndPatternLength(): void
    {
        global $zbp;
        foreach (array(
            array('name' => 'bad', 'match_type' => 'regex', 'pattern' => '/x', 'action' => 'include'),
            array('name' => 'bad', 'match_type' => 'prefix', 'pattern' => '/x', 'action' => 'delete'),
            array('name' => 'bad', 'match_type' => 'prefix', 'pattern' => str_repeat('x', 2049), 'action' => 'include'),
            array('name' => array('bad'), 'match_type' => 'prefix', 'pattern' => '/x', 'action' => 'include'),
        ) as $input) {
            $this->assertFalse(xz_visit_stats_v4_directory_rule_create($input)['ok']);
        }
        $this->assertStringNotContainsString('INSERT INTO', implode("\n", $zbp->db->queries));
    }

    public function testDirectoryRuleCreateAssignsStableOrderAndUpdateDeleteUseNumericIds(): void
    {
        global $zbp;
        $created = xz_visit_stats_v4_directory_rule_create(array(
            'name' => '文章', 'match_type' => 'prefix', 'pattern' => '/articles/', 'action' => 'include',
        ));
        $this->assertTrue($created['ok']);
        $this->assertSame(31, $created['id']);
        $this->assertStringContainsString('dr_SortOrder,dr_CreatedAt', implode("\n", $zbp->db->queries));
        $this->assertStringContainsString(',21,', implode("\n", $zbp->db->queries));

        $updated = xz_visit_stats_v4_directory_rule_update(31, array(
            'name' => '专栏', 'match_type' => 'contains', 'pattern' => "/column/'", 'action' => 'exclude', 'sort_order' => 4,
        ));
        $this->assertTrue($updated['ok']);
        $this->assertStringContainsString('WHERE dr_ID=31', implode("\n", $zbp->db->queries));
        $this->assertFalse(xz_visit_stats_v4_directory_rule_delete('31 OR 1=1')['ok']);
        $this->assertTrue(xz_visit_stats_v4_directory_rule_delete(31)['ok']);
        $this->assertStringContainsString('DELETE FROM `zbp_xz_visit_stats_directory_rules` WHERE dr_ID=31', implode("\n", $zbp->db->queries));
    }

    public function testSettingsPageWiresRuleAndExportActionsWithoutReplacingTheAdminShell(): void
    {
        $main = file_get_contents(dirname(__DIR__) . '/main.php');
        $this->assertStringContainsString("require_once __DIR__ . '/inc/v4_admin_actions.php'", $main);
        $this->assertStringContainsString("require_once __DIR__ . '/inc/v4_export.php'", $main);
        $this->assertStringContainsString('xz_visit_stats_v4_export_visible_ip', $main);
        foreach (array('ip_filter_create', 'ip_filter_update', 'ip_filter_delete', 'directory_rule_create', 'directory_rule_update', 'directory_rule_delete', 'spider_policy_save', 'export_request', 'export_run', 'download_export') as $action) {
            $this->assertStringContainsString($action, $main);
        }
        foreach (array('IP / CIDR 过滤规则', '目录包含/排除规则', '受控导出', '隐私说明') as $label) {
            $this->assertStringContainsString($label, $main);
        }
        $this->assertStringContainsString('function xz_visit_stats_admin_submenu', file_get_contents(dirname(__DIR__) . '/inc/admin.php'));
    }

    public function testSpiderPolicySaveChangesOnlyTheExplicitPolicySettings(): void
    {
        global $zbp;
        $saved = xz_visit_stats_v4_spider_policy_save(array('record_bots' => '0', 'record_googlebot' => '0'));
        $this->assertTrue($saved['ok']);
        $this->assertSame(0, (int) $zbp->config->record_bots);
        $this->assertSame(0, (int) $zbp->config->record_googlebot);
        $this->assertSame(90, $zbp->config->retention_days);
        $this->assertSame(array('xz_visit_stats'), $zbp->saved);
    }
}
