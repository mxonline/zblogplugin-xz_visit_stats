. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.HandoffGuard.psm1" -Force
Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Orchestrator.psm1" -Force

$ui = Test-ExecutorHandoffViolation -Text '请在 Codex UI 点击继续，然后再回来。'
Assert-True $ui.has_violation 'Codex UI responsibility transfer detected'
Assert-Contains @($ui.violation_types) 'CODEX_UI_HANDOFF' 'Codex UI violation classified'

$relay = Test-ExecutorHandoffViolation -Text '把结果发给 GPT，再决定下一步。'
Assert-True $relay.has_violation 'manual result relay detected'
Assert-Contains @($relay.violation_types) 'RESULT_RELAY_HANDOFF' 'result relay violation classified'

$manual = Test-ExecutorHandoffViolation -Text '请手动执行命令 php -l index.php。'
Assert-True $manual.has_violation 'manual command handoff detected'
Assert-Contains @($manual.violation_types) 'MANUAL_COMMAND_HANDOFF' 'manual command violation classified'

$harmless = Test-ExecutorHandoffViolation -Text 'PHPUnit passed. Continuing with the next implementation task.'
Assert-False $harmless.has_violation 'ordinary execution status remains harmless'

$script:guardCodexTurns = 0
$script:guardGptReviews = 0
$codex = {
    param($prompt, $context)
    $script:guardCodexTurns++
    if ($script:guardCodexTurns -eq 1) {
        return [pscustomobject]@{
            completed = $true
            output = '请在 Codex UI 点击继续'
            head_sha = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
        }
    }
    return [pscustomobject]@{
        completed = $true
        output = 'second turn completed normally'
        head_sha = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
    }
}
$gpt = {
    param($executionResult, $context)
    $script:guardGptReviews++
    if ($script:guardGptReviews -eq 1) {
        Assert-True ($null -ne $executionResult.PSObject.Properties['handoff_violation']) 'orchestrator attaches violation evidence to GPT review'
        Assert-True $executionResult.handoff_violation.has_violation 'attached violation is active'
        return [pscustomobject]@{
            decision = 'NEXT_STAGE'
            codex_prompt = 'continue programmatically without user handoff'
            next_stage = 'T4_ANALYTICS_ADMIN'
            reason = 'repair executor handoff violation'
        }
    }
    return [pscustomobject]@{
        decision = 'RELEASE_READY'
        codex_prompt = $null
        next_stage = 'T4_ANALYTICS_ADMIN'
        reason = 'guard integration checkpoint'
    }
}
$context = [pscustomobject]@{
    request_id = 'REQ-HANDOFF-GUARD'
    stage = 'T4_ANALYTICS_ADMIN'
    branch = 'feature/visit-stats-4.0'
    head_sha = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
}
$result = Invoke-BridgeContinuousLoop -InitialPrompt 'first guarded turn' -Context $context -CodexExecutor $codex -GptReviewer $gpt -MaxTurns 4
Assert-Equal 2 $result.codex_turns 'handoff violation self-redispatches to a second Codex turn'
Assert-Equal 0 $result.external_input_count 'handoff repair requires no user relay'
Assert-Equal 1 $result.handoff_violation_count 'handoff violation counted once'
Assert-Equal 'RELEASE_READY' $result.status 'guarded loop continues after repair'

Write-Host 'PASS: executor handoff violation detector and self-redispatch contract'
