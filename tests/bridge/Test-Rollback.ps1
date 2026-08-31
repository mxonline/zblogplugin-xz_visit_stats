. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Rollback.psm1" -Force

$majorNoRecovery = Get-RollbackGate -Context ([pscustomobject]@{
    lane = 'MAJOR_VERSION'
    previous_release_tag = $null
    previous_artifact_sha256 = $null
    backup_evidence_id = $null
    schema_change = $false
    forward_only = $false
})
Assert-Equal 'BLOCKED' $majorNoRecovery.status 'major release cannot skip rollback evidence'

$verified = Get-RollbackGate -Context ([pscustomobject]@{
    lane = 'MAJOR_VERSION'
    previous_release_tag = 'v3.0.0'
    previous_artifact_sha256 = 'abc123'
    backup_evidence_id = 'EVD-backup'
    rollback_path_verified = $true
    schema_change = $false
    forward_only = $false
})
Assert-Equal 'PASS' $verified.status 'known-good artifact and verified recovery path pass rollback gate'

$schemaUnsafe = Get-RollbackGate -Context ([pscustomobject]@{
    lane = 'SCHEMA_CHANGE'
    previous_release_tag = 'v3.0.0'
    previous_artifact_sha256 = 'abc123'
    backup_evidence_id = $null
    rollback_path_verified = $true
    schema_change = $true
    forward_only = $false
})
Assert-Equal 'BLOCKED' $schemaUnsafe.status 'schema change requires backup/recovery evidence'

$forwardOnly = Get-RollbackGate -Context ([pscustomobject]@{
    lane = 'MAJOR_VERSION'
    previous_release_tag = 'v3.0.0'
    previous_artifact_sha256 = 'abc123'
    backup_evidence_id = 'EVD-db-backup'
    rollback_path_verified = $false
    schema_change = $true
    forward_only = $true
    forward_fix_verified = $true
    forward_only_documented = $true
})
Assert-Equal 'PASS_FORWARD_ONLY' $forwardOnly.status 'irreversible migration requires verified forward-only recovery'

$docs = Get-RollbackGate -Context ([pscustomobject]@{ lane='DOC_ONLY'; schema_change=$false })
Assert-Equal 'NOT_REQUIRED' $docs.status 'docs-only change does not require rollback gate'

Write-Host 'PASS: rollback gate contract'
