. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Handoff.psm1" -Force

$baseline = [pscustomobject]@{
    request_id = 'REQ-HANDOFF-1'
    stage = 'T4_ANALYTICS_ADMIN'
    status = 'GPT_REVIEW'
    branch = 'feature/visit-stats-4.0'
    head_sha = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
    thread_id = 'thread-1'
    controller_response_id = 'resp-1'
    last_verified_gate = 'LOCAL_RUNTIME'
    verified_gates = @('UNIT_TEST','LOCAL_RUNTIME')
    evidence_ids = @('EVD-1','EVD-2')
    next_action = 'SQL_EXPLAIN'
}

$valid = [pscustomobject]@{
    request_id = 'REQ-HANDOFF-1'
    stage = 'T4_ANALYTICS_ADMIN'
    status = 'SQL_EXPLAIN'
    branch = 'feature/visit-stats-4.0'
    head_sha = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
    thread_id = 'thread-1'
    controller_response_id = 'resp-2'
    last_verified_gate = 'SQL_EXPLAIN'
    verified_gates = @('UNIT_TEST','LOCAL_RUNTIME','SQL_EXPLAIN')
    evidence_ids = @('EVD-1','EVD-2','EVD-3')
    next_action = 'GITHUB_CI'
}
$validResult = Test-BridgeHandoff -Current $baseline -Incoming $valid
Assert-Equal 'PASS' $validResult.status 'valid same-SHA handoff passes'
Assert-Equal 'GITHUB_CI' $validResult.handoff.next_action 'next action preserved'

$stale = $valid.PSObject.Copy()
$stale.head_sha = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
$staleResult = Test-BridgeHandoff -Current $baseline -Incoming $stale
Assert-Equal 'BLOCKED_STALE_HANDOFF' $staleResult.status 'stale SHA handoff blocked'
Assert-Equal 'HEAD_SHA_MISMATCH' $staleResult.reason_code 'stale SHA classified'

$wrongBranch = $valid.PSObject.Copy()
$wrongBranch.branch = 'main'
$wrongBranchResult = Test-BridgeHandoff -Current $baseline -Incoming $wrongBranch
Assert-Equal 'BLOCKED_STALE_HANDOFF' $wrongBranchResult.status 'wrong branch handoff blocked'
Assert-Equal 'BRANCH_MISMATCH' $wrongBranchResult.reason_code 'wrong branch classified'

$regression = $valid.PSObject.Copy()
$regression.verified_gates = @('UNIT_TEST')
$regressionResult = Test-BridgeHandoff -Current $baseline -Incoming $regression
Assert-Equal 'BLOCKED_VERIFIED_REGRESSION' $regressionResult.status 'verified gate regression blocked'
Assert-Equal 'VERIFIED_GATE_DROPPED' $regressionResult.reason_code 'verified regression classified'

$threadMismatch = $valid.PSObject.Copy()
$threadMismatch.thread_id = 'thread-other'
$threadResult = Test-BridgeHandoff -Current $baseline -Incoming $threadMismatch
Assert-Equal 'BLOCKED_STALE_HANDOFF' $threadResult.status 'unexpected thread replacement blocked'
Assert-Equal 'THREAD_ID_MISMATCH' $threadResult.reason_code 'thread mismatch classified'

$manual = $valid.PSObject.Copy()
$manual.next_action = 'ask user to copy command into Codex UI'
$manualResult = Test-BridgeHandoff -Current $baseline -Incoming $manual
Assert-Equal 'BLOCKED_ZERO_TOUCH_REGRESSION' $manualResult.status 'manual handoff regression blocked'
Assert-Equal 'MANUAL_USER_HANDOFF_FORBIDDEN' $manualResult.reason_code 'zero-touch regression classified'

$preThread = $baseline.PSObject.Copy()
$preThread.thread_id = $null
$newThread = $valid.PSObject.Copy()
$newThread.thread_id = 'thread-new'
$newThreadResult = Test-BridgeHandoff -Current $preThread -Incoming $newThread
Assert-Equal 'PASS' $newThreadResult.status 'initial thread binding allowed'

Write-Host 'PASS: handoff guard contract'
