Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module (Join-Path $PSScriptRoot 'Bridge.Evidence.psm1')

function Get-BridgeObjectProperty {
    param($Object, [Parameter(Mandatory = $true)][string]$Name, $Default = $null)
    if ($null -eq $Object) { return $Default }
    if ($null -eq $Object.PSObject.Properties[$Name]) { return $Default }
    return $Object.$Name
}

function Get-BridgeGateNextAction {
    param([Parameter(Mandatory = $true)][string]$Gate)
    switch ($Gate) {
        'UNIT_TEST'     { return 'LOCAL_RUNTIME' }
        'LOCAL_RUNTIME' { return 'SQL_EXPLAIN' }
        'SQL_EXPLAIN'   { return 'GITHUB_CI' }
        'GITHUB_CI'     { return 'GPT_REVIEW' }
        'CANDIDATE_BUILD' { return 'FINAL_RUNTIME' }
        'FINAL_RUNTIME' { return 'RELEASE_GATE' }
        default         { return 'GPT_REVIEW' }
    }
}

function Write-BridgeGateEvidenceRecord {
    param(
        [Parameter(Mandatory = $true)][string]$EvidenceRoot,
        [Parameter(Mandatory = $true)][string]$RequestId,
        [Parameter(Mandatory = $true)][string]$Gate,
        [Parameter(Mandatory = $true)][string]$Stage,
        [Parameter(Mandatory = $true)][string]$Branch,
        [Parameter(Mandatory = $true)][string]$HeadSha,
        [Parameter(Mandatory = $true)][string]$Environment,
        [Parameter(Mandatory = $true)][string]$Command,
        [Parameter(Mandatory = $true)][string]$Status,
        [Parameter(Mandatory = $true)]$Result,
        [Parameter(Mandatory = $true)][string]$ReasonCode
    )

    $record = [pscustomobject]@{
        request_id = $RequestId
        gate = $Gate
        stage = $Stage
        branch = $Branch
        head_sha = $HeadSha
        environment = $Environment
        command = $Command
        status = $Status
        result = $Result
        reason_code = $ReasonCode
    }
    return Write-BridgeEvidence -EvidenceRoot $EvidenceRoot -Record $record
}

function New-BridgeGateReturn {
    param(
        [Parameter(Mandatory = $true)][string]$Gate,
        [Parameter(Mandatory = $true)][string]$Status,
        [Parameter(Mandatory = $true)][string]$ReasonCode,
        [Parameter(Mandatory = $true)][string]$HeadSha,
        [Parameter(Mandatory = $true)][string]$EvidenceId,
        [Parameter(Mandatory = $true)][string]$NextAction,
        [bool]$Retryable = $false
    )
    return [pscustomobject]@{
        gate = $Gate
        status = $Status
        reason_code = $ReasonCode
        head_sha = $HeadSha
        evidence_id = $EvidenceId
        retryable = $Retryable
        next_action = $NextAction
    }
}

