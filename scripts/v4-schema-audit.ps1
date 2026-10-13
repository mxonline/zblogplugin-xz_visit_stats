[CmdletBinding()]
param(
    [string]$ZBlogRoot = 'D:\wwwroot\xinzhao_net',
    [string]$PhpPath = 'D:\BtSoft\php\83\php.exe',
    [string]$OutputDir = '',
    [string]$PluginId = 'xz_visit_stats'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

function Resolve-RequiredPath {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Label
    )

    if (-not (Test-Path -LiteralPath $Path)) {
        throw "$Label not found: $Path"
    }

    return (Resolve-Path -LiteralPath $Path).Path
}

$ZBlogRoot = Resolve-RequiredPath -Path $ZBlogRoot -Label 'Z-Blog root'
$PhpPath = Resolve-RequiredPath -Path $PhpPath -Label 'PHP CLI'

$pluginRoot = Join-Path $ZBlogRoot "zb_users\plugin\$PluginId"
$pluginRoot = Resolve-RequiredPath -Path $pluginRoot -Label 'Plugin root'

$pluginXml = Join-Path $pluginRoot 'plugin.xml'
$null = Resolve-RequiredPath -Path $pluginXml -Label 'plugin.xml'

if ([string]::IsNullOrWhiteSpace($OutputDir)) {
    $OutputDir = Join-Path $pluginRoot 'docs\v4.0.0\audit-output'
}

if (-not (Test-Path -LiteralPath $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
}
$OutputDir = (Resolve-Path -LiteralPath $OutputDir).Path

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$outputFile = Join-Path $OutputDir "schema-audit-$stamp.json"
$tempPhp = Join-Path ([System.IO.Path]::GetTempPath()) "xzvs-v4-schema-audit-$([Guid]::NewGuid().ToString('N')).php"
$stdoutFile = Join-Path ([System.IO.Path]::GetTempPath()) "xzvs-v4-schema-audit-$([Guid]::NewGuid().ToString('N')).out"
$stderrFile = Join-Path ([System.IO.Path]::GetTempPath()) "xzvs-v4-schema-audit-$([Guid]::NewGuid().ToString('N')).err"
$utf8NoBom = New-Object System.Text.UTF8Encoding($false)

$phpAudit = @'
<?php
error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

$root = getenv('XZVS_ZBLOG_ROOT');
$pluginId = getenv('XZVS_PLUGIN_ID');
if (!$root || !$pluginId) {
    fwrite(STDERR, "Missing audit environment variables.\n");
    exit(2);
}

$root = rtrim(str_replace('\\', '/', $root), '/') . '/';
$pluginRoot = $root . 'zb_users/plugin/' . $pluginId . '/';
$bootstrap = $root . 'zb_system/function/c_system_base.php';
$pluginXml = $pluginRoot . 'plugin.xml';

if (!is_file($bootstrap) || !is_file($pluginXml)) {
    fwrite(STDERR, "Z-Blog bootstrap or plugin.xml not found.\n");
    exit(3);
}

// Safety boundary: initialize Z-Blog in safe mode so no theme/plugin include,
// activation hook, collector, migration or maintenance code can run.
defined('ZBP_SAFEMODE') || define('ZBP_SAFEMODE', true);
defined('ZBP_OBSTART') || define('ZBP_OBSTART', false);
require $bootstrap;

if (!isset($GLOBALS['zbp']) || !isset($GLOBALS['zbp']->db)) {
    fwrite(STDERR, "Z-Blog database connection was not initialized.\n");
    exit(4);
}

$zbp = $GLOBALS['zbp'];
$queries = array();

function xzvs_ro_query($db, $sql, &$queries)
{
    if (!preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $sql)) {
        throw new RuntimeException('Blocked non-read-only SQL: ' . $sql);
    }
    $queries[] = $sql;
    return $db->Query($sql);
}

function xzvs_quote_ident($name)
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function xzvs_first_value($row)
{
    if (!is_array($row) || empty($row)) {
        return null;
    }
    foreach ($row as $value) {
        return $value;
    }
    return null;
}

$dbType = isset($zbp->db->type) ? (string) $zbp->db->type : '';
$dbClass = get_class($zbp->db);
$prefix = '';
if (isset($zbp->db->dbpre)) {
    $prefix = (string) $zbp->db->dbpre;
} elseif (isset($zbp->option['ZC_MYSQL_PRE'])) {
    $prefix = (string) $zbp->option['ZC_MYSQL_PRE'];
} elseif (isset($zbp->option['ZC_SQLITE_PRE'])) {
    $prefix = (string) $zbp->option['ZC_SQLITE_PRE'];
} elseif (isset($zbp->option['ZC_PGSQL_PRE'])) {
    $prefix = (string) $zbp->option['ZC_PGSQL_PRE'];
}

$runtime = array(
    'php_version' => PHP_VERSION,
    'php_sapi' => PHP_SAPI,
    'zblog_version' => isset($zbp->version) ? (string) $zbp->version : '',
    'database_type' => $dbType,
    'database_class' => $dbClass,
    'table_prefix' => $prefix,
);

if (stripos($dbType, 'mysql') !== false || stripos($dbClass, 'mysql') !== false) {
    $versionRows = xzvs_ro_query($zbp->db, 'SELECT VERSION() AS version', $queries);
    $runtime['database_server_version'] = isset($versionRows[0]['version'])
        ? (string) $versionRows[0]['version']
        : '';
} else {
    $runtime['database_server_version'] = isset($zbp->db->version) ? (string) $zbp->db->version : '';
}

