Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module (Join-Path $PSScriptRoot 'Bridge.State.psm1')
Import-Module (Join-Path $PSScriptRoot 'Bridge.HandoffGuard.psm1')

function Add-BridgeLifecycleTrace {
    param(
        [Parameter(Mandatory = $true)][System.Collections.ArrayList]$Trace,
        [Parameter(Mandatory = $true)][string]$NextStatus
    )

    if ($Trace.Count -gt 0) {
        $current = [string]$Trace[$Trace.Count - 1]
        if (-not (Test-BridgeTransition -From $current -To $NextStatus)) {
            throw "Illegal orchestrator lifecycle transition: $current -> $NextStatus"
        }
    }

    [void]$Trace.Add($NextStatus)
}

function Get-BridgeExecutionText {
    param($ExecutionResult)
    if ($null -eq $ExecutionResult) { return '' }
    if ($null -ne $ExecutionResult.PSObject.Properties['output']) {
        $output = $ExecutionResult.output
        if ($output -is [System.Array]) { return (@($output) -join "`n") }
        return [string]$output
    }
    return ($ExecutionResult | ConvertTo-Json -Depth 16 -Compress)
}

function New-BridgeLoopResult {
    param(
        [Parameter(Mandatory = $true)][string]$Status,
        [Parameter(Mandatory = $true)][string]$NextAction,
        [Parameter(Mandatory = $true)][int]$CodexTurns,
        [Parameter(Mandatory = $true)][int]$GptReviews,
        [Parameter(Mandatory = $true)][System.Collections.ArrayList]$Trace,
        [int]$HandoffViolationCount = 0,
        $LastExecutionResult,
        $LastDecision
    )

    return [pscustomobject]@{
        status = $Status
        success_terminal = (Test-BridgeSuccessTerminal -Status $Status)
        next_action = $NextAction
        codex_turns = $CodexTurns
        gpt_reviews = $GptReviews
        external_input_count = 0
        handoff_violation_count = $HandoffViolationCount
        trace = @($Trace)
        last_execution_result = $LastExecutionResult
        last_decision = $LastDecision
    }
}

function Invoke-BridgeContinuousLoop {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$InitialPrompt,
        [Parameter(Mandatory = $true)]$Context,
        [Parameter(Mandatory = $true)][scriptblock]$CodexExecutor,
        [Parameter(Mandatory = $true)][scriptblock]$GptReviewer,
        [int]$MaxTurns = 20
    )

    if ($MaxTurns -lt 1) {
        throw 'MaxTurns must be at least 1.'
    }

    $trace = New-Object System.Collections.ArrayList
    [void]$trace.Add('TASK_DISPATCH')

    $prompt = $InitialPrompt
    $codexTurns = 0
    $gptReviews = 0
    $handoffViolationCount = 0
    $lastExecution = $null
    $lastDecision = $null

    while ($codexTurns -lt $MaxTurns) {
        if ([string]::IsNullOrWhiteSpace($prompt)) {
            throw 'Continuous loop cannot dispatch an empty Codex prompt.'
        }

        Add-BridgeLifecycleTrace -Trace $trace -NextStatus 'CODEX_RUNNING'
        $lastExecution = & $CodexExecutor $prompt $Context
        $codexTurns++

        if ($null -eq $lastExecution) {
            throw 'Codex executor returned no result.'
        }
        if ($null -ne $lastExecution.PSObject.Properties['completed'] -and -not [bool]$lastExecution.completed) {
            throw 'Codex executor returned before the turn completed.'
        }

        $handoffViolation = Test-ExecutorHandoffViolation -Text (Get-BridgeExecutionText -ExecutionResult $lastExecution)
        if ($handoffViolation.has_violation) {
            $handoffViolationCount++
            Add-Member -InputObject $lastExecution -NotePropertyName 'handoff_violation' -NotePropertyValue $handoffViolation -Force
        }

        Add-BridgeLifecycleTrace -Trace $trace -NextStatus 'CODEX_TURN_COMPLETED'
        Add-BridgeLifecycleTrace -Trace $trace -NextStatus 'RESULT_COLLECT'
        Add-BridgeLifecycleTrace -Trace $trace -NextStatus 'GPT_REVIEW'

        $lastDecision = & $GptReviewer $lastExecution $Context
        $gptReviews++
        if ($null -eq $lastDecision -or $null -eq $lastDecision.PSObject.Properties['decision']) {
            throw 'GPT reviewer returned no machine decision.'
        }

        Add-BridgeLifecycleTrace -Trace $trace -NextStatus 'GPT_DECISION'
        $decision = [string]$lastDecision.decision

        switch ($decision) {
            'NEXT_STAGE' {
                $prompt = [string]$lastDecision.codex_prompt
                if ([string]::IsNullOrWhiteSpace($prompt)) {
                    throw 'NEXT_STAGE requires codex_prompt for automatic redispatch.'
                }
                Add-BridgeLifecycleTrace -Trace $trace -NextStatus 'TASK_DISPATCH'
                continue
            }
            'REPAIR' {
                Add-BridgeLifecycleTrace -Trace $trace -NextStatus 'REPAIR'
                $prompt = [string]$lastDecision.codex_prompt
                if ([string]::IsNullOrWhiteSpace($prompt)) {
                    throw 'REPAIR requires codex_prompt for automatic redispatch.'
                }
                Add-BridgeLifecycleTrace -Trace $trace -NextStatus 'TASK_DISPATCH'
                continue
            }
            'REVERIFY' {
                return New-BridgeLoopResult -Status 'REVERIFY' -NextAction 'UNIT_TEST' -CodexTurns $codexTurns -GptReviews $gptReviews -Trace $trace -HandoffViolationCount $handoffViolationCount -LastExecutionResult $lastExecution -LastDecision $lastDecision
            }
            'RETRY_INFRA' {
                return New-BridgeLoopResult -Status 'RETRY_INFRA' -NextAction 'AUTO_RETRY' -CodexTurns $codexTurns -GptReviews $gptReviews -Trace $trace -HandoffViolationCount $handoffViolationCount -LastExecutionResult $lastExecution -LastDecision $lastDecision
            }
            'BLOCKED' {
                return New-BridgeLoopResult -Status 'BLOCKED' -NextAction 'BLOCKED' -CodexTurns $codexTurns -GptReviews $gptReviews -Trace $trace -HandoffViolationCount $handoffViolationCount -LastExecutionResult $lastExecution -LastDecision $lastDecision
            }
            'RELEASE_READY' {
                return New-BridgeLoopResult -Status 'RELEASE_READY' -NextAction 'CANDIDATE_BUILD' -CodexTurns $codexTurns -GptReviews $gptReviews -Trace $trace -HandoffViolationCount $handoffViolationCount -LastExecutionResult $lastExecution -LastDecision $lastDecision
            }
            default {
                throw "Unsupported continuous-loop GPT decision: $decision"
            }
        }
    }

    return New-BridgeLoopResult -Status 'TURN_LIMIT_REACHED' -NextAction 'GPT_REVIEW_LIMIT' -CodexTurns $codexTurns -GptReviews $gptReviews -Trace $trace -HandoffViolationCount $handoffViolationCount -LastExecutionResult $lastExecution -LastDecision $lastDecision
}

Export-ModuleMember -Function Invoke-BridgeContinuousLoop
