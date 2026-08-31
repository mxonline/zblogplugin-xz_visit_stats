. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Gpt.psm1" -Force

$instructions = Resolve-Path "$PSScriptRoot/../../bridge/prompts/gpt-controller.md"
$captured = $null
$transport = {
    param($Body)
    $script:captured = $Body
    $payload = [ordered]@{
        decision = 'REPAIR'
        reason = 'PHPUnit failed and is repairable.'
        repair_instruction = 'Diagnose the failing assertion and make the smallest safe fix.'
        required_gates = @('UNIT_TEST', 'LOCAL_RUNTIME')
        safety_classification = 'SAFE_REVERSIBLE'
        next_stage = 'REPAIR'
        codex_prompt = 'Read the failing PHPUnit evidence, identify root cause, apply the smallest safe fix, rerun the focused test, then return structured evidence. Do not ask the user to operate Codex UI.'
    }
    return [pscustomobject]@{
        id = 'resp_fixture_001'
        status = 'completed'
        output = @(
            [pscustomobject]@{
                content = @(
                    [pscustomobject]@{
                        type = 'output_text'
                        text = ($payload | ConvertTo-Json -Depth 16 -Compress)
                    }
                )
            }
        )
    }
}

$context = [pscustomobject]@{ project = 'xz_visit_stats'; stage = 'T4_ANALYTICS_ADMIN' }
$execution = [pscustomobject]@{ result = 'REPAIRABLE'; failures = @(@{ gate = 'PHPUNIT' }) }
$decision = Invoke-GptBridgeDecision -Context $context -ExecutionResult $execution -Model 'gpt-test-model' -InstructionsPath $instructions -Transport $transport

Assert-Equal 'REPAIR' $decision.decision 'repair decision'
Assert-Equal 'resp_fixture_001' $decision.response_id 'response continuity id'
Assert-True (-not [string]::IsNullOrWhiteSpace($decision.codex_prompt)) 'repair must include next Codex prompt'
Assert-Contains $decision.required_gates 'UNIT_TEST' 'unit gate returned'
Assert-Equal 'json_schema' $script:captured.text.format.type 'structured output format'
Assert-Equal 'bridge_controller_decision' $script:captured.text.format.name 'structured output schema name'
Assert-True $script:captured.text.format.strict 'strict structured output'
Assert-Equal 'high' $script:captured.reasoning.effort 'controller high reasoning'

$secondCaptured = $null
$transport2 = {
    param($Body)
    $script:secondCaptured = $Body
    $payload = [ordered]@{
        decision = 'NEXT_STAGE'
        reason = 'Focused repair passed; continue verification.'
        repair_instruction = $null
        required_gates = @('LOCAL_RUNTIME')
        safety_classification = 'SAFE_REVERSIBLE'
        next_stage = 'LOCAL_RUNTIME'
        codex_prompt = 'Continue with the required local runtime verification and return structured evidence.'
    }
    return [pscustomobject]@{
        id = 'resp_fixture_002'
        status = 'completed'
        output_text = ($payload | ConvertTo-Json -Depth 16 -Compress)
    }
}

$next = Invoke-GptBridgeDecision -Context $context -ExecutionResult ([pscustomobject]@{ result = 'PASS' }) -Model 'gpt-test-model' -InstructionsPath $instructions -PreviousResponseId 'resp_fixture_001' -Transport $transport2
Assert-Equal 'NEXT_STAGE' $next.decision 'next-stage decision'
Assert-Equal 'resp_fixture_001' $script:secondCaptured.previous_response_id 'Responses API continuity'
Assert-True ($next.codex_prompt -match 'runtime') 'next Codex prompt returned'

Write-Host 'PASS: GPT controller structured decision and Codex re-dispatch contract'
