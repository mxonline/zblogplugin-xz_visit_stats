Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$tests = Get-ChildItem -LiteralPath $PSScriptRoot -Filter 'Test-*.ps1' -File | Sort-Object Name
if ($tests.Count -eq 0) {
    throw 'No bridge tests found.'
}

foreach ($test in $tests) {
    Write-Host "==> $($test.Name)"
    & powershell -NoProfile -ExecutionPolicy Bypass -File $test.FullName
    if ($LASTEXITCODE -ne 0) {
        throw "Bridge test failed: $($test.Name) (exit $LASTEXITCODE)"
    }
}

Write-Host "PASS: $($tests.Count) bridge test file(s)"
