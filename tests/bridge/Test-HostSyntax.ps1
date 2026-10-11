. "$PSScriptRoot/Assert.ps1"
Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
$root=(Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$paths=@('scripts/bridge-host.ps1','scripts/bridge-publish.ps1','bridge/lib/Bridge.Runner.psm1','bridge/lib/Bridge.RuntimeGates.psm1','bridge/lib/Bridge.Candidate.psm1','bridge/lib/Bridge.Artifact.psm1','bridge/lib/Bridge.Diff.psm1')
foreach($path in $paths) {
  $errors=$null
  $null=[System.Management.Automation.Language.Parser]::ParseFile((Join-Path $root $path),[ref]$null,[ref]$errors)
  Assert-Equal 0 @($errors).Count ('PowerShell syntax '+$path)
}
Write-Host 'PASS: unattended host and publish scripts parse with Windows PowerShell 5.1'
