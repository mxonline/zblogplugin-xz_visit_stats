. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Executor.psm1" -Force
Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Approval.psm1" -Force

$workspace = 'C:\repo\xz_visit_stats'
$runtime = 'D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats'
$policy = New-BridgeApprovalPolicy -WorkspaceRoot $workspace -AuthorizedRuntimeRoot $runtime -AllowedNetworkHosts @('github.com','api.github.com')
$script:approvalAudit = @()
$audit = {
    param($record)
    $script:approvalAudit += $record
}
$handler = New-BridgeAppServerApprovalHandler -Policy $policy -AuditSink $audit

$safeRequest = [pscustomobject]@{
    method = 'item/commandExecution/requestApproval'
    params = [pscustomobject]@{ itemId = 'cmd-safe'; cwd = $workspace; command = 'php -l index.php' }
}
$safeResponse = & $handler $safeRequest
Assert-Equal 'acceptForSession' $safeResponse.decision 'safe command auto-approved through App Server handler'
Assert-Equal 'AUTO_APPROVED' $script:approvalAudit[-1].status 'safe approval audited'

$riskyRequest = [pscustomobject]@{
    method = 'item/commandExecution/requestApproval'
    params = [pscustomobject]@{ itemId = 'cmd-risk'; cwd = $workspace; command = 'git reset --hard HEAD~1' }
}
$riskyResponse = & $handler $riskyRequest
Assert-Equal 'decline' $riskyResponse.decision 'destructive command denied without UI handoff'
Assert-Equal 'BLOCKED_RISK' $script:approvalAudit[-1].status 'destructive denial audited'

$script:appAttempts = 0
$appServerRecoverable = {
    param($prompt, $context, $approvalHandler)
    $script:appAttempts++
    if ($script:appAttempts -eq 1) { throw 'simulated App Server disconnect' }
    return [pscustomobject]@{ completed = $true; output = 'recovered app server turn'; thread_id = 'thread-1' }
}
$recover = {
    param($context)
    return $true
}
$fallbackUnused = {
    param($prompt, $context)
    throw 'fallback must not be used when App Server recovery succeeds'
}
$context = [pscustomobject]@{ stage = 'T4_ANALYTICS_ADMIN'; next_action = 'WAIT_CODEX_TURN'; branch = 'feature/visit-stats-4.0'; head_sha = 'cccccccccccccccccccccccccccccccccccccccc' }
$recovered = Invoke-BridgeExecutorTurn -Prompt 'recoverable turn' -Context $context -ApprovalPolicy $policy -AppServerInvoker $appServerRecoverable -AppServerRecover $recover -ExecFallback $fallbackUnused
Assert-Equal 'PASS' $recovered.status 'recovered App Server turn passes'
Assert-Equal 'codex_app_server' $recovered.executor_transport 'App Server remains primary after recovery'
Assert-Equal 1 $recovered.recovery_count 'one App Server recovery recorded'
Assert-Equal 'RECOVER_EXECUTOR' $recovered.watchdog_action 'Watchdog classified transport recovery'

$appServerDown = {
    param($prompt, $context, $approvalHandler)
    throw 'App Server unavailable'
}
$recoverFails = {
    param($context)
    return $false
}
$fallbackPass = {
    param($prompt, $context)
    return [pscustomobject]@{ exit_code = 0; output = @('fallback completed') }
}
$fallback = Invoke-BridgeExecutorTurn -Prompt 'fallback turn' -Context $context -ApprovalPolicy $policy -AppServerInvoker $appServerDown -AppServerRecover $recoverFails -ExecFallback $fallbackPass
Assert-Equal 'PASS' $fallback.status 'codex exec fallback can recover executor transport'
Assert-Equal 'codex_exec_fallback' $fallback.executor_transport 'fallback transport recorded'

$fallbackFail = {
    param($prompt, $context)
    return [pscustomobject]@{ exit_code = 1; output = @('fallback failed') }
}
$blocked = Invoke-BridgeExecutorTurn -Prompt 'blocked turn' -Context $context -ApprovalPolicy $policy -AppServerInvoker $appServerDown -AppServerRecover $recoverFails -ExecFallback $fallbackFail
Assert-Equal 'BLOCKED_EXECUTOR_TRANSPORT' $blocked.status 'both transports unavailable fail closed'
Assert-Equal 0 $blocked.external_input_count 'executor transport failure cannot become user relay'
Assert-False ([string]$blocked.next_action -match '(?i)codex ui|ask user|manual') 'blocked executor never points to Codex UI'

Write-Host 'PASS: executor adapter approval, recovery and fallback contract'
