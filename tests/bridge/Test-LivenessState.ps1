. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.State.psm1" -Force

$repoRoot = (Resolve-Path "$PSScriptRoot/../..").Path
$schema = Get-Content (Join-Path $repoRoot 'bridge/schemas/state.schema.json') -Raw | ConvertFrom-Json
$committedState = Get-Content (Join-Path $repoRoot 'bridge/state.json') -Raw | ConvertFrom-Json

foreach ($field in @('last_event_at','last_progress_at','turn_started_at','executor_restart_count','executor_transport','watchdog_status')) {
    Assert-Contains @($schema.required) $field "state schema requires $field"
    Assert-True ($null -ne $schema.properties.PSObject.Properties[$field]) "state schema defines $field"
    Assert-True ($null -ne $committedState.PSObject.Properties[$field]) "committed bridge state carries $field"
}

Assert-Equal 0 $committedState.executor_restart_count 'committed state starts with zero executor restarts'
Assert-Equal 'not_started' $committedState.executor_transport 'committed state starts with no executor transport'
Assert-Equal 'IDLE' $committedState.watchdog_status 'committed state starts with idle watchdog'

$tempRoot = Join-Path ([System.IO.Path]::GetTempPath()) ('xz-bridge-liveness-' + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $tempRoot -Force | Out-Null
try {
    $statePath = Join-Path $tempRoot 'state.json'
    $state = $committedState.PSObject.Copy()
    $state.status = 'CODEX_RUNNING'
    $state.stage = 'T4_ANALYTICS_ADMIN'
    $state.request_id = 'REQ-LIVE-1'
    $state.head_sha = 'dddddddddddddddddddddddddddddddddddddddd'

    $started = Start-BridgeTurnLiveness -Path $statePath -State $state -Transport 'codex_app_server' -Timestamp '2026-09-01T01:50:00+08:00'
    Assert-Equal '2026-09-01T01:50:00+08:00' $started.turn_started_at 'turn start persisted'
    Assert-Equal '2026-09-01T01:50:00+08:00' $started.last_event_at 'turn start initializes last event'
    Assert-Equal '2026-09-01T01:50:00+08:00' $started.last_progress_at 'turn start initializes last progress'
    Assert-Equal 'codex_app_server' $started.executor_transport 'primary transport persisted'
    Assert-Equal 'HEALTHY' $started.watchdog_status 'turn start marks watchdog healthy'

    $heartbeat = Update-BridgeLiveness -Path $statePath -State $started -EventTimestamp '2026-09-01T01:50:10+08:00' -ProgressTimestamp '2026-09-01T01:50:08+08:00' -WatchdogStatus 'HEALTHY'
    Assert-Equal '2026-09-01T01:50:10+08:00' $heartbeat.last_event_at 'event heartbeat persisted'
    Assert-Equal '2026-09-01T01:50:08+08:00' $heartbeat.last_progress_at 'progress heartbeat persisted independently'

    $restarted = Register-BridgeExecutorRestart -Path $statePath -State $heartbeat -Transport 'codex_exec_fallback' -Timestamp '2026-09-01T01:51:00+08:00'
    Assert-Equal 1 $restarted.executor_restart_count 'executor restart count increments'
    Assert-Equal 'codex_exec_fallback' $restarted.executor_transport 'fallback transport persisted after restart'
    Assert-Equal 'RECOVERED_EXECUTOR' $restarted.watchdog_status 'restart recovery status persisted'
    Assert-Equal '2026-09-01T01:51:00+08:00' $restarted.last_event_at 'restart updates last event'

    $reloaded = Get-BridgeState -Path $statePath
    Assert-Equal 1 $reloaded.executor_restart_count 'restart state survives reload'
    Assert-Equal 'T4_ANALYTICS_ADMIN' $reloaded.stage 'liveness updates preserve stage'
    Assert-Equal 'dddddddddddddddddddddddddddddddddddddddd' $reloaded.head_sha 'liveness updates preserve exact SHA'
} finally {
    Remove-Item -LiteralPath $tempRoot -Recurse -Force -ErrorAction SilentlyContinue
}

Write-Host 'PASS: persistent watchdog liveness state contract'
