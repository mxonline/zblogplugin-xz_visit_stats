. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.State.psm1" -Force

Assert-True (Test-BridgeTransition -From 'IDLE' -To 'CONTEXT_SYNC') 'IDLE -> CONTEXT_SYNC'
Assert-False (Test-BridgeTransition -From 'IDLE' -To 'RELEASE') 'IDLE cannot jump to RELEASE'
Assert-True (Test-BridgeTransition -From 'TASK_DISPATCH' -To 'CODEX_RUNNING') 'dispatch -> codex running'
Assert-True (Test-BridgeTransition -From 'CODEX_RUNNING' -To 'CODEX_TURN_COMPLETED') 'codex running -> turn complete'
Assert-True (Test-BridgeTransition -From 'CODEX_TURN_COMPLETED' -To 'RESULT_COLLECT') 'turn complete -> result collect'
Assert-False (Test-BridgeTransition -From 'CODEX_TURN_COMPLETED' -To 'PLUGIN_RELEASED') 'Codex output cannot end the workflow'
Assert-False (Test-BridgeTransition -From 'CODEX_TURN_COMPLETED' -To 'RELEASE') 'Codex output cannot bypass GPT review'
Assert-True (Test-BridgeTransition -From 'RESULT_COLLECT' -To 'GPT_REVIEW') 'result collect -> GPT review'
Assert-True (Test-BridgeTransition -From 'GPT_REVIEW' -To 'GPT_DECISION') 'GPT review -> decision'
Assert-True (Test-BridgeTransition -From 'GPT_DECISION' -To 'REPAIR') 'GPT decision -> repair'
Assert-True (Test-BridgeTransition -From 'GPT_DECISION' -To 'TASK_DISPATCH') 'GPT decision -> next Codex turn'
Assert-True (Test-BridgeTransition -From 'PROJECT_STATE_WRITEBACK' -To 'PLUGIN_RELEASED') 'only final writeback can enter released state'

$valid = [pscustomobject]@{
    status = 'CODEX_TURN_COMPLETED'
    stage = 'T4_ANALYTICS_ADMIN'
    next_action = 'RESULT_COLLECT'
}
Assert-True (Assert-ZeroTouchStateInvariant -State $valid) 'valid Codex turn handoff'

$threw = $false
try {
    $invalid = [pscustomobject]@{
        status = 'CODEX_TURN_COMPLETED'
        stage = 'T4_ANALYTICS_ADMIN'
        next_action = 'Ask user to open Codex UI and continue'
    }
    Assert-ZeroTouchStateInvariant -State $invalid | Out-Null
} catch {
    $threw = $true
}
Assert-True $threw 'manual Codex UI handoff must be rejected'

Write-Host 'PASS: bridge state transitions and continuous-loop invariants'
