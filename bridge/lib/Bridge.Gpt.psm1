Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module (Join-Path $PSScriptRoot 'Bridge.Quota.psm1') -Force
Import-Module (Join-Path $PSScriptRoot 'Bridge.Common.psm1') -Force

function Get-BridgeControllerDecisionSchema {
    return [ordered]@{
        type = 'object'
        additionalProperties = $false
        required = @(
            'decision',
            'reason',
            'repair_instruction',
            'required_gates',
            'safety_classification',
            'next_stage',
            'codex_prompt'
        )
        properties = [ordered]@{
            decision = @{ enum = @('NEXT_STAGE', 'REPAIR', 'REVERIFY', 'RETRY_INFRA', 'BLOCKED', 'RELEASE_READY') }
            reason = @{ type = 'string'; minLength = 1 }
            repair_instruction = @{ anyOf = @(@{ type = 'string' }, @{ type = 'null' }) }
            required_gates = @{ type = 'array'; items = @{ type = 'string' } }
            safety_classification = @{ enum = @('SAFE_REVERSIBLE', 'INFRA_TRANSIENT', 'BLOCKED_CREDENTIAL', 'BLOCKED_SECURITY', 'BLOCKED_DESTRUCTIVE', 'BLOCKED_DATA_RISK') }
            next_stage = @{ anyOf = @(@{ type = 'string' }, @{ type = 'null' }) }
            codex_prompt = @{ anyOf = @(@{ type = 'string' }, @{ type = 'null' }) }
        }
    }
}

function Get-ResponseOutputText {
    param([Parameter(Mandatory = $true)]$Response)

    if ($null -ne $Response.PSObject.Properties['output_text'] -and -not [string]::IsNullOrWhiteSpace([string]$Response.output_text)) {
        return [string]$Response.output_text
    }

    $parts = New-Object System.Collections.Generic.List[string]
    foreach ($item in @($Response.output)) {
        foreach ($content in @($item.content)) {
            if ($null -ne $content.PSObject.Properties['text'] -and -not [string]::IsNullOrWhiteSpace([string]$content.text)) {
                [void]$parts.Add([string]$content.text)
            }
        }
    }
    return ($parts -join '')
}

function Get-GptErrorText {
    param([Parameter(Mandatory = $true)]$ErrorRecord)

    $parts = New-Object System.Collections.Generic.List[string]
    if ($null -ne $ErrorRecord.Exception) { [void]$parts.Add([string]$ErrorRecord.Exception.Message) }
    if ($null -ne $ErrorRecord.ErrorDetails -and -not [string]::IsNullOrWhiteSpace([string]$ErrorRecord.ErrorDetails.Message)) {
        [void]$parts.Add([string]$ErrorRecord.ErrorDetails.Message)
    }
    [void]$parts.Add(($ErrorRecord | Out-String))
    return ($parts -join "`n")
}

function Invoke-GptBridgeDecision {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]$Context,
        [Parameter(Mandatory = $true)]$ExecutionResult,
        [Parameter(Mandatory = $true)][string]$Model,
        [Parameter(Mandatory = $true)][string]$InstructionsPath,
        [string]$PreviousResponseId,
        [string]$ApiKey = $env:OPENAI_API_KEY,
        [scriptblock]$Transport
    )

    if (-not (Test-Path -LiteralPath $InstructionsPath)) {
        throw "GPT controller instructions not found: $InstructionsPath"
    }
    $instructions = Get-Content -LiteralPath $InstructionsPath -Raw -Encoding UTF8

    $inputEnvelope = [ordered]@{
        context = $Context
        execution_result = $ExecutionResult
    }

    $body = [ordered]@{
        model = $Model
        instructions = $instructions
        input = @(
            [ordered]@{
                role = 'user'
                content = @(
                    [ordered]@{
                        type = 'input_text'
                        text = ($inputEnvelope | ConvertTo-Json -Depth 32 -Compress)
                    }
                )
            }
        )
        reasoning = @{ effort = 'high' }
        text = [ordered]@{
            format = [ordered]@{
                type = 'json_schema'
                name = 'bridge_controller_decision'
                strict = $true
                schema = (Get-BridgeControllerDecisionSchema)
            }
        }
        store = $true
    }

    if (-not [string]::IsNullOrWhiteSpace($PreviousResponseId)) {
        $body.previous_response_id = $PreviousResponseId
    }

    try {
        if ($null -ne $Transport) {
            $response = & $Transport $body
        } else {
            if ([string]::IsNullOrWhiteSpace($ApiKey)) {
                throw 'OPENAI_API_KEY is required for GPT controller decisions.'
            }

            $headers = @{
                Authorization = "Bearer $ApiKey"
                'Content-Type' = 'application/json'
            }
            $json = $body | ConvertTo-Json -Depth 64
            $response = Invoke-RestMethod -Method Post -Uri 'https://api.openai.com/v1/responses' -Headers $headers -Body $json
        }
    } catch {
        $errorText = Get-GptErrorText -ErrorRecord $_
        if (Test-QuotaExhaustion -Text $errorText) {
            $safe = Protect-BridgeEvidence $errorText
            throw "BRIDGE_QUOTA_EXHAUSTED|openai_responses|$safe"
        }
        throw
    }

    if ($null -eq $response) {
        throw 'GPT controller returned no response.'
    }
    if ($null -ne $response.PSObject.Properties['status'] -and [string]$response.status -notin @('completed', 'incomplete')) {
        throw "GPT controller response status is '$($response.status)'."
    }

    $text = Get-ResponseOutputText -Response $response
    if ([string]::IsNullOrWhiteSpace($text)) {
        throw 'GPT controller response contained no structured output text.'
    }

    try {
        $decision = $text | ConvertFrom-Json
    } catch {
        throw "GPT controller output is not valid JSON: $text"
    }

    $allowed = @('NEXT_STAGE', 'REPAIR', 'REVERIFY', 'RETRY_INFRA', 'BLOCKED', 'RELEASE_READY')
    if ($allowed -notcontains [string]$decision.decision) {
        throw "Unsupported GPT controller decision: $($decision.decision)"
    }

    if ([string]$decision.decision -in @('NEXT_STAGE', 'REPAIR', 'REVERIFY') -and [string]::IsNullOrWhiteSpace([string]$decision.codex_prompt)) {
        throw "GPT controller decision '$($decision.decision)' requires a codex_prompt."
    }

    $responseId = $null
    if ($null -ne $response.PSObject.Properties['id']) {
        $responseId = [string]$response.id
    }

    return [pscustomobject]@{
        response_id = $responseId
        decision = [string]$decision.decision
        reason = [string]$decision.reason
        repair_instruction = $decision.repair_instruction
        required_gates = @($decision.required_gates)
        safety_classification = [string]$decision.safety_classification
        next_stage = $decision.next_stage
        codex_prompt = $decision.codex_prompt
    }
}

Export-ModuleMember -Function Invoke-GptBridgeDecision, Get-BridgeControllerDecisionSchema
