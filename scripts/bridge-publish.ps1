[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$WorkspaceRoot,
    [Parameter(Mandatory=$true)][string]$RequestId,
    [Parameter(Mandatory=$true)][string]$ExpectedSha,
    [Parameter(Mandatory=$true)][string]$TargetVersion,
    [Parameter(Mandatory=$true)][string]$EvidenceRoot
)
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
$bridgeRoot=(Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Import-Module (Join-Path $bridgeRoot 'bridge/lib/Bridge.Candidate.psm1') -Force
Import-Module (Join-Path $bridgeRoot 'bridge/lib/Bridge.Artifact.psm1') -Force
Import-Module (Join-Path $bridgeRoot 'bridge/lib/Bridge.Notion.psm1') -Force
Import-Module (Join-Path $bridgeRoot 'bridge/lib/Bridge.Evidence.psm1') -Force
$repo='mxonline/zblogplugin-xz_visit_stats'
$tag='v'+$TargetVersion.TrimStart('v')
$zip=Join-Path $WorkspaceRoot ('dist/xz_visit_stats-'+$TargetVersion.TrimStart('v')+'.zip')
$notes=Join-Path $WorkspaceRoot 'CHANGELOG.md'
if (-not (Test-Path -LiteralPath $zip -PathType Leaf)) { throw 'BLOCKED_RELEASE: candidate ZIP missing.' }
if (-not (Test-Path -LiteralPath $notes -PathType Leaf)) { throw 'BLOCKED_RELEASE: CHANGELOG missing.' }
$manifestPath=Join-Path $WorkspaceRoot 'dist/candidate-manifest.json'
if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) { throw 'BLOCKED_RELEASE: candidate manifest missing.' }
$manifest=Get-Content -LiteralPath $manifestPath -Raw -Encoding UTF8 | ConvertFrom-Json
if ($manifest.commit_sha -ne $ExpectedSha -or $manifest.version -ne $TargetVersion.TrimStart('v')) {
    throw 'BLOCKED_RELEASE: manifest does not bind exact version and SHA.'
}
$localHash=Test-ReleaseArtifactHash -Manifest $manifest -ReleaseArtifactPath $zip
if ($localHash.status -ne 'PASS') { throw 'BLOCKED_RELEASE: candidate ZIP checksum mismatch.' }
$required=@('UNIT_TEST','LOCAL_RUNTIME','SQL_EXPLAIN','GITHUB_CI','FINAL_RUNTIME','RELEASE_GATE','ROLLBACK_GATE','VERSION_CONSISTENCY_GATE')
foreach ($gate in $required) {
    $records=@(Get-ChildItem -LiteralPath $EvidenceRoot -Filter 'EVD-*.json' -File -ErrorAction SilentlyContinue |
        ForEach-Object { Get-Content -LiteralPath $_.FullName -Raw -Encoding UTF8 | ConvertFrom-Json } |
        Where-Object { $_.gate -eq $gate -and $_.head_sha -eq $ExpectedSha -and $_.status -eq 'PASS' })
    if ($records.Count -eq 0) { throw ('BLOCKED_RELEASE: missing exact-SHA gate '+$gate) }
}
$pluginXml=Join-Path $WorkspaceRoot 'plugin.xml'
if (-not (Test-Path -LiteralPath $pluginXml)) { throw 'BLOCKED_RELEASE: plugin.xml missing.' }
[xml]$xml=Get-Content -LiteralPath $pluginXml -Raw -Encoding UTF8
$pluginVersion=[string]$xml.plugin.version
if ($pluginVersion -ne $TargetVersion.TrimStart('v')) { throw 'BLOCKED_RELEASE: plugin.xml version mismatch.' }
if (-not (Select-String -Path $notes -Pattern ([regex]::Escape($TargetVersion.TrimStart('v'))) -Quiet)) { throw 'BLOCKED_RELEASE: changelog version missing.' }
$actualSha=((& git -C $WorkspaceRoot rev-parse HEAD) | Out-String).Trim()
if ($LASTEXITCODE -ne 0 -or $actualSha -ne $ExpectedSha) { throw 'BLOCKED_RELEASE: checkout is not candidate SHA.' }
$dirty=((& git -C $WorkspaceRoot status --porcelain) | Out-String).Trim()
if (-not [string]::IsNullOrWhiteSpace($dirty)) { throw 'BLOCKED_RELEASE: worktree dirty.' }
& git -C $WorkspaceRoot fetch origin main --tags --quiet
if ($LASTEXITCODE -ne 0) { throw 'BLOCKED_RELEASE: cannot verify remote main.' }
$mainSha=((& git -C $WorkspaceRoot rev-parse refs/remotes/origin/main) | Out-String).Trim()
if ($mainSha -ne $ExpectedSha) { throw 'BLOCKED_RELEASE: candidate is not the verified main HEAD.' }
$localTag=((& git -C $WorkspaceRoot tag --list $tag) | Out-String).Trim()
if (-not [string]::IsNullOrWhiteSpace($localTag)) {
    $tagSha=((& git -C $WorkspaceRoot rev-parse ($tag+'^{commit}')) | Out-String).Trim()
    if ($tagSha -ne $ExpectedSha) { throw 'BLOCKED_RELEASE: existing tag points at another commit.' }
} else {
    & git -C $WorkspaceRoot tag -a $tag $ExpectedSha -m ("xz_visit_stats "+$tag)
    if ($LASTEXITCODE -ne 0) { throw 'BLOCKED_RELEASE: cannot create tag.' }
    & git -C $WorkspaceRoot push origin ('refs/tags/'+$tag) | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'BLOCKED_RELEASE: cannot push tag.' }
}
$existing=& gh release view $tag --repo $repo --json tagName,isDraft,publishedAt 2>$null
if ($LASTEXITCODE -ne 0) {
    & gh release create $tag $zip --repo $repo --verify-tag --title ('xz_visit_stats '+$tag) --notes-file $notes | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'BLOCKED_RELEASE: GitHub Release create failed.' }
}
$release=(& gh release view $tag --repo $repo --json tagName,isDraft,publishedAt | Out-String | ConvertFrom-Json)
if ($release.tagName -ne $tag -or $release.isDraft -or [string]::IsNullOrWhiteSpace($release.publishedAt)) {
    throw 'BLOCKED_RELEASE: GitHub Release not actually published.'
}
$downloadDir=Join-Path $env:TEMP ('bridge-release-'+[Guid]::NewGuid().ToString('N'))
New-Item -Path $downloadDir -ItemType Directory -Force | Out-Null
try {
    & gh release download $tag --repo $repo --pattern ([IO.Path]::GetFileName($zip)) --dir $downloadDir | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'BLOCKED_RELEASE: release asset download failed.' }
    $remoteZip=Join-Path $downloadDir ([IO.Path]::GetFileName($zip))
    $artifact=Test-ReleaseArtifactHash -Manifest $manifest -ReleaseArtifactPath $remoteZip
    if ($artifact.status -ne 'PASS') { throw 'BLOCKED_RELEASE: remote GitHub ZIP SHA256 mismatch.' }
} finally { Remove-Item -LiteralPath $downloadDir -Recurse -Force -ErrorAction SilentlyContinue }
$payload=[pscustomobject]@{
    request_id=$RequestId;stage='RELEASE';status='RELEASED';sha=$ExpectedSha
    gates=$required;release_state='RELEASED';timestamp=[DateTime]::UtcNow.ToString('o')
}
$writeback=Write-NotionStageUpdate -Payload $payload
if ($writeback.status -ne 'PASS') { throw 'BLOCKED_WRITEBACK: Notion write/readback failed.' }
$stateFile=Join-Path $WorkspaceRoot 'knowledge/PROJECT-STATE.md'
if (-not (Test-Path -LiteralPath $stateFile -PathType Leaf)) { throw 'BLOCKED_WRITEBACK: project state file missing.' }
$marker=('BRIDGE_RELEASED|'+$RequestId+'|'+$tag+'|'+$ExpectedSha)
$stateText=Get-Content -LiteralPath $stateFile -Raw -Encoding UTF8
if (-not $stateText.Contains($marker)) {
    [IO.File]::AppendAllText($stateFile, ("\n## Verified Bridge release - "+([DateTime]::UtcNow.ToString('o'))+"\n"+$marker+"\nGitHub Release: "+$tag+"; ZIP SHA256: "+$manifest.artifact_sha256+"; Notion marker: "+$writeback.marker+"\n"),(New-Object System.Text.UTF8Encoding($false)))
    & git -C $WorkspaceRoot add -- knowledge/PROJECT-STATE.md
    & git -C $WorkspaceRoot commit -m ('docs(release): record verified '+$tag+' release') | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'BLOCKED_WRITEBACK: project-state commit failed.' }
    & git -C $WorkspaceRoot push origin HEAD:main | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'BLOCKED_WRITEBACK: project-state push failed.' }
}
$back=((& git -C $WorkspaceRoot show 'origin/main:knowledge/PROJECT-STATE.md') | Out-String)
if ($back -notlike ('*'+$marker+'*')) { throw 'BLOCKED_WRITEBACK: project-state remote readback missing.' }
[pscustomobject]@{
    status='PASS';release_created=$true;tag_verified=$true;zip_hash_verified=$true
    local_runtime_verified=$true;ci_verified=$true;rollback_verified=$true
    notion_verified=$true;project_state_verified=$true;release_sha=$ExpectedSha
    tag=$tag;artifact_sha256=$manifest.artifact_sha256
} | ConvertTo-Json -Depth 8 -Compress
