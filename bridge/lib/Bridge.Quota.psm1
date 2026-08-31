Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module (Join-Path $PSScriptRoot 'Bridge.Common.psm1') -Force

function Test-QuotaExhaustion {
    [CmdletBinding()]
    param(
        [AllowNull()][AllowEmptyString()][string]$Text,
        [int]$StatusCode = 0
    )

    if ([string]::IsNullOrWhiteSpace($Text)) { return $false }

    $quotaPatterns = @(
        '(?i)insufficient_quota',
        '(?i)usage[_\s-]*limit[_\s-]*(?:reached|exceeded|hit)',
        '(?i)you(?:''|’)ve\s+hit\s+your\s+usage\s+limit',
        '(?i)billing[_\s-]*hard[_\s-]*limit[_\s-]*(?:reached|exceeded)',
        '(?i)quota\s+(?:is\s+)?(?:exhausted|depleted|exceeded)',
        '(?i)(?:credits?|balance)\s+(?:are\s+)?(?:exhausted|depleted)',
        '(?i)(?:remaining|available)\s+(?:quota|credits?|tokens?)\s*[:=]\s*0\b'
    )

    foreach ($pattern in $quotaPatterns) {
        if ($Text -match $pattern) { return $true }
    }

    # A bare HTTP 429 is a transient rate limit, not proof that quota is zero.
    if ($StatusCode -eq 429) { return $false }
    return $false
}

function Get-QuotaProvider {
    [CmdletBinding()]
    param([AllowNull()][AllowEmptyString()][string]$Source)

    if ($Source -match '(?i)responses|openai|api\.openai') { return 'openai_responses' }
    if ($Source -match '(?i)codex|app server') { return 'codex' }
    return 'unknown'
}

function Add-OrSetProperty {
    param(
        [Parameter(Mandatory = $true)]$Object,
        [Parameter(Mandatory = $true)][string]$Name,
        $Value
    )

    if ($null -ne $Object.PSObject.Properties[$Name]) {
        $Object.$Name = $Value
    } else {
        $Object | Add-Member -NotePropertyName $Name -NotePropertyValue $Value
    }
}

function Save-QuotaCheckpoint {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$StatePath,
        [Parameter(Mandatory = $true)][string]$RunsRoot,
        [Parameter(Mandatory = $true)]$State,
        [Parameter(Mandatory = $true)][ValidateSet('openai_responses','codex','unknown')][string]$Provider,
        [Parameter(Mandatory = $true)][string]$Reason,
        [string]$PendingAction,
        [string[]]$EvidenceIds = @(),
        [datetime]$ResumeAfter = ([DateTime]::UtcNow.AddMinutes(15))
    )

    $requestId = [string]$State.request_id
    if ([string]::IsNullOrWhiteSpace($requestId)) { $requestId = 'unknown-request' }

    $safeRequestId = ($requestId -replace '[^A-Za-z0-9._-]', '_')
    $runDir = Join-Path $RunsRoot $safeRequestId
    if (-not (Test-Path -LiteralPath $runDir)) {
        New-Item -ItemType Directory -Path $runDir -Force | Out-Null
    }

    $checkpointPath = Join-Path $runDir 'quota-checkpoint.json'
    $savedAt = Get-UtcIsoTimestamp
    $resumeAt = $ResumeAfter.ToUniversalTime().ToString('o')
    $safeReason = Protect-BridgeEvidence $Reason

    $checkpoint = [ordered]@{
        schema_version = '1.0'
        checkpoint_type = 'QUOTA_EXHAUSTION'
        request_id = $requestId
        saved_at = $savedAt
        resume_after = $resumeAt
        provider = $Provider
        reason = $safeReason
        status_before_pause = [string]$State.status
        stage = [string]$State.stage
        branch = if ($null -ne $State.PSObject.Properties['branch']) { $State.branch } else { $null }
        head_sha = $State.head_sha
        candidate_sha = if ($null -ne $State.PSObject.Properties['candidate_sha']) { $State.candidate_sha } else { $null }
        thread_id = $State.thread_id
        controller_response_id = if ($null -ne $State.PSObject.Properties['controller_response_id']) { $State.controller_response_id } else { $null }
        repair_round = [int]$State.repair_round
        infra_retry_round = [int]$State.infra_retry_round
        last_verified_gate = $State.last_verified_gate
        pending_action = if ([string]::IsNullOrWhiteSpace($PendingAction)) { [string]$State.next_action } else { $PendingAction }
        evidence_ids = @($EvidenceIds)
        resume_policy = 'PRESERVE_VERIFIED'
    }

    Write-BridgeJsonAtomic -Path $checkpointPath -Value $checkpoint

    Add-OrSetProperty -Object $State -Name 'status' -Value 'PAUSED_QUOTA'
    Add-OrSetProperty -Object $State -Name 'next_action' -Value 'AUTO_RESUME_FROM_QUOTA_CHECKPOINT'
    Add-OrSetProperty -Object $State -Name 'quota_provider' -Value $Provider
    Add-OrSetProperty -Object $State -Name 'quota_reason' -Value $safeReason
    Add-OrSetProperty -Object $State -Name 'resume_after' -Value $resumeAt
    Add-OrSetProperty -Object $State -Name 'quota_checkpoint_path' -Value $checkpointPath
    Add-OrSetProperty -Object $State -Name 'updated_at' -Value $savedAt
    Write-BridgeJsonAtomic -Path $StatePath -Value $State

    return [pscustomobject]@{
        status = 'PAUSED_QUOTA'
        checkpoint_path = $checkpointPath
        resume_after = $resumeAt
        provider = $Provider
        repair_round_preserved = [int]$State.repair_round
        infra_retry_round_preserved = [int]$State.infra_retry_round
    }
}

function Get-QuotaResumeCheckpoint {
    [CmdletBinding()]
    param([Parameter(Mandatory = $true)][string]$CheckpointPath)

    $checkpoint = Read-BridgeJson -Path $CheckpointPath
    if ([string]$checkpoint.checkpoint_type -ne 'QUOTA_EXHAUSTION') {
        throw "Not a quota checkpoint: $CheckpointPath"
    }
    return $checkpoint
}

Export-ModuleMember -Function Test-QuotaExhaustion, Get-QuotaProvider, Save-QuotaCheckpoint, Get-QuotaResumeCheckpoint
