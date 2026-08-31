Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$commonModule = Join-Path $PSScriptRoot 'Bridge.Common.psm1'
Import-Module $commonModule -Force

$script:BridgeTransitions = @{
    'IDLE'                       = @('CONTEXT_SYNC', 'BLOCKED', 'FAILED')
    'CONTEXT_SYNC'               = @('RESUME_GATE', 'BLOCKED', 'FAILED')
    'RESUME_GATE'                = @('REQUIREMENT_GATE', 'BLOCKED', 'FAILED')
    'REQUIREMENT_GATE'           = @('REUSE_GATE', 'CHANGE_IMPACT_GATE', 'BLOCKED', 'FAILED')
    'REUSE_GATE'                 = @('CHANGE_IMPACT_GATE', 'BLOCKED', 'FAILED')
    'CHANGE_IMPACT_GATE'         = @('BASELINE_INHERITANCE_GATE', 'BLOCKED', 'FAILED')
    'BASELINE_INHERITANCE_GATE'  = @('EXPECTED_DIFF_GATE', 'BLOCKED', 'FAILED')
    'EXPECTED_DIFF_GATE'         = @('LANE_ROUTE', 'BLOCKED', 'FAILED')
    'LANE_ROUTE'                 = @('TASK_DISPATCH', 'BLOCKED', 'FAILED')
    'TASK_DISPATCH'              = @('CODEX_RUNNING', 'BLOCKED', 'FAILED')
    'CODEX_RUNNING'              = @('CODEX_TURN_COMPLETED', 'BLOCKED', 'FAILED')
    'CODEX_TURN_COMPLETED'       = @('RESULT_COLLECT', 'BLOCKED', 'FAILED')
    'RESULT_COLLECT'             = @('GPT_REVIEW', 'BLOCKED', 'FAILED')
    'GPT_REVIEW'                 = @('GPT_DECISION', 'BLOCKED', 'FAILED')
    'GPT_DECISION'               = @('TASK_DISPATCH', 'REPAIR', 'REVERIFY', 'UNIT_TEST', 'LOCAL_RUNTIME', 'SQL_EXPLAIN', 'GITHUB_CI', 'CANDIDATE_BUILD', 'FINAL_RUNTIME', 'RELEASE_GATE', 'BLOCKED', 'FAILED')
    'REPAIR'                     = @('TASK_DISPATCH', 'BLOCKED', 'FAILED')
    'REVERIFY'                   = @('UNIT_TEST', 'LOCAL_RUNTIME', 'SQL_EXPLAIN', 'GITHUB_CI', 'TASK_DISPATCH', 'BLOCKED', 'FAILED')
    'UNIT_TEST'                  = @('LOCAL_RUNTIME', 'SQL_EXPLAIN', 'GITHUB_CI', 'GPT_REVIEW', 'BLOCKED', 'FAILED')
    'LOCAL_RUNTIME'              = @('SQL_EXPLAIN', 'GITHUB_CI', 'GPT_REVIEW', 'BLOCKED', 'FAILED')
    'SQL_EXPLAIN'                = @('GITHUB_CI', 'GPT_REVIEW', 'BLOCKED', 'FAILED')
    'GITHUB_CI'                  = @('GPT_REVIEW', 'CANDIDATE_BUILD', 'FINAL_RUNTIME', 'RELEASE_GATE', 'BLOCKED', 'FAILED')
    'CANDIDATE_BUILD'            = @('FINAL_RUNTIME', 'GITHUB_CI', 'GPT_REVIEW', 'BLOCKED', 'FAILED')
    'FINAL_RUNTIME'              = @('RELEASE_GATE', 'GPT_REVIEW', 'BLOCKED', 'FAILED')
    'RELEASE_GATE'               = @('ROLLBACK_GATE', 'GPT_REVIEW', 'BLOCKED', 'FAILED')
    'ROLLBACK_GATE'              = @('VERSION_CONSISTENCY_GATE', 'GPT_REVIEW', 'BLOCKED', 'FAILED')
    'VERSION_CONSISTENCY_GATE'   = @('RELEASE', 'GPT_REVIEW', 'BLOCKED', 'FAILED')
    'RELEASE'                    = @('NOTION_WRITEBACK', 'GPT_REVIEW', 'BLOCKED', 'FAILED')
    'NOTION_WRITEBACK'           = @('PROJECT_STATE_WRITEBACK', 'BLOCKED', 'FAILED')
    'PROJECT_STATE_WRITEBACK'    = @('PLUGIN_RELEASED', 'BLOCKED', 'FAILED')
    'PLUGIN_RELEASED'            = @()
    'BLOCKED'                    = @('CONTEXT_SYNC', 'RESUME_GATE', 'FAILED')
    'FAILED'                     = @()
}

function Test-BridgeTransition {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$From,
        [Parameter(Mandatory = $true)][string]$To
    )

    if (-not $script:BridgeTransitions.ContainsKey($From)) { return $false }
    return ($script:BridgeTransitions[$From] -contains $To)
}

function Get-BridgeState {
    [CmdletBinding()]
    param([Parameter(Mandatory = $true)][string]$Path)
    return Read-BridgeJson -Path $Path
}

function Set-BridgeState {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)]$State,
        [Parameter(Mandatory = $true)][string]$NextStatus,
        [string]$NextAction,
        [string]$BlockedReason
    )

    $current = [string]$State.status
    if (-not (Test-BridgeTransition -From $current -To $NextStatus)) {
        throw "Illegal bridge transition: $current -> $NextStatus"
    }

    $State.status = $NextStatus
    if (-not [string]::IsNullOrWhiteSpace($NextAction)) {
        $State.next_action = $NextAction
    }
    if ($PSBoundParameters.ContainsKey('BlockedReason')) {
        $State.blocked_reason = $BlockedReason
    }
    $State.updated_at = Get-UtcIsoTimestamp
    Write-BridgeJsonAtomic -Path $Path -Value $State
    return $State
}

function Assert-ZeroTouchStateInvariant {
    [CmdletBinding()]
    param([Parameter(Mandatory = $true)]$State)

    if ([string]$State.status -eq 'CODEX_TURN_COMPLETED' -and [string]$State.next_action -match '(?i)user|manual|codex ui|copy|paste|continue') {
        throw 'Zero-touch invariant violated: Codex turn completion cannot hand control to the user.'
    }

    if ([string]$State.status -eq 'PLUGIN_RELEASED' -and [string]$State.stage -notmatch '(?i)release|complete|released') {
        throw 'PLUGIN_RELEASED requires a release-complete stage marker.'
    }

    return $true
}

Export-ModuleMember -Function Test-BridgeTransition, Get-BridgeState, Set-BridgeState, Assert-ZeroTouchStateInvariant
