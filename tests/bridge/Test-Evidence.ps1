. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Evidence.psm1" -Force

$temp = Join-Path ([IO.Path]::GetTempPath()) ('bridge-evidence-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $temp -Force | Out-Null
try {
    $record = [pscustomobject]@{
        request_id = 'REQ-E1'
        gate = 'GITHUB_CI'
        stage = 'T4_ANALYTICS_ADMIN'
        branch = 'feature/visit-stats-4.0'
        head_sha = 'sha-A'
        environment = 'github-actions'
        command = 'ci exact sha'
        status = 'PASS'
        result = 'success'
    }
    $saved = Write-BridgeEvidence -EvidenceRoot $temp -Record $record
    Assert-True (-not [string]::IsNullOrWhiteSpace([string]$saved.evidence_id)) 'evidence id generated'
    Assert-True (Test-Path -LiteralPath $saved.path) 'evidence persisted'

    $loaded = Get-BridgeEvidence -EvidenceRoot $temp -EvidenceId $saved.evidence_id
    Assert-Equal 'sha-A' $loaded.head_sha 'evidence bound to SHA'
    Assert-Equal 'PASS' $loaded.status 'evidence status persisted'

    $gatePass = Test-GateEvidence -GateResult ([pscustomobject]@{ status = 'PASS'; evidence_id = $saved.evidence_id }) -EvidenceRoot $temp -ExpectedSha 'sha-A'
    Assert-True $gatePass.valid 'PASS with matching evidence accepted'

    $missing = Test-GateEvidence -GateResult ([pscustomobject]@{ status = 'PASS'; evidence_id = $null }) -EvidenceRoot $temp -ExpectedSha 'sha-A'
    Assert-False $missing.valid 'PASS without evidence rejected'

    $wrongSha = Test-GateEvidence -GateResult ([pscustomobject]@{ status = 'PASS'; evidence_id = $saved.evidence_id }) -EvidenceRoot $temp -ExpectedSha 'sha-B'
    Assert-False $wrongSha.valid 'old SHA evidence cannot satisfy new candidate'

    $invalidated = Invalidate-ShaDependentEvidence -EvidenceRoot $temp -OldSha 'sha-A' -NewSha 'sha-B'
    Assert-True ($invalidated.invalidated_count -ge 1) 'new SHA invalidates SHA-bound evidence'

    $secretRecord = [pscustomobject]@{
        request_id = 'REQ-E2'; gate = 'UNIT_TEST'; stage = 'T4'; branch = 'x'; head_sha = 'sha-B'; environment = 'local';
        command = 'Authorization: Bearer unit-test-sensitive-value'; status = 'FAIL'; result = 'cookie=session-value'
    }
    $secretSaved = Write-BridgeEvidence -EvidenceRoot $temp -Record $secretRecord
    $secretText = Get-Content -LiteralPath $secretSaved.path -Raw
    Assert-NotContains $secretText 'unit-test-sensitive-value' 'authorization material redacted'
    Assert-NotContains $secretText 'session-value' 'cookie material redacted'

    $correction = $record.PSObject.Copy()
    $correction.status = 'FAIL'
    $correction.result = 'superseding correction'
    $corrected = Write-BridgeEvidence -EvidenceRoot $temp -Record $correction -Supersedes $saved.evidence_id
    Assert-True (Test-Path -LiteralPath $saved.path) 'original evidence remains append-only'
    Assert-True ($corrected.evidence_id -ne $saved.evidence_id) 'correction gets new evidence id'
    $correctedLoaded = Get-BridgeEvidence -EvidenceRoot $temp -EvidenceId $corrected.evidence_id
    Assert-Equal $saved.evidence_id $correctedLoaded.supersedes 'correction references superseded evidence'

    Write-Host 'PASS: evidence ledger contract'
} finally {
    Remove-Item -LiteralPath $temp -Recurse -Force -ErrorAction SilentlyContinue
}
