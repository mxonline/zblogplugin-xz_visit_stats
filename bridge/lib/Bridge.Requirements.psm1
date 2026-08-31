Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-RequirementEnvelope {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$BusinessGoal,
        [Parameter(Mandatory = $true)][string]$UserOutcome,
        [string[]]$InScope = @(),
        [string[]]$OutOfScope = @(),
        [string]$Compatibility = 'INFER_FROM_PROJECT',
        [string]$SchemaImpact = 'UNKNOWN_REQUIRES_ANALYSIS',
        [string]$SecurityPrivacyImpact = 'PRESERVE_EXISTING',
        [string[]]$AcceptanceCriteria = @('REQUIREMENT_GATE'),
        [string]$ReleaseTarget = 'FOLLOW_PROJECT_RELEASE_PLAN',
        [ValidateSet('NOT_REQUIRED','REQUIRED','COMPLETED')][string]$ReuseGate = 'NOT_REQUIRED',
        [string[]]$UnresolvedBusinessDecisions = @(),
        [string]$SafetyScope = 'AUTHORIZED_LOCAL_DEV'
    )

    return [pscustomobject]@{
        business_goal = $BusinessGoal
        user_outcome = $UserOutcome
        in_scope = @($InScope)
        out_of_scope = @($OutOfScope)
        compatibility = $Compatibility
        schema_impact = $SchemaImpact
        security_privacy_impact = $SecurityPrivacyImpact
        acceptance_criteria = @($AcceptanceCriteria)
        release_target = $ReleaseTarget
        reuse_gate = $ReuseGate
        unresolved_business_decisions = @($UnresolvedBusinessDecisions)
        safety_scope = $SafetyScope
    }
}

function Test-RequirementGate {
    [CmdletBinding()]
    param([Parameter(Mandatory = $true)]$Requirement)

    $required = @(
        'business_goal',
        'user_outcome',
        'in_scope',
        'out_of_scope',
        'compatibility',
        'schema_impact',
        'security_privacy_impact',
        'acceptance_criteria',
        'release_target',
        'reuse_gate',
        'unresolved_business_decisions',
        'safety_scope'
    )

    $missing = New-Object System.Collections.Generic.List[string]
    foreach ($name in $required) {
        $property = $Requirement.PSObject.Properties[$name]
        if ($null -eq $property) {
            [void]$missing.Add($name)
            continue
        }

        if ($name -in @('business_goal','user_outcome','compatibility','schema_impact','security_privacy_impact','release_target','reuse_gate','safety_scope')) {
            if ([string]::IsNullOrWhiteSpace([string]$Requirement.$name)) {
                [void]$missing.Add($name)
            }
        }
    }

    if ($missing.Count -gt 0) {
        return [pscustomobject]@{
            status = 'INVALID_REQUIREMENT'
            missing_fields = @($missing)
            reason = 'Requirement envelope is incomplete.'
        }
    }

    $businessDecisions = @($Requirement.unresolved_business_decisions)
    if ($businessDecisions.Count -gt 0) {
        return [pscustomobject]@{
            status = 'BLOCKED_BUSINESS_DECISION'
            missing_fields = @()
            reason = 'A user-visible business choice cannot be safely inferred.'
            decisions = $businessDecisions
        }
    }

    if ([string]$Requirement.safety_scope -match '^(UNAUTHORIZED|DESTRUCTIVE|PRODUCTION_RISK)') {
        return [pscustomobject]@{
            status = 'BLOCKED_SAFETY_SCOPE'
            missing_fields = @()
            reason = 'Requested mutation is outside the authorized safe development scope.'
        }
    }

    if ([string]$Requirement.reuse_gate -eq 'REQUIRED') {
        return [pscustomobject]@{
            status = 'NEEDS_REUSE_GATE'
            missing_fields = @()
            reason = 'Reusable subsystem decision requires GitHub/official ecosystem Reuse Gate before mutation.'
        }
    }

    return [pscustomobject]@{
        status = 'PASS'
        missing_fields = @()
        reason = 'Requirement Gate satisfied.'
    }
}

Export-ModuleMember -Function Get-RequirementEnvelope, Test-RequirementGate
