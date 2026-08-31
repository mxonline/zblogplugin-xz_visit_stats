. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Watchdog.psm1" -Force

$now = [DateTimeOffset]::Parse('2026-09-01T01:40:00+08:00')

$healthy = [pscustomobject]@{
    status = 'CODEX_EXECUTION'
    stage = 'T4_ANALYTICS_ADMIN'
    next_action = 'WAIT_CODEX_TURN'
    last_heartbeat = '2026-09-01T01:39:30+08:00'
    process_alive = $true
    transport_connected = $true
    resume_after = $null
}
$healthyResult = Get-BridgeWatchdogDecision -State $healthy -Now $now -StallSeconds 300
Assert-Equal 'CONTINUE' $healthyResult.action 'healthy execution continues'
Assert-Equal 'HEALTHY' $healthyResult.reason_code 'healthy reason classified'
Assert-Equal 'T4_ANALYTICS_ADMIN' $healthyResult.stage 'healthy stage preserved'

$dead = $healthy.PSObject.Copy()
$dead.process_alive = $false
$deadResult = Get-BridgeWatchdogDecision -State $dead -Now $now -StallSeconds 300
Assert-Equal 'RECOVER_EXECUTOR' $deadResult.action 'dead app server triggers executor recovery'
Assert-Equal 'APP_SERVER_EXITED' $deadResult.reason_code 'dead app server classified'
Assert-Equal 'WAIT_CODEX_TURN' $deadResult.next_action 'executor recovery preserves next action'

$disconnected = $healthy.PSObject.Copy()
$disconnected.transport_connected = $false
$disconnectResult = Get-BridgeWatchdogDecision -State $disconnected -Now $now -StallSeconds 300
Assert-Equal 'RECOVER_EXECUTOR' $disconnectResult.action 'transport disconnect triggers executor recovery'
Assert-Equal 'APP_SERVER_DISCONNECTED' $disconnectResult.reason_code 'transport disconnect classified'

$stalled = $healthy.PSObject.Copy()
$stalled.last_heartbeat = '2026-09-01T01:30:00+08:00'
$stalledResult = Get-BridgeWatchdogDecision -State $stalled -Now $now -StallSeconds 300
Assert-Equal 'RECOVER_TURN' $stalledResult.action 'stalled Codex turn triggers turn recovery'
Assert-Equal 'TURN_STALLED' $stalledResult.reason_code 'stalled turn classified'
Assert-Equal 'T4_ANALYTICS_ADMIN' $stalledResult.stage 'stalled recovery preserves stage'

$quotaWait = [pscustomobject]@{
    status = 'QUOTA_EXHAUSTED'
    stage = 'T4_ANALYTICS_ADMIN'
    next_action = 'RESUME_FROM_CHECKPOINT'
    last_heartbeat = '2026-09-01T01:39:30+08:00'
    process_alive = $true
    transport_connected = $true
    resume_after = '2026-09-01T01:45:00+08:00'
}
$quotaWaitResult = Get-BridgeWatchdogDecision -State $quotaWait -Now $now -StallSeconds 300
Assert-Equal 'WAIT_QUOTA' $quotaWaitResult.action 'quota waits until resume window'
Assert-Equal 'QUOTA_WINDOW_ACTIVE' $quotaWaitResult.reason_code 'active quota window classified'

$quotaReady = $quotaWait.PSObject.Copy()
$quotaReady.resume_after = '2026-09-01T01:39:00+08:00'
$quotaReadyResult = Get-BridgeWatchdogDecision -State $quotaReady -Now $now -StallSeconds 300
Assert-Equal 'RESUME_CHECKPOINT' $quotaReadyResult.action 'expired quota window resumes checkpoint'
Assert-Equal 'QUOTA_WINDOW_ELAPSED' $quotaReadyResult.reason_code 'expired quota window classified'
Assert-Equal 'RESUME_FROM_CHECKPOINT' $quotaReadyResult.next_action 'quota recovery preserves checkpoint action'

Write-Host 'PASS: watchdog recovery contract'
