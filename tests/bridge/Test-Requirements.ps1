. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Requirements.psm1" -Force

$base = [pscustomobject]@{
    business_goal = 'Resume xz_visit_stats v4 T4 analytics/admin work to the next verified gate.'
    user_outcome = 'T4 completes without reopening verified T2/T3.'
    in_scope = @('T4 analytics/admin')
    out_of_scope = @('T2','T3','premature release')
    compatibility = 'INFER_FROM_PROJECT'
    schema_impact = 'NONE_EXPECTED'
    security_privacy_impact = 'PRESERVE_EXISTING'
    acceptance_criteria = @('UNIT_TEST','LOCAL_RUNTIME','SQL_EXPLAIN','GITHUB_CI')
    release_target = 'CONTINUE_TO_PROJECT_RELEASE_PLAN'
    reuse_gate = 'COMPLETED'
    unresolved_business_decisions = @()
    safety_scope = 'AUTHORIZED_LOCAL_DEV'
}

$pass = Test-RequirementGate -Requirement $base
Assert-Equal 'PASS' $pass.status 'complete T4 requirement passes'

$reuse = $base.PSObject.Copy()
$reuse.reuse_gate = 'REQUIRED'
$reuseResult = Test-RequirementGate -Requirement $reuse
Assert-Equal 'NEEDS_REUSE_GATE' $reuseResult.status 'new reusable subsystem requires reuse gate'

$businessConflict = $base.PSObject.Copy()
$businessConflict.unresolved_business_decisions = @('Whether the user-facing behavior should delete history or preserve history')
$conflictResult = Test-RequirementGate -Requirement $businessConflict
Assert-Equal 'BLOCKED_BUSINESS_DECISION' $conflictResult.status 'real user-visible conflict blocks mutation'

$safety = $base.PSObject.Copy()
$safety.safety_scope = 'UNAUTHORIZED_PRODUCTION_MUTATION'
$safetyResult = Test-RequirementGate -Requirement $safety
Assert-Equal 'BLOCKED_SAFETY_SCOPE' $safetyResult.status 'unauthorized production scope blocks mutation'

$missing = [pscustomobject]@{ business_goal = 'missing contract' }
$missingResult = Test-RequirementGate -Requirement $missing
Assert-Equal 'INVALID_REQUIREMENT' $missingResult.status 'missing required contract is invalid, not guessed'

Write-Host 'PASS: requirement gate contract'
