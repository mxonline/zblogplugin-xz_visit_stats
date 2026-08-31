. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Orchestrator.psm1" -Force
Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.State.psm1" -Force

$codexTurns = 0
$gptReviews = 0

$codex = {
    param($prompt, $context)
    $script:codexTurns++
    return [pscustomobject]@{
        completed = $true
        turn_number = $script:codexTurns
        output = "codex-turn-$script:codexTurns"
        head_sha = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
    }
}

$gpt = {
    param($executionResult, $context)
    $script:gptReviews++
    if ($script:gptReviews -eq 1) {
        return [pscustomobject]@{
            decision = 'NEXT_STAGE'
            codex_prompt = 'execute second Codex turn'
            next_stage = 'T4_ANALYTICS_ADMIN'
            reason = 'continue automatically'
        }
    }

    return [pscustomobject]@{
        decision = 'RELEASE_READY'
        codex_prompt = $null
        next_stage = 'T4_ANALYTICS_ADMIN'
        reason = 'two-turn acceptance checkpoint reached'
    }
}

$context = [pscustomobject]@{
    request_id = 'REQ-CONTINUOUS-1'
    stage = 'T4_ANALYTICS_ADMIN'
    branch = 'feature/visit-stats-4.0'
    head_sha = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
}

$result = Invoke-BridgeContinuousLoop `
    -InitialPrompt 'execute first Codex turn' `
    -Context $context `
    -CodexExecutor $codex `
    -GptReviewer $gpt `
    -MaxTurns 5

Assert-Equal 2 $result.codex_turns 'two Codex turns executed without external input'
Assert-Equal 2 $result.gpt_reviews 'each Codex terminal result returned to GPT'
Assert-Equal 0 $result.external_input_count 'continuous loop required no user relay'
Assert-Equal 'RELEASE_READY' $result.status 'release-ready marker returned as checkpoint'
Assert-False $result.success_terminal 'RELEASE_READY is not whole-run success'
Assert-Equal 'CANDIDATE_BUILD' $result.next_action 'release-ready advances to candidate build'

$trace = @($result.trace)
Assert-Contains $trace 'CODEX_TURN_COMPLETED' 'Codex completion appears in trace'
Assert-Contains $trace 'RESULT_COLLECT' 'Codex terminal moves to result collection'
Assert-Contains $trace 'GPT_REVIEW' 'result collection returns control to GPT'
Assert-Contains $trace 'GPT_DECISION' 'GPT produces machine decision'

$turnCompletedIndex = [Array]::IndexOf($trace, 'CODEX_TURN_COMPLETED')
$resultCollectIndex = [Array]::IndexOf($trace, 'RESULT_COLLECT')
$gptReviewIndex = [Array]::IndexOf($trace, 'GPT_REVIEW')
Assert-True ($turnCompletedIndex -ge 0 -and $resultCollectIndex -gt $turnCompletedIndex -and $gptReviewIndex -gt $resultCollectIndex) 'Codex terminal follows mandatory collect/review order'

foreach ($marker in @('TEST_PASS','CI_PASS','T4_COMPLETE','RELEASE_READY')) {
    Assert-False (Test-BridgeSuccessTerminal -Status $marker) "$marker must remain non-terminal"
}
Assert-True (Test-BridgeSuccessTerminal -Status 'PLUGIN_RELEASED') 'PLUGIN_RELEASED is the only success terminal marker'
Assert-False (Test-BridgeTransition -From 'CODEX_TURN_COMPLETED' -To 'PLUGIN_RELEASED') 'Codex turn cannot jump to release terminal'

Write-Host 'PASS: continuous GPT-Codex redispatch contract'
