[CmdletBinding()]
param(
    [string]$SiteRoot = 'D:\wwwroot\www.xzhao.net',
    [string]$SiteUrl = 'http://127.0.0.1',
    [string]$PhpPath = 'D:\BtSoft\php\83\php.exe',
    [string]$PluginId = 'xz_visit_stats',
    [int]$DwellMilliseconds = 7000
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$SiteRoot = (Resolve-Path -LiteralPath $SiteRoot).Path
$PhpPath = (Resolve-Path -LiteralPath $PhpPath).Path
$marker = [Guid]::NewGuid().ToString('N')
$pluginUrl = $SiteUrl.TrimEnd('/') + "/zb_users/plugin/$PluginId"
$configScript = Join-Path $PSScriptRoot 't5-beacon-config.php'
$markerScript = Join-Path $PSScriptRoot 't5-marker-data.php'
$env:XZVS_ZBLOG_ROOT = $SiteRoot
$env:XZVS_PLUGIN_ID = $PluginId
$env:XZVS_T5_MARKER = $marker

function Invoke-JsonPhp([string]$Path, [string[]]$Arguments = @()) {
    $result = & $PhpPath $Path @Arguments
    $exitCode = $LASTEXITCODE
    if ($exitCode -ne 0) { throw "PHP helper failed with exit code $exitCode." }
    $text = ($result -join "`n").Trim()
    if ([string]::IsNullOrWhiteSpace($text)) { throw 'PHP helper returned no JSON.' }
    return ($text | ConvertFrom-Json)
}

function Invoke-MarkerData([string]$Mode) {
    $env:XZVS_T5_MARKER_MODE = $Mode
    try { return Invoke-JsonPhp $markerScript }
    finally { Remove-Item Env:\XZVS_T5_MARKER_MODE -ErrorAction SilentlyContinue }
}

function Assert-HttpStatus($Response, [int]$Expected, [string]$Label) {
    if ([int]$Response.StatusCode -ne $Expected) { throw "$Label returned HTTP $([int]$Response.StatusCode); expected $Expected." }
}

$beaconEnabled = $false
$clientSession = $null
$pageLoadedAt = 0L
$result = $null
$after = $null
try {
    $enabled = Invoke-JsonPhp $configScript @('1', '0')
    if ($enabled.status -ne 'PASS' -or [int]$enabled.beacon_enabled -ne 1) { throw 'Beacon was not enabled for the isolated lifecycle probe.' }
    $beaconEnabled = $true

    $clientSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $headers = @{ 'User-Agent' = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) XzVisitStatsT5/' + $marker }
    $query = '?__xzvs_t5=' + $marker
    $first = Invoke-WebRequest -Uri ($SiteUrl.TrimEnd('/') + '/' + $query) -Headers $headers -WebSession $clientSession -UseBasicParsing -TimeoutSec 25
    Assert-HttpStatus $first 200 'First marked homepage visit'
    $cookie = $null
    foreach ($candidateCookie in $clientSession.Cookies.GetCookies([Uri]$SiteUrl)) {
        if ($candidateCookie.Name -eq 'xzvs_sk') { $cookie = $candidateCookie; break }
    }
    if ($null -eq $cookie -or $cookie.Value -notmatch '^[a-f0-9]{64}$') { throw 'The visit response did not issue the expected session cookie.' }
    if ($first.Content -notmatch '(?i)assets/rum\.js') { throw 'The enabled beacon script was not present in the page response.' }

    $pageLoadedAt = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds()
    Start-Sleep -Milliseconds $DwellMilliseconds
    $leftAt = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds()

    $lifecyclePayload = @{
        path = '/'
        language = 'en-US'
        screen = '1920x1080'
        viewport = '1280x900'
        lcp = 1234
        inp = 42
        cls = 0.125
        ttfb = 34
        fcp = 567
        lifecycle = @{
            session_key = $cookie.Value
            path = '/'
            entered_at = $pageLoadedAt
            left_at = $leftAt
            exit_reason = 'pagehide'
        }
    } | ConvertTo-Json -Depth 5 -Compress
    $origin = ([Uri]$SiteUrl).GetLeftPart([System.UriPartial]::Authority)
    $postHeaders = @{ Origin = $origin; 'User-Agent' = $headers['User-Agent'] }
    $rumResponse = Invoke-WebRequest -Uri ($pluginUrl + '/rum.php') -Method Post -Headers $postHeaders -ContentType 'application/json' -Body $lifecyclePayload -WebSession $clientSession -UseBasicParsing -TimeoutSec 25
    Assert-HttpStatus $rumResponse 204 'RUM lifecycle POST'

    $second = Invoke-WebRequest -Uri ($SiteUrl.TrimEnd('/') + '/' + $query + '&__xzvs_t5_page=second') -Headers $headers -WebSession $clientSession -UseBasicParsing -TimeoutSec 25
    Assert-HttpStatus $second 200 'Second marked homepage visit'

    $eventPayload = @{
        name = 'T5_' + $marker
        path = '/'
        triggered_at = [DateTimeOffset]::UtcNow.ToUnixTimeMilliseconds()
        session_key = $cookie.Value
        params = @{ category = 'acceptance'; action = 'lifecycle'; label = 'safe-value'; cookie = 'must-not-store'; ip = 'must-not-store'; token = 'must-not-store' }
    } | ConvertTo-Json -Depth 5 -Compress
    $eventResponse = Invoke-WebRequest -Uri ($pluginUrl + '/event.php') -Method Post -Headers $postHeaders -ContentType 'application/json' -Body $eventPayload -WebSession $clientSession -UseBasicParsing -TimeoutSec 25
    Assert-HttpStatus $eventResponse 204 'Event POST'

    $verified = Invoke-MarkerData 'verify'
    $data = $verified.aggregate
    $sessions = $verified.session_metrics
    $pages = $verified.page_metrics
    $event = $verified.events
    if ([int]$data.pv -ne 2 -or [int]$data.human_pv -ne 2 -or [int]$data.uv -ne 1 -or [int]$data.ip -ne 1) { throw 'Marked PV/UV/IP totals did not match the isolated SQL expectation.' }
    if ([int]$sessions.session_count -ne 1 -or [int]$sessions.page_count -ne 2) { throw 'The marked requests did not reuse exactly one two-page session.' }
    $measuredDwell = [int]($leftAt - $pageLoadedAt)
    if ([int]$pages.dwell_samples -ne 1 -or [int]$pages.min_dwell_ms -ne $measuredDwell -or [int]$pages.max_dwell_ms -ne $measuredDwell) { throw 'Page dwell time did not match the lifecycle payload.' }
    if ([int]$sessions.dwell_samples -ne 1 -or [int]$sessions.average_dwell_ms -ne $measuredDwell) { throw 'Session dwell time did not match the lifecycle payload.' }
    if ([int]$verified.rum_rows -ne 1) { throw 'The beacon did not create exactly one RUM row.' }
    $expectedEvent = 'T5_' + $marker
    if ([int]$event.count -ne 1 -or @($event.names).Count -ne 1 -or $event.names[0] -ne $expectedEvent) { throw 'The expected isolated event was not stored exactly once.' }
    $expectedKeys = @('action', 'category', 'label')
    if ((@($event.parameter_keys | Sort-Object) -join ',') -ne ($expectedKeys -join ',')) { throw 'Event parameter allowlisting did not match the expected safe keys.' }
    $result = [pscustomobject]@{
        status = 'PASS'
        site_http = 200
        cookie_session_reuse = $true
        sql = @{pv=[int]$data.pv;human_pv=[int]$data.human_pv;uv=[int]$data.uv;ip=[int]$data.ip;session_count=[int]$sessions.session_count;page_count=[int]$sessions.page_count}
        page_dwell_ms = [int]$pages.max_dwell_ms
        session_dwell_ms = [int]$sessions.average_dwell_ms
        rum_rows = [int]$verified.rum_rows
        event_parameter_keys = $expectedKeys
        marker = $marker
    }
}
finally {
    try {
        try {
            $cleanup = Invoke-MarkerData 'cleanup'
            if ($cleanup.status -ne 'PASS') { throw 'Exact marker cleanup did not pass.' }
            $after = Invoke-MarkerData 'verify'
            if ([int]$after.aggregate.pv -ne 0 -or [int]$after.rum_rows -ne 0 -or [int]$after.events.count -ne 0) { throw 'Marked rows remained after exact cleanup.' }
        }
        finally {
            if ($beaconEnabled) {
                $restored = Invoke-JsonPhp $configScript @('0', '1')
                if ($restored.status -ne 'PASS' -or [int]$restored.beacon_enabled -ne 0) { throw 'Beacon configuration could not be restored to its original disabled value.' }
            }
        }
    }
    finally {
        Remove-Item Env:\XZVS_ZBLOG_ROOT,Env:\XZVS_PLUGIN_ID,Env:\XZVS_T5_MARKER -ErrorAction SilentlyContinue
    }
}

if ($null -eq $result) { throw 'Lifecycle acceptance did not produce a verified result.' }
$result | ConvertTo-Json -Depth 6 -Compress
