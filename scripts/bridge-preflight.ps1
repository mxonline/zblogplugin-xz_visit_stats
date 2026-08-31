param(
    [string]$RepositoryRoot,
    [string]$ConfigPath,
    [switch]$ReadOnly,
    [switch]$SkipCredentialChecks,
    [switch]$SkipRuntimeChecks,
    [switch]$AsJson
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

if ([string]::IsNullOrWhiteSpace($RepositoryRoot)) {
    $RepositoryRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
}

Import-Module (Join-Path $RepositoryRoot 'bridge/lib/Bridge.Preflight.psm1') -Force

$result = Invoke-BridgePreflight `
    -RepositoryRoot $RepositoryRoot `
    -ConfigPath $ConfigPath `
    -SkipCredentialChecks:$SkipCredentialChecks `
    -SkipRuntimeChecks:$SkipRuntimeChecks

if ($AsJson) {
    $result | ConvertTo-Json -Depth 10
} else {
    $result | Format-List
}

if ($result.status -eq 'PASS') {
    exit 0
}

exit 2
