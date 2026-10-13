[CmdletBinding()]
param(
    [string]$ZBlogRoot = 'D:\wwwroot\www.xzhao.net',
    [string]$PhpPath = 'D:\BtSoft\php\83\php.exe',
    [string]$PluginId = 'xz_visit_stats'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

foreach ($path in @($ZBlogRoot, $PhpPath)) {
    if (-not (Test-Path -LiteralPath $path)) { throw "Required runtime path is unavailable." }
}
$ZBlogRoot = (Resolve-Path -LiteralPath $ZBlogRoot).Path
$PhpPath = (Resolve-Path -LiteralPath $PhpPath).Path
$pluginRoot = Join-Path $ZBlogRoot "zb_users\plugin\$PluginId"
if (-not (Test-Path -LiteralPath (Join-Path $ZBlogRoot 'zb_system\function\c_system_base.php'))) { throw 'Z-Blog bootstrap not found.' }
if (-not (Test-Path -LiteralPath (Join-Path $pluginRoot 'inc\upgrade\migrate.php'))) { throw 'Plugin migration code not found.' }

$tempPhp = Join-Path ([System.IO.Path]::GetTempPath()) "xzvs-t5-migration-$([Guid]::NewGuid().ToString('N')).php"
$stdoutFile = Join-Path ([System.IO.Path]::GetTempPath()) "xzvs-t5-migration-$([Guid]::NewGuid().ToString('N')).out"
$stderrFile = Join-Path ([System.IO.Path]::GetTempPath()) "xzvs-t5-migration-$([Guid]::NewGuid().ToString('N')).err"
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

$phpSource = @'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

$root = getenv('XZVS_ZBLOG_ROOT');
$pluginId = getenv('XZVS_PLUGIN_ID');
$root = rtrim(str_replace('\\', '/', $root), '/') . '/';
$pluginRoot = $root . 'zb_users/plugin/' . $pluginId . '/';
defined('ZBP_SAFEMODE') || define('ZBP_SAFEMODE', true);
defined('ZBP_OBSTART') || define('ZBP_OBSTART', false);
require $root . 'zb_system/function/c_system_base.php';

$zbp = $GLOBALS['zbp'];
$db = $zbp->db;
$originalPrefix = (string) $db->dbpre;
$sourceTable = $originalPrefix . 'xz_visit_stats_log';
if (!method_exists($db, 'ExistTable') || !$db->ExistTable($sourceTable)) {
    fwrite(STDERR, "The existing plugin log table is unavailable.\n");
    exit(3);
}
$expectedV3Columns = array(
    'vs_ID', 'vs_IP', 'vs_VisitorHash', 'vs_Url', 'vs_Path', 'vs_PathKey', 'vs_Referer', 'vs_UserAgent',
    'vs_UaType', 'vs_Browser', 'vs_Os', 'vs_Device', 'vs_IsBot', 'vs_BotName', 'vs_StatusCode', 'vs_DurationMs',
    'vs_SourceType', 'vs_SourceDomain', 'vs_AiSource', 'vs_UtmSource', 'vs_UtmMedium', 'vs_UtmCampaign',
    'vs_UtmContent', 'vs_UtmTerm', 'vs_PageTitle', 'vs_PostID', 'vs_GeoCountry', 'vs_GeoRegion', 'vs_AiCrawler', 'vs_VisitedAt',
);
$sourceColumnRows = (array) $db->Query('SHOW COLUMNS FROM ' . xzvs_t5_quote_ident($sourceTable));
$sourceColumns = array();
foreach ($sourceColumnRows as $columnRow) if (isset($columnRow['Field'])) $sourceColumns[] = (string) $columnRow['Field'];
sort($sourceColumns, SORT_STRING);
$expectedSourceColumns = $expectedV3Columns;
sort($expectedSourceColumns, SORT_STRING);
if ($sourceColumns !== $expectedSourceColumns) {
    fwrite(STDERR, "The real log table no longer matches the v3.0.0 log schema baseline.\n");
    exit(4);
}

require_once $pluginRoot . 'inc/helpers.php';
require_once $pluginRoot . 'inc/install.php';
require_once $pluginRoot . 'inc/rollup.php';
require_once $pluginRoot . 'inc/upgrade/migrate.php';

function xzvs_t5_quote_ident($name)
{
    return '`' . str_replace('`', '``', (string) $name) . '`';
}

function xzvs_t5_sql_value($value)
{
    if (is_int($value)) return (string) $value;
    return "'" . str_replace("'", "''", (string) $value) . "'";
}

function xzvs_t5_require_table($name)
{
    global $db;
    if (!$db->ExistTable($name)) throw new RuntimeException('expected_table_missing');
}

function xzvs_t5_drop_prefix($prefix)
{
    global $db;
    $names = array(
        'xz_visit_stats_log', 'xz_visit_stats_rollup_daily', 'xz_visit_stats_rollup_state',
        'xz_visit_stats_rollup_hourly', 'xz_visit_stats_saved_filters', 'xz_visit_stats_rum',
        'xz_visit_stats_sessions', 'xz_visit_stats_session_pages', 'xz_visit_stats_events',
        'xz_visit_stats_directory_rules', 'xz_visit_stats_export_tasks', 'xz_visit_stats_ip_filters',
    );
    foreach ($names as $name) {
        $table = $prefix . $name;
        if ($db->ExistTable($table)) $db->Query('DROP TABLE ' . xzvs_t5_quote_ident($table));
    }
}

$suffix = bin2hex(random_bytes(4));
$freshPrefix = 'xzvst5' . $suffix . '_f_';
$upgradePrefix = 'xzvst5' . $suffix . '_u_';
$dbVersion = (string) $db->Query('SELECT VERSION() AS mysql_version')[0]['mysql_version'];
$stage = 'isolation_preflight';
$expectedTables = array(
    'xz_visit_stats_log', 'xz_visit_stats_rollup_daily', 'xz_visit_stats_rollup_state',
    'xz_visit_stats_rollup_hourly', 'xz_visit_stats_saved_filters', 'xz_visit_stats_rum',
    'xz_visit_stats_sessions', 'xz_visit_stats_session_pages', 'xz_visit_stats_events',
    'xz_visit_stats_directory_rules', 'xz_visit_stats_export_tasks', 'xz_visit_stats_ip_filters',
);

try {
    foreach (array($freshPrefix, $upgradePrefix) as $prefix) {
        foreach ($expectedTables as $name) {
            if ($db->ExistTable($prefix . $name)) throw new RuntimeException('unique_test_prefix_collision');
        }
    }

    $stage = 'fresh_install';
    $db->dbpre = $freshPrefix;
    xz_visit_stats_install_table();
    xz_visit_stats_upgrade_migrate_to_40();
    foreach ($expectedTables as $name) xzvs_t5_require_table($freshPrefix . $name);

    $stage = 'v3_schema_clone';
    $db->dbpre = $upgradePrefix;
    $testLog = $upgradePrefix . 'xz_visit_stats_log';
    $stage = 'v3_test_table_create';
    $db->Query('CREATE TABLE ' . xzvs_t5_quote_ident($testLog) . ' LIKE ' . xzvs_t5_quote_ident($sourceTable));
    $marker = '/__xzvs_t5_' . $suffix;
    $pathKey = hash('sha256', $marker);
    $stage = 'v3_synthetic_row_insert';
    $v3Row = array(
        'vs_IP' => '192.0.2.77', 'vs_VisitorHash' => hash('sha256', 't5-' . $suffix),
        'vs_Url' => 'https://example.test' . $marker, 'vs_Path' => $marker, 'vs_PathKey' => $pathKey,
        'vs_Referer' => '', 'vs_UserAgent' => '', 'vs_UaType' => '', 'vs_Browser' => '', 'vs_Os' => '', 'vs_Device' => '',
        'vs_IsBot' => 0, 'vs_BotName' => '', 'vs_StatusCode' => 200, 'vs_DurationMs' => 15, 'vs_VisitedAt' => time(),
        'vs_SourceType' => '', 'vs_SourceDomain' => '', 'vs_AiSource' => '', 'vs_UtmSource' => '', 'vs_UtmMedium' => '',
        'vs_UtmCampaign' => '', 'vs_UtmContent' => '', 'vs_UtmTerm' => '', 'vs_PageTitle' => '', 'vs_PostID' => 0,
        'vs_GeoCountry' => '', 'vs_GeoRegion' => '', 'vs_AiCrawler' => '',
    );
    $db->Query('INSERT INTO ' . xzvs_t5_quote_ident($testLog) . ' (' . implode(',', array_map('xzvs_t5_quote_ident', array_keys($v3Row))) . ') VALUES (' . implode(',', array_map('xzvs_t5_sql_value', array_values($v3Row))) . ')');
    $stage = 'v3_synthetic_row_readback';
    $before = (array) $db->Query('SELECT vs_ID,vs_Path,vs_PathKey,vs_VisitedAt FROM ' . xzvs_t5_quote_ident($testLog) . " WHERE vs_Path='" . $marker . "' LIMIT 1");
    if (count($before) !== 1) throw new RuntimeException('synthetic_v3_row_not_created');

    $v4Names = array('xz_visit_stats_sessions', 'xz_visit_stats_session_pages', 'xz_visit_stats_events', 'xz_visit_stats_directory_rules', 'xz_visit_stats_export_tasks', 'xz_visit_stats_ip_filters');
    foreach ($v4Names as $name) {
        if ($db->ExistTable($upgradePrefix . $name)) throw new RuntimeException('v4_table_existed_before_upgrade');
    }

    $stage = 'v3_to_v4_migration';
    xz_visit_stats_upgrade_migrate_to_40();
    foreach ($expectedTables as $name) xzvs_t5_require_table($upgradePrefix . $name);
    $firstMigrationCompatible = xz_visit_stats_upgrade_v4_schema_compatible();
    $stage = 'repeat_migration';
    xz_visit_stats_upgrade_migrate_to_40();
    $stage = 'historical_row_readback';
    $repeatMigrationCompatible = xz_visit_stats_upgrade_v4_schema_compatible();
    $after = (array) $db->Query('SELECT vs_ID,vs_Path,vs_PathKey,vs_VisitedAt FROM ' . xzvs_t5_quote_ident($testLog) . " WHERE vs_Path='" . $marker . "' LIMIT 1");
    if (!$firstMigrationCompatible || !$repeatMigrationCompatible || count($after) !== 1 || $before[0] !== $after[0]) {
        throw new RuntimeException('migration_or_history_preservation_check_failed');
    }

    $report = array(
        'status' => 'PASS',
        'mysql_version' => $dbVersion,
        'fresh_install_tables' => count($expectedTables),
        'v3_log_schema_columns' => count($expectedSourceColumns),
        'v3_log_schema_matches_release_tag' => true,
        'v3_upgrade_tables' => count($expectedTables),
        'repeat_migration' => 'PASS',
        'synthetic_v3_rows_preserved' => 1,
        'historical_rows_touched' => 0,
        'test_tables_removed' => true,
    );
} catch (Throwable $exception) {
    $report = array('status' => 'BLOCKED', 'failed_stage' => $stage, 'failure_code' => preg_match('/^[a-z0-9_]{1,64}$/', $exception->getMessage()) === 1 ? $exception->getMessage() : 'migration_acceptance_failed');
} finally {
    $db->dbpre = $freshPrefix;
    xzvs_t5_drop_prefix($freshPrefix);
    $db->dbpre = $upgradePrefix;
    xzvs_t5_drop_prefix($upgradePrefix);
    $db->dbpre = $originalPrefix;
    $remaining = false;
    foreach (array($freshPrefix, $upgradePrefix) as $prefix) {
        foreach ($expectedTables as $name) if ($db->ExistTable($prefix . $name)) $remaining = true;
    }
    $report['test_tables_removed'] = !$remaining;
    if ($remaining) {
        $report['status'] = 'BLOCKED';
        $report['failed_stage'] = 'test_table_cleanup';
        $report['failure_code'] = 'isolated_test_table_remains';
    }
}

echo json_encode($report, JSON_UNESCAPED_SLASHES) . "\n";
if ($report['status'] !== 'PASS') exit(10);
'@

try {
    [System.IO.File]::WriteAllText($tempPhp, $phpSource, $utf8NoBom)
    $env:XZVS_ZBLOG_ROOT = $ZBlogRoot
    $env:XZVS_PLUGIN_ID = $PluginId
    $proc = Start-Process -FilePath $PhpPath -ArgumentList @($tempPhp) -NoNewWindow -Wait -PassThru -RedirectStandardOutput $stdoutFile -RedirectStandardError $stderrFile
    $output = if (Test-Path -LiteralPath $stdoutFile) { Get-Content -LiteralPath $stdoutFile -Raw } else { '' }
    if (-not [string]::IsNullOrWhiteSpace($output)) { Write-Output $output.Trim() }
    if ($proc.ExitCode -ne 0) {
        $errorText = if (Test-Path -LiteralPath $stderrFile) { Get-Content -LiteralPath $stderrFile -Raw } else { '' }
        if (-not [string]::IsNullOrWhiteSpace($errorText)) { Write-Error 'Isolated migration acceptance did not pass; see PHP output in the current execution result.' }
        exit $proc.ExitCode
    }
} finally {
    Remove-Item -LiteralPath $tempPhp, $stdoutFile, $stderrFile -Force -ErrorAction SilentlyContinue
    Remove-Item Env:\XZVS_ZBLOG_ROOT, Env:\XZVS_PLUGIN_ID -ErrorAction SilentlyContinue
}