function Invoke-BridgeGate {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]
        [ValidateSet('UNIT_TEST','LOCAL_RUNTIME','SQL_EXPLAIN','GITHUB_CI','CANDIDATE_BUILD','FINAL_RUNTIME','RELEASE_GATE')]
        [string]$Gate,
        [Parameter(Mandatory = $true)][string]$RequestId,
        [Parameter(Mandatory = $true)][string]$Stage,
        [Parameter(Mandatory = $true)][string]$Branch,
        [Parameter(Mandatory = $true)][ValidatePattern('^[0-9a-f]{40}$')][string]$HeadSha,
        [Parameter(Mandatory = $true)][string]$Environment,
        [Parameter(Mandatory = $true)][string]$Command,
        [Parameter(Mandatory = $true)][string]$EvidenceRoot,
        [Parameter(Mandatory = $true)][scriptblock]$Executor
    )

    $environmentReason = $null
    if ($Gate -in @('LOCAL_RUNTIME','SQL_EXPLAIN','FINAL_RUNTIME') -and $Environment -ne 'local_zblog_windows') {
        if ($Gate -eq 'LOCAL_RUNTIME') { $environmentReason = 'LOCAL_RUNTIME_REQUIRES_LOCAL_ZBLOG' }
        elseif ($Gate -eq 'SQL_EXPLAIN') { $environmentReason = 'SQL_EXPLAIN_REQUIRES_LOCAL_ZBLOG' }
        else { $environmentReason = 'FINAL_RUNTIME_REQUIRES_LOCAL_ZBLOG' }
    }
    if ($Gate -eq 'GITHUB_CI' -and $Environment -ne 'github_actions') {
        $environmentReason = 'GITHUB_CI_REQUIRES_GITHUB_ACTIONS'
    }

    if (-not [string]::IsNullOrWhiteSpace($environmentReason)) {
        $raw = [pscustomobject]@{ blocked = $true; reason = $environmentReason }
        $evidence = Write-BridgeGateEvidenceRecord -EvidenceRoot $EvidenceRoot -RequestId $RequestId -Gate $Gate -Stage $Stage -Branch $Branch -HeadSha $HeadSha -Environment $Environment -Command $Command -Status 'BLOCKED' -Result $raw -ReasonCode $environmentReason
        return New-BridgeGateReturn -Gate $Gate -Status 'BLOCKED' -ReasonCode $environmentReason -HeadSha $HeadSha -EvidenceId $evidence.evidence_id -NextAction 'GPT_REVIEW_BLOCKED'
    }

    $context = [pscustomobject]@{
        request_id = $RequestId
        gate = $Gate
        stage = $Stage
        branch = $Branch
        head_sha = $HeadSha
        environment = $Environment
        command = $Command
    }

    try {
        $raw = & $Executor $context
    } catch {
        $raw = [pscustomobject]@{
            exit_code = -1
            output = $_.Exception.Message
            observed_sha = $HeadSha
            details = [pscustomobject]@{ exception = $_.Exception.GetType().FullName }
        }
        $evidence = Write-BridgeGateEvidenceRecord -EvidenceRoot $EvidenceRoot -RequestId $RequestId -Gate $Gate -Stage $Stage -Branch $Branch -HeadSha $HeadSha -Environment $Environment -Command $Command -Status 'RETRYABLE_INFRA' -Result $raw -ReasonCode 'EXECUTOR_EXCEPTION'
        return New-BridgeGateReturn -Gate $Gate -Status 'RETRYABLE_INFRA' -ReasonCode 'EXECUTOR_EXCEPTION' -HeadSha $HeadSha -EvidenceId $evidence.evidence_id -NextAction 'AUTO_RETRY_GATE' -Retryable $true
    }

    $exitCode = [int](Get-BridgeObjectProperty -Object $raw -Name 'exit_code' -Default -1)
    $observedSha = [string](Get-BridgeObjectProperty -Object $raw -Name 'observed_sha' -Default '')

    if ($Gate -eq 'GITHUB_CI') {
        if ([string]::IsNullOrWhiteSpace($observedSha)) {
            $evidence = Write-BridgeGateEvidenceRecord -EvidenceRoot $EvidenceRoot -RequestId $RequestId -Gate $Gate -Stage $Stage -Branch $Branch -HeadSha $HeadSha -Environment $Environment -Command $Command -Status 'BLOCKED' -Result $raw -ReasonCode 'EXACT_SHA_MISSING'
            return New-BridgeGateReturn -Gate $Gate -Status 'BLOCKED' -ReasonCode 'EXACT_SHA_MISSING' -HeadSha $HeadSha -EvidenceId $evidence.evidence_id -NextAction 'RECHECK_GITHUB_CI'
        }
        if ($observedSha -ne $HeadSha) {
            $evidence = Write-BridgeGateEvidenceRecord -EvidenceRoot $EvidenceRoot -RequestId $RequestId -Gate $Gate -Stage $Stage -Branch $Branch -HeadSha $HeadSha -Environment $Environment -Command $Command -Status 'BLOCKED' -Result $raw -ReasonCode 'EXACT_SHA_MISMATCH'
            return New-BridgeGateReturn -Gate $Gate -Status 'BLOCKED' -ReasonCode 'EXACT_SHA_MISMATCH' -HeadSha $HeadSha -EvidenceId $evidence.evidence_id -NextAction 'RECHECK_GITHUB_CI'
        }
    }

    if ($exitCode -eq 0) {
        $evidence = Write-BridgeGateEvidenceRecord -EvidenceRoot $EvidenceRoot -RequestId $RequestId -Gate $Gate -Stage $Stage -Branch $Branch -HeadSha $HeadSha -Environment $Environment -Command $Command -Status 'PASS' -Result $raw -ReasonCode 'GATE_EXECUTION_PASS'
        return New-BridgeGateReturn -Gate $Gate -Status 'PASS' -ReasonCode 'GATE_EXECUTION_PASS' -HeadSha $HeadSha -EvidenceId $evidence.evidence_id -NextAction (Get-BridgeGateNextAction -Gate $Gate)
    }

    $evidence = Write-BridgeGateEvidenceRecord -EvidenceRoot $EvidenceRoot -RequestId $RequestId -Gate $Gate -Stage $Stage -Branch $Branch -HeadSha $HeadSha -Environment $Environment -Command $Command -Status 'REPAIRABLE' -Result $raw -ReasonCode 'GATE_EXECUTION_FAILED'
    return New-BridgeGateReturn -Gate $Gate -Status 'REPAIRABLE' -ReasonCode 'GATE_EXECUTION_FAILED' -HeadSha $HeadSha -EvidenceId $evidence.evidence_id -NextAction 'GPT_REVIEW_REPAIR'
}

Export-ModuleMember -Function Invoke-BridgeGate
