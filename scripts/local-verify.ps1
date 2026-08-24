[CmdletBinding()]
param(
    [string]$PhpExe = 'D:\BtSoft\php\83\php.exe',
    [string]$SiteUrl = 'http://127.0.0.1',
    [int]$HttpTimeoutSec = 20,
    [switch]$SkipHttp,
    [switch]$SkipPhpUnit
)

Set-StrictMode -Version 2.0
$ErrorActionPreference = 'Stop'

$PluginRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path

function Write-Step {
    param([string]$Message)
    Write-Host "`n==> $Message"
}

function Resolve-PhpExecutable {
    param([string]$Preferred)

    if ($Preferred -and (Test-Path -LiteralPath $Preferred -PathType Leaf)) {
        return (Resolve-Path -LiteralPath $Preferred).Path
    }

    $php = Get-Command php -ErrorAction SilentlyContinue
    if ($null -ne $php) {
        return $php.Source
    }

    throw "PHP CLI was not found. Pass -PhpExe with the actual php.exe path."
}

function Invoke-CheckedCommand {
    param(
        [Parameter(Mandatory = $true)][string]$FilePath,
        [Parameter(Mandatory = $false)][string[]]$Arguments = @(),
        [Parameter(Mandatory = $false)][string]$WorkingDirectory = $PluginRoot
    )

    Push-Location $WorkingDirectory
    try {
        & $FilePath @Arguments
        $code = $LASTEXITCODE
        if ($null -eq $code) { $code = 0 }
        if ($code -ne 0) {
            throw "Command failed with exit code ${code}: $FilePath $($Arguments -join ' ')"
        }
    }
    finally {
        Pop-Location
    }
}

function Invoke-HttpStatus {
    param(
        [Parameter(Mandatory = $true)][string]$Uri,
        [hashtable]$Headers = @{}
    )

    try {
        $response = Invoke-WebRequest -Uri $Uri -Headers $Headers -UseBasicParsing -TimeoutSec $HttpTimeoutSec
        return [int]$response.StatusCode
    }
    catch [System.Net.WebException] {
        if ($null -ne $_.Exception.Response) {
            return [int]$_.Exception.Response.StatusCode
        }
        throw
    }
}

$phpExeResolved = Resolve-PhpExecutable -Preferred $PhpExe

Write-Host "xz_visit_stats local verification"
Write-Host "Plugin root: $PluginRoot"
Write-Host "PHP:         $phpExeResolved"
Write-Host "Site:        $SiteUrl"

Write-Step 'Git diff whitespace check'
Invoke-CheckedCommand -FilePath 'git' -Arguments @('diff', '--check')

Write-Step 'PHP syntax check'
$phpFiles = Get-ChildItem -LiteralPath $PluginRoot -Recurse -File -Filter '*.php' |
    Where-Object {
        $_.FullName -notmatch '[\\/]vendor[\\/]' -and
        $_.FullName -notmatch '[\\/]\.git[\\/]'
    }

if (-not $phpFiles) {
    throw 'No PHP files were found in the plugin worktree.'
}

foreach ($file in $phpFiles) {
    & $phpExeResolved -l $file.FullName | Out-Host
    if ($LASTEXITCODE -ne 0) {
        throw "PHP syntax check failed: $($file.FullName)"
    }
}

if (-not $SkipPhpUnit) {
    Write-Step 'PHPUnit'
    $phpUnitBat = Join-Path $PluginRoot 'vendor\bin\phpunit.bat'
    $phpUnitPhar = Join-Path $PluginRoot 'phpunit.phar'

    if (Test-Path -LiteralPath $phpUnitBat -PathType Leaf) {
        Invoke-CheckedCommand -FilePath 'cmd.exe' -Arguments @('/d', '/c', $phpUnitBat, '--testsuite', 'default')
    }
    elseif (Test-Path -LiteralPath $phpUnitPhar -PathType Leaf) {
        Invoke-CheckedCommand -FilePath $phpExeResolved -Arguments @($phpUnitPhar)
    }
    else {
        $phpUnitCommand = Get-Command phpunit -ErrorAction SilentlyContinue
        if ($null -ne $phpUnitCommand) {
            Invoke-CheckedCommand -FilePath $phpUnitCommand.Source
        }
        else {
            Write-Host 'PHPUnit: SKIP (no local executable found)'
        }
    }
}

if (-not $SkipHttp) {
    Write-Step 'Local HTTP smoke test'
    $base = $SiteUrl.TrimEnd('/')

    $homeStatus = Invoke-HttpStatus -Uri ($base + '/')
    if ($homeStatus -lt 200 -or $homeStatus -ge 400) {
        throw "Homepage smoke test failed with HTTP $homeStatus"
    }
    Write-Host "Homepage: HTTP $homeStatus"

    $notFoundStatus = Invoke-HttpStatus -Uri ($base + '/__xz_visit_stats_runtime_404__')
    if ($notFoundStatus -ne 404) {
        throw "Expected page-type 404 request to return HTTP 404, got $notFoundStatus"
    }
    Write-Host "404 probe: HTTP $notFoundStatus"

    $headers = @{
        'User-Agent' = 'Baiduspider/2.0 (+https://www.baidu.com/search/spider.html)'
        'Referer' = 'https://www.baidu.com/s?wd=xz_visit_stats_runtime_test'
    }
    $botStatus = Invoke-HttpStatus -Uri ($base + '/') -Headers $headers
    if ($botStatus -lt 200 -or $botStatus -ge 400) {
        throw "Bot/Referer smoke request failed with HTTP $botStatus"
    }
    Write-Host "Bot/Referer probe: HTTP $botStatus"
}

Write-Host "`nLOCAL VERIFY: PASS"
Write-Host 'Note: runtime-sensitive tasks still require the database/Hook assertions in docs/TESTING.md.'