$pluginVersion = '';
$xml = @simplexml_load_file($pluginXml);
if ($xml !== false && isset($xml->version)) {
    $pluginVersion = trim((string) $xml->version);
}
$runtime['plugin_id'] = $pluginId;
$runtime['plugin_version'] = $pluginVersion;

$safeConfigKeys = array(
    'enabled',
    'exclude_admin',
    'record_bots',
    'record_baiduspider',
    'record_googlebot',
    'record_bingbot',
    'record_other_bots',
    'record_referer',
    'record_user_agent',
    'retention_days',
    'auto_cleanup',
    'ip_mode',
    'write_mode',
    'log_alert_count',
    'real_ip_header',
    'realtime_window',
    'enhanced_collect',
    'beacon_enabled',
);

$config = array();
try {
    $pluginConfig = $zbp->Config($pluginId);
    foreach ($safeConfigKeys as $key) {
        if (isset($pluginConfig->$key)) {
            $config[$key] = $pluginConfig->$key;
        }
    }
    $config['trusted_proxies_configured'] = isset($pluginConfig->trusted_proxies)
        && trim((string) $pluginConfig->trusted_proxies) !== '';
    $config['geo_db_path_configured'] = isset($pluginConfig->geo_db_path)
        && trim((string) $pluginConfig->geo_db_path) !== '';
} catch (Throwable $e) {
    $config['config_read_error'] = $e->getMessage();
}

$tables = array();
$tablePrefix = $prefix . $pluginId;

if (stripos($dbType, 'mysql') !== false || stripos($dbClass, 'mysql') !== false) {
    $rows = xzvs_ro_query($zbp->db, 'SHOW TABLES', $queries);
    foreach ((array) $rows as $row) {
        $tableName = (string) xzvs_first_value($row);
        if ($tableName === '' || strpos($tableName, $tablePrefix) !== 0) {
            continue;
        }

        $quoted = xzvs_quote_ident($tableName);
        $columns = xzvs_ro_query($zbp->db, 'SHOW FULL COLUMNS FROM ' . $quoted, $queries);
        $indexes = xzvs_ro_query($zbp->db, 'SHOW INDEX FROM ' . $quoted, $queries);
        $countRows = xzvs_ro_query($zbp->db, 'SELECT COUNT(*) AS row_count FROM ' . $quoted, $queries);
        $rowCount = isset($countRows[0]['row_count']) ? (int) $countRows[0]['row_count'] : null;

        $tables[] = array(
            'name' => $tableName,
            'row_count' => $rowCount,
            'columns' => array_values((array) $columns),
            'indexes' => array_values((array) $indexes),
        );
    }
} else {
    throw new RuntimeException(
        'v4 schema audit currently requires MySQL/MariaDB for exact SHOW COLUMNS/INDEX output. Detected: ' . $dbType
    );
}

$result = array(
    'audit_version' => '1.0',
    'generated_at' => gmdate('c'),
    'mode' => 'read-only',
    'zblog_root' => str_replace('\\', '/', rtrim($root, '/')),
    'plugin_root' => str_replace('\\', '/', rtrim($pluginRoot, '/')),
    'runtime' => $runtime,
    'safe_plugin_config' => $config,
    'tables' => $tables,
    'safety' => array(
        'zbp_safe_mode' => true,
        'plugin_and_theme_loading_disabled' => true,
        'visitor_level_rows_exported' => false,
        'secrets_exported' => false,
        'allowed_sql_prefixes' => array('SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'),
        'query_count' => count($queries),
        'queries_executed' => $queries,
    ),
);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
'@

try {
    [System.IO.File]::WriteAllText($tempPhp, $phpAudit, $utf8NoBom)

    $env:XZVS_ZBLOG_ROOT = $ZBlogRoot
    $env:XZVS_PLUGIN_ID = $PluginId

    $startArgs = @{
        FilePath = $PhpPath
        ArgumentList = @($tempPhp)
        NoNewWindow = $true
        Wait = $true
        PassThru = $true
        RedirectStandardOutput = $stdoutFile
        RedirectStandardError = $stderrFile
    }
    $process = Start-Process @startArgs

    $stderr = if (Test-Path -LiteralPath $stderrFile) {
        Get-Content -LiteralPath $stderrFile -Raw -ErrorAction SilentlyContinue
    } else {
        ''
    }

    if ($process.ExitCode -ne 0) {
        throw "Audit PHP exited with code $($process.ExitCode). $stderr"
    }

    $raw = Get-Content -LiteralPath $stdoutFile -Raw
    if ([string]::IsNullOrWhiteSpace($raw)) {
        throw "Audit PHP returned no JSON. $stderr"
    }

    try {
        $parsed = $raw | ConvertFrom-Json
    } catch {
        throw "Audit PHP returned invalid JSON. STDERR: $stderr`nSTDOUT:`n$raw"
    }

    $normalizedJson = $parsed | ConvertTo-Json -Depth 30
    [System.IO.File]::WriteAllText($outputFile, $normalizedJson, $utf8NoBom)

    Write-Host 'xz_visit_stats v4 schema audit completed.'
    Write-Host 'Database mode: READ ONLY'
    Write-Host "Report: $outputFile"
    Write-Output $outputFile
} finally {
    Remove-Item Env:XZVS_ZBLOG_ROOT -ErrorAction SilentlyContinue
    Remove-Item Env:XZVS_PLUGIN_ID -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $tempPhp -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $stdoutFile -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $stderrFile -Force -ErrorAction SilentlyContinue
}
