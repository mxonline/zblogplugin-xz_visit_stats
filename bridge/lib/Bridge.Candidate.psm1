Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'

function New-CandidateManifest {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)][string]$Project,
        [Parameter(Mandatory=$true)][string]$Version,
        [Parameter(Mandatory=$true)][string]$Branch,
        [Parameter(Mandatory=$true)][string]$HeadSha,
        [Parameter(Mandatory=$true)][string]$ArtifactPath,
        [string[]]$RequiredGates=@(),
        [string[]]$ChangedFiles=@(),
        [string]$SchemaVersion=''
    )
    if ($HeadSha -notmatch '^[0-9a-f]{40}$') { throw 'Candidate requires an exact 40-character Git commit SHA.' }
    if ($Version -notmatch '^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9._-]+)?$') { throw 'Candidate version is not semantic.' }
    if (-not (Test-Path -LiteralPath $ArtifactPath -PathType Leaf)) { throw 'Candidate artifact does not exist.' }
    $artifact=[System.IO.Path]::GetFullPath($ArtifactPath)
    $checksum=(Get-FileHash -LiteralPath $artifact -Algorithm SHA256).Hash.ToLowerInvariant()
    return [pscustomobject]@{
        schema_version='1.0'; project=$Project; version=$Version; branch=$Branch
        commit_sha=$HeadSha; changed_files=@($ChangedFiles); db_schema_version=$SchemaVersion
        required_gates=@($RequiredGates); artifact_sha256=$checksum; artifact_filename=[IO.Path]::GetFileName($artifact)
        created_at=[DateTime]::UtcNow.ToString('o')
    }
}
function Test-CandidateIdentity {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)]$Manifest,
        [Parameter(Mandatory=$true)][string]$RuntimeSha,
        [Parameter(Mandatory=$true)][string]$CiSha,
        [string[]]$EvidenceGates=@()
    )
    $missing=@($Manifest.required_gates | Where-Object { $_ -notin @($EvidenceGates) })
    $reason=@()
    if ([string]$Manifest.commit_sha -ne $RuntimeSha) { $reason+='RUNTIME_SHA_MISMATCH' }
    if ([string]$Manifest.commit_sha -ne $CiSha) { $reason+='CI_SHA_MISMATCH' }
    if ($missing.Count -gt 0) { $reason+='MISSING_REQUIRED_GATES' }
    return [pscustomobject]@{
        status=$(if ($reason.Count -eq 0) {'PASS'} else {'BLOCKED'})
        reasons=@($reason); missing_gates=@($missing)
        next_action=$(if ($reason.Count -eq 0) {'VERSION_CONSISTENCY_GATE'} else {'REVERIFY_CANDIDATE'})
    }
}
Export-ModuleMember -Function New-CandidateManifest,Test-CandidateIdentity
