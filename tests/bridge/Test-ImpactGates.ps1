. "$PSScriptRoot/Assert.ps1"
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Impact.psm1" -Force
Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Baseline.psm1" -Force
Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Diff.psm1" -Force

$requirement = [pscustomobject]@{
  business_goal = 'Finish T4 admin analytics'
  schema_impact = 'NONE'
  expected_change_paths = @('inc/admin-v4.php','assets/admin-v4.js','tests/V4AnalyticsTest.php')
  reviewed_additional_paths = @([pscustomobject]@{ path='inc/v4-query.php'; reason='required new bounded reporting query' })
  locked_baselines = @('T2','T3')
}
$impact = Get-ChangeImpact -Requirement $requirement
Assert-Equal 'PASS' $impact.status 'impact is computed'
Assert-True $impact.runtime_required 'admin analytics requires real Z-Blog runtime'
$baseline = Get-BaselineInheritance -VerifiedStages @('T2','T3') -ChangedPaths @('inc/admin-v4.php') -ProtectedSurfaces @{ T2=@('docs/v4.0.0/SCHEMA-AUDIT-v1.0.md'); T3=@('inc/session.php','inc/migrate-v4.php') }
Assert-Equal 'PASS' $baseline.status 'unaffected verified stages inherited'
Assert-Contains $baseline.inherited_stages 'T2' 'T2 preserved'
Assert-Contains $baseline.inherited_stages 'T3' 'T3 preserved'
$impacting = Get-BaselineInheritance -VerifiedStages @('T2','T3') -ChangedPaths @('inc/migrate-v4.php') -ProtectedSurfaces @{ T2=@('docs/v4.0.0/SCHEMA-AUDIT-v1.0.md'); T3=@('inc/migrate-v4.php') }
Assert-Equal 'REVIEW_REQUIRED' $impacting.status 'touching T3 verified surface requires review'
Assert-Contains $impacting.affected_stages 'T3' 'T3 impact flagged'
$plan = Get-ExpectedDiff -Requirement $requirement
$pass = Test-ExpectedDiff -ActualPaths @('inc/admin-v4.php','inc/v4-query.php','assets/admin-v4.js') -Plan $plan
Assert-Equal 'PASS' $pass.status 'reviewed extra path allowed'
$unexpected = Test-ExpectedDiff -ActualPaths @('inc/admin-v4.php','inc/surprise.php') -Plan $plan
Assert-Equal 'REVIEW_REQUIRED' $unexpected.status 'unplanned addition not silently accepted'
$core = Test-ExpectedDiff -ActualPaths @('zb_system/functions/c_system_base.php') -Plan $plan
Assert-Equal 'BLOCKED' $core.status 'Z-Blog core always protected'
$other = Test-ExpectedDiff -ActualPaths @('zb_users/plugin/unrelated/include.php') -Plan $plan
Assert-Equal 'BLOCKED' $other.status 'other plugins protected'
$pathEscape = Test-ExpectedDiff -ActualPaths @('../secrets.php') -Plan $plan
Assert-Equal 'BLOCKED' $pathEscape.status 'path traversal protected'
Write-Host 'PASS: release-first impact baseline expected-diff gates'
