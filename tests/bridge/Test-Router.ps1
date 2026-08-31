. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Router.psm1" -Force

$cases = @(
    @{ name='docs'; input=[pscustomobject]@{ docs_only=$true }; lane='DOC_ONLY' },
    @{ name='pure bug'; input=[pscustomobject]@{ isolated_pure_logic=$true }; lane='FAST_FIX' },
    @{ name='normal feature'; input=[pscustomobject]@{ feature=$true }; lane='NORMAL_FEATURE' },
    @{ name='admin runtime'; input=[pscustomobject]@{ admin_ui=$true; request_lifecycle=$true }; lane='RUNTIME_FEATURE' },
    @{ name='migration'; input=[pscustomobject]@{ schema_change=$true }; lane='SCHEMA_CHANGE' },
    @{ name='v4 major'; input=[pscustomobject]@{ major_version=$true; admin_ui=$true; schema_change=$true }; lane='MAJOR_VERSION' },
    @{ name='release'; input=[pscustomobject]@{ release_operation=$true }; lane='RELEASE' }
)

foreach ($case in $cases) {
    $route = Get-DevelopmentLane -Task $case.input
    Assert-Equal $case.lane $route.lane ("route " + $case.name)
    Assert-True ($route.required_gates.Count -ge 1) ("gates assigned " + $case.name)
}

$currentT4 = Get-DevelopmentLane -Task ([pscustomobject]@{
    major_version = $true
    admin_ui = $true
    request_lifecycle = $true
    sql_explain_required = $true
    current_stage = 'T4_ANALYTICS_ADMIN'
})
Assert-Equal 'MAJOR_VERSION' $currentT4.lane 'current T4 is major-version lane'
Assert-Contains $currentT4.required_gates 'UNIT_TEST' 'T4 unit gate'
Assert-Contains $currentT4.required_gates 'LOCAL_RUNTIME' 'T4 runtime gate'
Assert-Contains $currentT4.required_gates 'SQL_EXPLAIN' 'T4 SQL gate'
Assert-Contains $currentT4.required_gates 'GITHUB_CI' 'T4 CI gate'

$strict = Get-DevelopmentLane -Task ([pscustomobject]@{ isolated_pure_logic=$true; schema_change=$true })
Assert-Equal 'SCHEMA_CHANGE' $strict.lane 'stricter schema lane wins over fast-fix signal'

Write-Host 'PASS: development lane router contract'
