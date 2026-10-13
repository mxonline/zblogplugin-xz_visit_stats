[CmdletBinding()]
param(
    [string]$SiteRoot = 'D:\wwwroot\www.xzhao.net',
    [string]$PluginName = 'xz_visit_stats',
    [string]$ZipPath = (Join-Path $PSScriptRoot '..\dist\xz_visit_stats-v4.0.0.zip'),
    [string]$BackupParent = 'D:\wwwroot\xz_visit_stats-runtime-backups'
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$siteRoot = (Resolve-Path -LiteralPath $SiteRoot).Path
$runtimeRoot = (Resolve-Path -LiteralPath (Join-Path $siteRoot ('zb_users\plugin\' + $PluginName))).Path
$zipPath = (Resolve-Path -LiteralPath $ZipPath).Path
$backupParent = (Resolve-Path -LiteralPath $BackupParent).Path
if (-not $runtimeRoot.StartsWith($siteRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Runtime plugin path escaped the local site root.'
}

$backupPath = Join-Path $backupParent ('xz_visit_stats.backup-t5-pre-candidate-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
if (Test-Path -LiteralPath $backupPath) { throw 'Refusing to overwrite an existing runtime backup.' }
New-Item -ItemType Directory -Path $backupPath | Out-Null
$currentFiles = @(Get-ChildItem -LiteralPath $runtimeRoot -File -Force -Recurse)
foreach ($file in $currentFiles) {
    $relative = $file.FullName.Substring($runtimeRoot.Length).TrimStart('\', '/')
    $destination = Join-Path $backupPath $relative
    $destinationParent = Split-Path -Parent $destination
    if (-not (Test-Path -LiteralPath $destinationParent)) {
        New-Item -ItemType Directory -Path $destinationParent -Force | Out-Null
    }
    Copy-Item -LiteralPath $file.FullName -Destination $destination
}
$backupMismatch = @()
foreach ($file in $currentFiles) {
    $relative = $file.FullName.Substring($runtimeRoot.Length).TrimStart('\', '/')
    $backupFile = Join-Path $backupPath $relative
    if ((Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash -ne (Get-FileHash -LiteralPath $backupFile -Algorithm SHA256).Hash) {
        $backupMismatch += $relative
    }
}
if ($backupMismatch.Count) { throw ('Backup hash mismatch: ' + ($backupMismatch -join ', ')) }

$tempRoot = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
$stageRoot = [IO.Path]::GetFullPath((Join-Path $tempRoot ('xzvs-install-stage-' + [Guid]::NewGuid().ToString('N'))))
if (-not $stageRoot.StartsWith($tempRoot.TrimEnd('\', '/') + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Stage path escaped the system temp directory.'
}
New-Item -ItemType Directory -Path $stageRoot | Out-Null
[IO.Compression.ZipFile]::ExtractToDirectory($zipPath, $stageRoot)
$packageRoot = (Resolve-Path -LiteralPath (Join-Path $stageRoot $PluginName)).Path
$archive = [IO.Compression.ZipFile]::OpenRead($zipPath)
try {
    $entries = @($archive.Entries | Where-Object { $_.Name } | ForEach-Object { $_.FullName })
}
finally { $archive.Dispose() }
if ($entries.Count -ne 94 -or @($entries | Where-Object { $_ -notmatch '^xz_visit_stats/(?!\.\.)[^/]+(?:/[^/]+)*$' }).Count) {
    throw 'Unexpected ZIP manifest or unsafe archive entry.'
}

$installed = 0
$overwritten = 0
foreach ($entry in $entries) {
    $relative = $entry.Substring(($PluginName + '/').Length).Replace('/', '\')
    $source = Join-Path $packageRoot $relative
    $destination = [IO.Path]::GetFullPath((Join-Path $runtimeRoot $relative))
    if (-not $destination.StartsWith($runtimeRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Package entry escaped the plugin directory.'
    }
    $existed = Test-Path -LiteralPath $destination -PathType Leaf
    $destinationParent = Split-Path -Parent $destination
    if (-not (Test-Path -LiteralPath $destinationParent)) {
        New-Item -ItemType Directory -Path $destinationParent -Force | Out-Null
    }
    Copy-Item -LiteralPath $source -Destination $destination -Force
    if ((Get-FileHash -LiteralPath $source -Algorithm SHA256).Hash -ne (Get-FileHash -LiteralPath $destination -Algorithm SHA256).Hash) {
        throw ('Installed file hash mismatch: ' + $relative)
    }
    $installed++
    if ($existed) { $overwritten++ }
}

$runtimeFileCount = @(Get-ChildItem -LiteralPath $runtimeRoot -File -Force -Recurse).Count
if ($runtimeFileCount -lt $installed) { throw 'Installed runtime file count is unexpectedly low.' }
$zipHash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash
[pscustomobject]@{
    status = 'PASS'
    backup = (Split-Path -Leaf $backupPath)
    backup_files = $currentFiles.Count
    backup_hashes_match = $true
    package_files = $entries.Count
    installed_files = $installed
    overwritten_files = $overwritten
    runtime_extras_preserved = ($runtimeFileCount - $installed)
    zip_sha256 = $zipHash
    runtime_file_hashes_match = $true
} | ConvertTo-Json -Compress
