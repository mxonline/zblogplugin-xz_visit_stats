[CmdletBinding()]
param(
    [string]$SiteRoot = 'D:\wwwroot\www.xzhao.net',
    [string]$SiteUrl = 'http://127.0.0.1',
    [string]$PhpPath = 'D:\BtSoft\php\83\php.exe',
    [string]$PluginId = 'xz_visit_stats'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$SiteRoot = (Resolve-Path -LiteralPath $SiteRoot).Path
$PhpPath = (Resolve-Path -LiteralPath $PhpPath).Path
$lifecycle = Join-Path $PSScriptRoot 't5-plugin-lifecycle.php'
$activity = Join-Path $PSScriptRoot 't5-readonly-activity.php'
$marker = [Guid]::NewGuid().ToString('N')
$env:XZVS_ZBLOG_ROOT = $SiteRoot
$env:XZVS_PLUGIN_ID = $PluginId

function Invoke-PluginLifecycle([string]$Action) {
    & $PhpPath $lifecycle $Action
    if ($LASTEXITCODE -ne 0) { throw "Z-Blog plugin lifecycle '$Action' failed." }
}

function Get-MarkerRows {
    $env:XZVS_ACTIVITY_MODE = 'verify_marker'
    $env:XZVS_T5_MARKER = $marker
    try {
        $output = & $PhpPath $activity
        if ($LASTEXITCODE -ne 0) { throw 'Marker SQL verification failed.' }
        return (($output -join "`n") | ConvertFrom-Json)
    }
    finally {
        Remove-Item Env:\XZVS_ACTIVITY_MODE,Env:\XZVS_T5_MARKER -ErrorAction SilentlyContinue
    }
}

try {
    $status = (& $PhpPath $lifecycle 'status' | ConvertFrom-Json)
    if ($LASTEXITCODE -ne 0 -or -not $status.enabled) { throw 'Plugin was not enabled before lifecycle recheck.' }
    Invoke-PluginLifecycle 'disable'
    $url = $SiteUrl.TrimEnd('/') + '/?__xzvs_t5_disabled=' + $marker
    $headers = @{ 'User-Agent' = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) XzVisitStatsT5/' + $marker }
    $response = Invoke-WebRequest -Uri $url -Headers $headers -UseBasicParsing -TimeoutSec 20
    if ([int]$response.StatusCode -ne 200) { throw "Disabled-plugin HTTP request returned $([int]$response.StatusCode)." }
    $rows = Get-MarkerRows
    if ([int]$rows.marker_rows -ne 0) { throw 'The disabled plugin recorded a marked foreground visit.' }
    [pscustomobject]@{status='PASS';http_status=[int]$response.StatusCode;disabled_marker_log_rows=[int]$rows.marker_rows;marker=$marker} | ConvertTo-Json -Compress
}
finally {
    Invoke-PluginLifecycle 'enable'
    $status = (& $PhpPath $lifecycle 'status' | ConvertFrom-Json)
    if ($LASTEXITCODE -ne 0 -or -not $status.enabled) { throw 'Plugin re-enable state could not be verified.' }
    Remove-Item Env:\XZVS_ZBLOG_ROOT,Env:\XZVS_PLUGIN_ID,Env:\XZVS_ACTIVITY_MODE,Env:\XZVS_T5_MARKER -ErrorAction SilentlyContinue
}
