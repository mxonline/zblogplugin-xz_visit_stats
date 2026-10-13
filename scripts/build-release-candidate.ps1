[CmdletBinding()]
param([string]$OutputPath = '')

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$sourceRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
if ([string]::IsNullOrWhiteSpace($OutputPath)) { $OutputPath = Join-Path $sourceRoot 'dist\xz_visit_stats-v4.0.0.zip' }
$distRoot = (Join-Path $sourceRoot 'dist')
if (-not (Test-Path -LiteralPath $distRoot)) { New-Item -ItemType Directory -Path $distRoot | Out-Null }
$distRoot = (Resolve-Path -LiteralPath $distRoot).Path
$OutputPath = [System.IO.Path]::GetFullPath($OutputPath)
if (-not $OutputPath.StartsWith($distRoot + [System.IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Release ZIP must be written under the repository dist directory.'
}

$explicit = @(
    'plugin.xml', 'README.md', 'THIRD-PARTY-NOTICES.md', 'main.php', 'include.php', 'realtime_api.php', 'rum.php', 'event.php',
    'docs/VERSION.md', 'docs/CHANGELOG.md', 'docs/RELEASE_NOTES_v4.0.0.md', 'docs/v4.0.0/UPGRADE-v3-to-v4.md',
    'data/exports/.htaccess', 'data/exports/index.php', 'data/exports/web.config',
    'vendor/mlocati/ip-lib/ip-lib.php', 'vendor/mlocati/ip-lib/LICENSE.txt'
)
$files = [System.Collections.Generic.HashSet[string]]::new([StringComparer]::OrdinalIgnoreCase)
foreach ($path in $explicit) {
    if (-not (Test-Path -LiteralPath (Join-Path $sourceRoot $path) -PathType Leaf)) { throw "Required release file is missing: $path" }
    [void]$files.Add($path.Replace('\', '/'))
}
foreach ($directory in @('inc', 'assets', 'vendor/mlocati/ip-lib/src', 'third-party-licenses')) {
    $root = Join-Path $sourceRoot $directory
    if (-not (Test-Path -LiteralPath $root -PathType Container)) { throw "Required release directory is missing: $directory" }
    foreach ($file in Get-ChildItem -LiteralPath $root -File -Force -Recurse) {
        $relative = $file.FullName.Substring($sourceRoot.Length).TrimStart('\', '/')
        [void]$files.Add($relative.Replace('\', '/'))
    }
}
$ordered = @($files | Sort-Object)
if ($ordered.Count -eq 0) { throw 'Release allowlist is empty.' }

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zipPath = $OutputPath
$parent = Split-Path -Parent $zipPath
if (-not (Test-Path -LiteralPath $parent)) { New-Item -ItemType Directory -Path $parent | Out-Null }
if (Test-Path -LiteralPath $zipPath) { Remove-Item -LiteralPath $zipPath -Force }
$archive = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($relative in $ordered) {
        $entryName = 'xz_visit_stats/' + $relative
        $entry = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
        $input = [System.IO.File]::OpenRead((Join-Path $sourceRoot ($relative.Replace('/', '\'))))
        $output = $entry.Open()
        try { $input.CopyTo($output) } finally { $output.Dispose(); $input.Dispose() }
    }
} finally {
    $archive.Dispose()
}

$tempRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
$verifyRoot = [System.IO.Path]::GetFullPath((Join-Path $tempRoot ('xzvs-release-verify-' + [Guid]::NewGuid().ToString('N'))))
if (-not $verifyRoot.StartsWith($tempRoot.TrimEnd('\', '/') + [System.IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Verification directory escaped the system temp directory.' }
if (Test-Path -LiteralPath $verifyRoot) { throw 'Verification directory unexpectedly exists.' }
try {
    [System.IO.Compression.ZipFile]::ExtractToDirectory($zipPath, $verifyRoot)
    $extractedRoot = Join-Path $verifyRoot 'xz_visit_stats'
    $actualFiles = @(Get-ChildItem -LiteralPath $extractedRoot -File -Force -Recurse | ForEach-Object { $_.FullName.Substring($extractedRoot.Length).TrimStart('\', '/').Replace('\', '/') } | Sort-Object)
    if ($actualFiles.Count -ne $ordered.Count -or (Compare-Object $ordered $actualFiles)) { throw 'Archive entries do not match the explicit allowlist.' }
    foreach ($relative in $ordered) {
        $sourceFile = Join-Path $sourceRoot ($relative.Replace('/', '\'))
        $archiveFile = Join-Path $extractedRoot ($relative.Replace('/', '\'))
        if ((Get-FileHash -LiteralPath $sourceFile -Algorithm SHA256).Hash -ne (Get-FileHash -LiteralPath $archiveFile -Algorithm SHA256).Hash) { throw "Archive content hash mismatch: $relative" }
    }
    if ($actualFiles -match '(^|/)(\.git|\.github|tests|scripts|tools|phpstan|cache|backup|config)(/|$)|(^|/)(composer\.(json|lock)|phpunit\.xml|phpstan\.neon)$') {
        throw 'Archive includes a forbidden development, cache, backup, or configuration path.'
    }
    $hash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash
    [pscustomobject]@{status='PASS';file=(Split-Path -Leaf $zipPath);sha256=$hash;files=$ordered.Count;allowlist_matches=$true;source_hashes_match=$true} | ConvertTo-Json -Compress
} finally {
    if (Test-Path -LiteralPath $verifyRoot) { Remove-Item -LiteralPath $verifyRoot -Recurse -Force }
}
