Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-ContextValue {
    param(
        [Parameter(Mandatory = $true)]$Context,
        [Parameter(Mandatory = $true)][string]$Name,
        $Default = $null
    )
    $property = $Context.PSObject.Properties[$Name]
    if ($null -eq $property) { return $Default }
    return $property.Value
}

function Get-RollbackGate {
    [CmdletBinding()]
    param([Parameter(Mandatory = $true)]$Context)

    $lane = [string](Get-ContextValue -Context $Context -Name 'lane' -Default '')
    $schemaChange = [bool](Get-ContextValue -Context $Context -Name 'schema_change' -Default $false)
    $forwardOnly = [bool](Get-ContextValue -Context $Context -Name 'forward_only' -Default $false)

    if ($lane -eq 'DOC_ONLY' -and -not $schemaChange) {
        return [pscustomobject]@{
            status = 'NOT_REQUIRED'
            reason = 'Documentation-only change has no runtime/schema rollback surface.'
            recovery_mode = 'NONE'
        }
    }

    $previousTag = [string](Get-ContextValue -Context $Context -Name 'previous_release_tag' -Default '')
    $previousArtifact = [string](Get-ContextValue -Context $Context -Name 'previous_artifact_sha256' -Default '')
    $backupEvidence = [string](Get-ContextValue -Context $Context -Name 'backup_evidence_id' -Default '')
    $rollbackVerified = [bool](Get-ContextValue -Context $Context -Name 'rollback_path_verified' -Default $false)
    $forwardFixVerified = [bool](Get-ContextValue -Context $Context -Name 'forward_fix_verified' -Default $false)
    $forwardDocumented = [bool](Get-ContextValue -Context $Context -Name 'forward_only_documented' -Default $false)

    $releaseLike = $lane -in @('MAJOR_VERSION','SCHEMA_CHANGE','RELEASE')
    if (-not $releaseLike -and -not $schemaChange) {
        return [pscustomobject]@{
            status = 'NOT_REQUIRED'
            reason = 'Lane does not require release rollback evidence.'
            recovery_mode = 'NONE'
        }
    }

    if ([string]::IsNullOrWhiteSpace($previousTag) -or [string]::IsNullOrWhiteSpace($previousArtifact)) {
        return [pscustomobject]@{
            status = 'BLOCKED'
            reason = 'Previous verified release tag/artifact identity is missing.'
            recovery_mode = 'UNPROVEN'
        }
    }

    if ($schemaChange -and [string]::IsNullOrWhiteSpace($backupEvidence)) {
        return [pscustomobject]@{
            status = 'BLOCKED'
            reason = 'Schema-changing release requires verified backup/recovery evidence.'
            recovery_mode = 'UNPROVEN'
        }
    }

    if ($forwardOnly) {
        if (-not [string]::IsNullOrWhiteSpace($backupEvidence) -and $forwardFixVerified -and $forwardDocumented) {
            return [pscustomobject]@{
                status = 'PASS_FORWARD_ONLY'
                reason = 'Reverse downgrade is unsafe, but backup plus documented verified forward-fix recovery is available.'
                recovery_mode = 'FORWARD_ONLY'
                previous_release_tag = $previousTag
                previous_artifact_sha256 = $previousArtifact
                backup_evidence_id = $backupEvidence
            }
        }

        return [pscustomobject]@{
            status = 'BLOCKED'
            reason = 'Forward-only release lacks verified backup/forward-fix/documentation evidence.'
            recovery_mode = 'UNPROVEN'
        }
    }

    if (-not $rollbackVerified) {
        return [pscustomobject]@{
            status = 'BLOCKED'
            reason = 'Rollback path has not been verified.'
            recovery_mode = 'UNPROVEN'
        }
    }

    if ([string]::IsNullOrWhiteSpace($backupEvidence) -and $lane -eq 'MAJOR_VERSION') {
        return [pscustomobject]@{
            status = 'BLOCKED'
            reason = 'Major-version release requires a recovery checkpoint even without a schema change.'
            recovery_mode = 'UNPROVEN'
        }
    }

    return [pscustomobject]@{
        status = 'PASS'
        reason = 'Previous release artifact and recovery path are verified.'
        recovery_mode = 'ROLLBACK'
        previous_release_tag = $previousTag
        previous_artifact_sha256 = $previousArtifact
        backup_evidence_id = $backupEvidence
    }
}

Export-ModuleMember -Function Get-RollbackGate
