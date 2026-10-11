. "$PSScriptRoot/Assert.ps1"
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Candidate.psm1" -Force
Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Artifact.psm1" -Force
$root=Join-Path $env:TEMP ('bridge-candidate-'+[Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $root -Force | Out-Null
try {
    $zip=Join-Path $root 'candidate.zip'
    [System.IO.File]::WriteAllText($zip,'reproducible dummy payload')
    $sha='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
    $manifest=New-CandidateManifest -Project 'xz_visit_stats' -Version '4.0.0' -Branch 'feature/visit-stats-4.0' -HeadSha $sha -ArtifactPath $zip -RequiredGates @('UNIT_TEST','LOCAL_RUNTIME','SQL_EXPLAIN','GITHUB_CI')
    Assert-Equal $sha $manifest.commit_sha 'candidate binds commit SHA'
    Assert-Equal 64 $manifest.artifact_sha256.Length 'candidate SHA256 generated'
    $ok=Test-CandidateIdentity -Manifest $manifest -RuntimeSha $sha -CiSha $sha -EvidenceGates @('UNIT_TEST','LOCAL_RUNTIME','SQL_EXPLAIN','GITHUB_CI')
    Assert-Equal 'PASS' $ok.status 'exact SHA + required evidence bound'
    $bad=Test-CandidateIdentity -Manifest $manifest -RuntimeSha $sha -CiSha 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' -EvidenceGates @('UNIT_TEST','LOCAL_RUNTIME','SQL_EXPLAIN','GITHUB_CI')
    Assert-Equal 'BLOCKED' $bad.status 'wrong CI sha blocked'
    $missing=Test-CandidateIdentity -Manifest $manifest -RuntimeSha $sha -CiSha $sha -EvidenceGates @('UNIT_TEST','GITHUB_CI')
    Assert-Equal 'BLOCKED' $missing.status 'CI cannot replace runtime SQL'
    $fileOk=Test-ReleaseArtifactHash -Manifest $manifest -ReleaseArtifactPath $zip
    Assert-Equal 'PASS' $fileOk.status 'identical release artifact allowed'
    [System.IO.File]::WriteAllText($zip,'tampered artifact')
    $tampered=Test-ReleaseArtifactHash -Manifest $manifest -ReleaseArtifactPath $zip
    Assert-Equal 'BLOCKED' $tampered.status 'tampered ZIP blocked'
    $versions=Test-VersionConsistency -PluginVersion '4.0.0' -ChangeLogVersion '4.0.0' -TagVersion 'v4.0.0' -ManifestVersion '4.0.0' -ReleaseVersion '4.0.0'
    Assert-Equal 'PASS' $versions.status 'all release version surfaces agree'
    $mismatch=Test-VersionConsistency -PluginVersion '3.0.0' -ChangeLogVersion '4.0.0' -TagVersion 'v4.0.0' -ManifestVersion '4.0.0' -ReleaseVersion '4.0.0'
    Assert-Equal 'BLOCKED' $mismatch.status 'version skew blocked'
} finally { Remove-Item -LiteralPath $root -Recurse -Force -ErrorAction SilentlyContinue }
Write-Host 'PASS: candidate manifest and artifact identity contract'
