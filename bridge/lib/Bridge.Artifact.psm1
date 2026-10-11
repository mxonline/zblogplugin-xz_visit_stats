Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'

function Test-ReleaseArtifactHash {
    [CmdletBinding()]
    param([Parameter(Mandatory=$true)]$Manifest,[Parameter(Mandatory=$true)][string]$ReleaseArtifactPath)
    if (-not (Test-Path -LiteralPath $ReleaseArtifactPath -PathType Leaf)) {
        return [pscustomobject]@{ status='BLOCKED'; reason='RELEASE_ARTIFACT_MISSING' }
    }
    $hash=(Get-FileHash -LiteralPath $ReleaseArtifactPath -Algorithm SHA256).Hash.ToLowerInvariant()
    $expected=[string]$Manifest.artifact_sha256
    return [pscustomobject]@{
        status=$(if ($hash -eq $expected -and $hash -match '^[a-f0-9]{64}$') {'PASS'} else {'BLOCKED'})
        expected_sha256=$expected; observed_sha256=$hash
        reason=$(if ($hash -eq $expected) {'ARTIFACT_IDENTITY_VERIFIED'} else {'ARTIFACT_SHA256_MISMATCH'})
    }
}
function Test-VersionConsistency {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)][string]$PluginVersion,
        [Parameter(Mandatory=$true)][string]$ChangeLogVersion,
        [Parameter(Mandatory=$true)][string]$TagVersion,
        [Parameter(Mandatory=$true)][string]$ManifestVersion,
        [Parameter(Mandatory=$true)][string]$ReleaseVersion
    )
    $target=$ManifestVersion.TrimStart('v')
    $values=@($PluginVersion,$ChangeLogVersion,$TagVersion,$ReleaseVersion) |
        ForEach-Object { ([string]$_).TrimStart('v') }
    $consistent=($target -match '^\d+\.\d+\.\d+' -and @($values | Where-Object { $_ -ne $target }).Count -eq 0)
    return [pscustomobject]@{
        status=$(if ($consistent) {'PASS'} else {'BLOCKED'})
        reason=$(if ($consistent) {'VERSION_IDENTITY_VERIFIED'} else {'VERSION_MISMATCH'})
        expected_version=$target; observed_versions=@($values)
    }
}
Export-ModuleMember -Function Test-ReleaseArtifactHash,Test-VersionConsistency
