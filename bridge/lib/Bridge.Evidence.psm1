Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module (Join-Path $PSScriptRoot 'Bridge.Common.psm1') -Force

function ConvertTo-SafeEvidenceObject {
    param([Parameter(Mandatory = $true)]$Value)

    $json = $Value | ConvertTo-Json -Depth 32 -Compress
    $safeJson = Protect-BridgeEvidence $json
    return ($safeJson | ConvertFrom-Json)
}

function Write-BridgeEvidence {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$EvidenceRoot,
        [Parameter(Mandatory = $true)]$Record,
        [string]$Supersedes
    )

    foreach ($field in @('request_id','gate','stage','branch','head_sha','environment','command','status','result')) {
        if ($null -eq $Record.PSObject.Properties[$field]) {
            throw "Evidence record missing required field '$field'."
        }
    }

    if (-not (Test-Path -LiteralPath $EvidenceRoot)) {
        New-Item -ItemType Directory -Path $EvidenceRoot -Force | Out-Null
    }

    $id = 'EVD-' + [Guid]::NewGuid().ToString('N')
    $safe = ConvertTo-SafeEvidenceObject -Value $Record
    $out = [ordered]@{
        schema_version = '1.0'
        evidence_id = $id
        request_id = [string]$safe.request_id
        gate = [string]$safe.gate
        stage = [string]$safe.stage
        branch = [string]$safe.branch
        head_sha = [string]$safe.head_sha
        timestamp = Get-UtcIsoTimestamp
        environment = [string]$safe.environment
        command = [string]$safe.command
        status = [string]$safe.status
        result = $safe.result
        supersedes = if ([string]::IsNullOrWhiteSpace($Supersedes)) { $null } else { $Supersedes }
    }

    foreach ($property in @($safe.PSObject.Properties)) {
        if (-not $out.Contains($property.Name)) {
            $out[$property.Name] = $property.Value
        }
    }

    $path = Join-Path $EvidenceRoot ($id + '.json')
    Write-BridgeJsonAtomic -Path $path -Value $out

    return [pscustomobject]@{
        evidence_id = $id
        path = $path
        status = [string]$out.status
        head_sha = [string]$out.head_sha
    }
}

function Get-BridgeEvidence {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$EvidenceRoot,
        [Parameter(Mandatory = $true)][string]$EvidenceId
    )

    $path = Join-Path $EvidenceRoot ($EvidenceId + '.json')
    return Read-BridgeJson -Path $path
}

function Get-EvidenceInvalidation {
    param(
        [Parameter(Mandatory = $true)][string]$EvidenceRoot,
        [Parameter(Mandatory = $true)][string]$EvidenceId
    )

    if (-not (Test-Path -LiteralPath $EvidenceRoot)) { return $null }
    $matches = Get-ChildItem -LiteralPath $EvidenceRoot -Filter ('INV-' + $EvidenceId + '-*.json') -File -ErrorAction SilentlyContinue
    if ($matches.Count -eq 0) { return $null }
    return (Read-BridgeJson -Path $matches[-1].FullName)
}

function Test-GateEvidence {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]$GateResult,
        [Parameter(Mandatory = $true)][string]$EvidenceRoot,
        [string]$ExpectedSha
    )

    if ([string]$GateResult.status -eq 'PASS' -and [string]::IsNullOrWhiteSpace([string]$GateResult.evidence_id)) {
        return [pscustomobject]@{ valid = $false; reason = 'PASS_WITHOUT_EVIDENCE' }
    }

    if ([string]::IsNullOrWhiteSpace([string]$GateResult.evidence_id)) {
        return [pscustomobject]@{ valid = $true; reason = 'NON_PASS_NO_EVIDENCE_REQUIRED' }
    }

    try {
        $evidence = Get-BridgeEvidence -EvidenceRoot $EvidenceRoot -EvidenceId ([string]$GateResult.evidence_id)
    } catch {
        return [pscustomobject]@{ valid = $false; reason = 'EVIDENCE_NOT_FOUND' }
    }

    $invalidation = Get-EvidenceInvalidation -EvidenceRoot $EvidenceRoot -EvidenceId ([string]$GateResult.evidence_id)
    if ($null -ne $invalidation) {
        return [pscustomobject]@{ valid = $false; reason = 'EVIDENCE_INVALIDATED'; evidence = $evidence }
    }

    if (-not [string]::IsNullOrWhiteSpace($ExpectedSha) -and [string]$evidence.head_sha -ne $ExpectedSha) {
        return [pscustomobject]@{ valid = $false; reason = 'SHA_MISMATCH'; evidence = $evidence }
    }

    if ([string]$GateResult.status -eq 'PASS' -and [string]$evidence.status -ne 'PASS') {
        return [pscustomobject]@{ valid = $false; reason = 'EVIDENCE_NOT_PASS'; evidence = $evidence }
    }

    return [pscustomobject]@{ valid = $true; reason = 'VALID'; evidence = $evidence }
}

function Invalidate-ShaDependentEvidence {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$EvidenceRoot,
        [Parameter(Mandatory = $true)][string]$OldSha,
        [Parameter(Mandatory = $true)][string]$NewSha
    )

    if (-not (Test-Path -LiteralPath $EvidenceRoot)) {
        return [pscustomobject]@{ invalidated_count = 0; old_sha = $OldSha; new_sha = $NewSha }
    }

    $count = 0
    foreach ($file in Get-ChildItem -LiteralPath $EvidenceRoot -Filter 'EVD-*.json' -File) {
        $record = Read-BridgeJson -Path $file.FullName
        if ([string]$record.head_sha -ne $OldSha) { continue }

        $invalidation = [ordered]@{
            schema_version = '1.0'
            invalidation_type = 'SHA_CHANGED'
            evidence_id = [string]$record.evidence_id
            old_sha = $OldSha
            new_sha = $NewSha
            invalidated_at = Get-UtcIsoTimestamp
        }
        $path = Join-Path $EvidenceRoot ('INV-' + [string]$record.evidence_id + '-' + [Guid]::NewGuid().ToString('N') + '.json')
        Write-BridgeJsonAtomic -Path $path -Value $invalidation
        $count++
    }

    return [pscustomobject]@{ invalidated_count = $count; old_sha = $OldSha; new_sha = $NewSha }
}

Export-ModuleMember -Function Write-BridgeEvidence, Get-BridgeEvidence, Test-GateEvidence, Invalidate-ShaDependentEvidence
