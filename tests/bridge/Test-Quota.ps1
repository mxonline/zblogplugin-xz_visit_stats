. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Quota.psm1" -Force
Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.State.psm1" -Force

Assert-True (Test-QuotaExhaustion -Text 'insufficient_quota: You exceeded your current quota.' -StatusCode 429) 'explicit OpenAI quota exhaustion'
Assert-True (Test-QuotaExhaustion -Text "You've hit your usage limit. Please try again later.") 'Codex usage limit exhaustion'
Assert-True (Test-QuotaExhaustion -Text 'remaining credits: 0') 'zero credits exhaustion'
Assert-False (Test-QuotaExhaustion -Text 'rate_limit_exceeded: too many requests' -StatusCode 429) 'ordinary 429 must remain transient infra retry'
Assert-False (Test-QuotaExhaustion -Text 'HTTP 500 internal server error' -StatusCode 500) 'non-quota infrastructure failure'
Assert-Equal 'openai_responses' (Get-QuotaProvider -Source 'https://api.openai.com/v1/responses') 'Responses API provider classification'
Assert-Equal 'codex' (Get-QuotaProvider -Source 'Codex App Server') 'Codex provider classification'

Assert-True (Test-BridgeTransition -From 'CODEX_RUNNING' -To 'PAUSED_QUOTA') 'Codex can pause on exhausted quota'
Assert-True (Test-BridgeTransition -From 'GPT_REVIEW' -To 'PAUSED_QUOTA') 'GPT controller can pause on exhausted quota'
Assert-True (Test-BridgeTransition -From 'PAUSED_QUOTA' -To 'RESUME_GATE') 'quota pause must resume through Resume Gate'
Assert-False (Test-BridgeTransition -From 'PLUGIN_RELEASED' -To 'PAUSED_QUOTA') 'released workflow cannot become quota-paused'

$temp = Join-Path ([System.IO.Path]::GetTempPath()) ("xz-bridge-quota-" + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $temp -Force | Out-Null
$statePath = Join-Path $temp 'state.runtime.json'
$runsRoot = Join-Path $temp 'runs'

try {
    $state = [pscustomobject]@{
        schema_version = '1.0'
        status = 'CODEX_RUNNING'
        stage = 'T4_ANALYTICS_ADMIN'
        request_id = 'REQ-QUOTA-001'
        thread_id = 'thread-test-001'
        controller_response_id = 'resp-test-001'
        branch = 'feature/visit-stats-4.0'
        head_sha = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
        candidate_sha = $null
        repair_round = 2
        infra_retry_round = 4
        last_verified_gate = 'UNIT_TEST'
        next_action = 'WAIT_CODEX_RESULT'
        blocked_reason = $null
        updated_at = '2026-08-31T00:00:00Z'
    }

    $resumeAt = [DateTime]::UtcNow.AddHours(1)
    $saved = Save-QuotaCheckpoint -StatePath $statePath -RunsRoot $runsRoot -State $state -Provider 'codex' -Reason 'usage limit reached; token=secret_should_not_survive' -PendingAction 'RESUME_CODEX_TURN' -EvidenceIds @('ev-1','ev-2') -ResumeAfter $resumeAt

    Assert-Equal 'PAUSED_QUOTA' $saved.status 'quota save status'
    Assert-PathExists $saved.checkpoint_path 'quota checkpoint persisted'
    Assert-PathExists $statePath 'runtime state persisted'
    Assert-Equal 2 $saved.repair_round_preserved 'repair round preserved'
    Assert-Equal 4 $saved.infra_retry_round_preserved 'infra retry round preserved'

    $checkpoint = Get-QuotaResumeCheckpoint -CheckpointPath $saved.checkpoint_path
    Assert-Equal 'CODEX_RUNNING' $checkpoint.status_before_pause 'original state preserved'
    Assert-Equal 'T4_ANALYTICS_ADMIN' $checkpoint.stage 'stage preserved'
    Assert-Equal 'thread-test-001' $checkpoint.thread_id 'thread preserved'
    Assert-Equal 'resp-test-001' $checkpoint.controller_response_id 'GPT response continuity preserved'
    Assert-Equal 'UNIT_TEST' $checkpoint.last_verified_gate 'verified gate preserved'
    Assert-Equal 'RESUME_CODEX_TURN' $checkpoint.pending_action 'pending action preserved'
    Assert-Contains $checkpoint.evidence_ids 'ev-1' 'evidence ledger pointer preserved'
    Assert-NotContains $checkpoint.reason 'secret_should_not_survive' 'quota reason must be redacted'

    $runtimeState = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
    Assert-Equal 'PAUSED_QUOTA' $runtimeState.status 'runtime state paused'
    Assert-Equal 'AUTO_RESUME_FROM_QUOTA_CHECKPOINT' $runtimeState.next_action 'automatic resume action'
    Assert-Equal 'codex' $runtimeState.quota_provider 'quota provider persisted'
    Assert-True (Assert-ZeroTouchStateInvariant -State $runtimeState) 'paused quota remains zero-touch safe'

    Write-Host 'PASS: quota exhaustion checkpoint and resume contract'
} finally {
    Remove-Item -LiteralPath $temp -Recurse -Force -ErrorAction SilentlyContinue
}
