. "$PSScriptRoot/Assert.ps1"
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.RuntimeGates.psm1" -Force
$root=(Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$sha=((& git -C $root rev-parse HEAD) | Out-String).Trim()
$evidenceRoot=Join-Path $env:TEMP ('gate-evidence-'+[Guid]::NewGuid().ToString('N'))
try {
  $context=[pscustomobject]@{request_id='GATE-NEGATIVE';branch='feature/gpt-codex-bridge-v1';stage='T4_ANALYTICS_ADMIN';head_sha=$sha}
  $noRuntime=Invoke-BridgeLiveGate -Gate 'LOCAL_RUNTIME' -Context $context -WorkspaceRoot $root -EvidenceRoot $evidenceRoot -Repository 'mxonline/zblogplugin-xz_visit_stats'
  Assert-Equal 'BLOCKED' $noRuntime.status 'cloud CI cannot fake local runtime'
  Assert-True (-not [string]::IsNullOrWhiteSpace($noRuntime.evidence_id)) 'negative gate has evidence ID'
  $wrong=$context | Select-Object *
  $wrong.head_sha='bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
  $mismatch=Invoke-BridgeLiveGate -Gate 'UNIT_TEST' -Context $wrong -WorkspaceRoot $root -EvidenceRoot $evidenceRoot -Repository 'mxonline/zblogplugin-xz_visit_stats'
  Assert-Equal 'BLOCKED' $mismatch.status 'wrong SHA cannot pass any gate'
} finally {
  Remove-Item -LiteralPath $evidenceRoot -Recurse -Force -ErrorAction SilentlyContinue
}
Write-Host 'PASS: runtime gates fail closed without Windows runtime evidence'
