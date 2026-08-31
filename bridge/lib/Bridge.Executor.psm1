Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module (Join-Path $PSScriptRoot 'Bridge.Approval.psm1')
Import-Module (Join-Path $PSScriptRoot 'Bridge.Watchdog.psm1')

function New-BridgeAppServerApprovalHandler {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]$Policy,
        [scriptblock]$AuditSink
    )

    $localPolicy = $Policy
    $localAuditSink = $AuditSink

    $handler = {
        param($Request)

        $resolution = Resolve-BridgeApprovalRequest -Request $Request -Policy $localPolicy
        if ($null -ne $localAuditSink) {
            & $localAuditSink $resolution
        }

        if ($null -eq $resolution.response) {
            throw ("BRIDGE_APPROVAL_BLOCKED|{0}|{1}" -f [string]$resolution.status, [string]$resolution.reason_code)
        }

        return $resolution.response
    }.GetNewClosure()

    return $handler
}

function New-BridgeExecutorResult {
    param(
        [Parameter(Mandatory = $true)][string]$Status,
        [Parameter(Mandatory = $true)][string]$Transport,
        [Parameter(Mandatory = $true)][int]$RecoveryCount,
        [Parameter(Mandatory = $true)][string]$WatchdogAction,
        [Parameter(Mandatory = $true)][string]$NextAction,
        $Raw,
        [AllowNull()][string]$ErrorMessage
    )

    $completed = $false
    $output = $null
    if ($null -ne $Raw) {
        if ($null -ne $Raw.PSObject.Properties['completed']) {
            $completed = [bool]$Raw.completed
        } elseif ($null -ne $Raw.PSObject.Properties['exit_code']) {
            $completed = ([int]$Raw.exit_code -eq 0)
        }
        if ($null -ne $Raw.PSObject.Properties['output']) {
            $output = $Raw.output
        }
    }

    return [pscustomobject]@{
        status = $Status
        completed = $completed
        output = $output
        executor_transport = $Transport
        recovery_count = $RecoveryCount
        watchdog_action = $WatchdogAction
        external_input_count = 0
        next_action = $NextAction
        error = $ErrorMessage
        raw = $Raw
    }
}

function Get-BridgeExecutorWatchdogState {
    param([Parameter(Mandatory = $true)]$Context)

    $stage = if ($null -ne $Context.PSObject.Properties['stage']) { [string]$Context.stage } else { 'UNKNOWN_STAGE' }
    $nextAction = if ($null -ne $Context.PSObject.Properties['next_action']) { [string]$Context.next_action } else { 'WAIT_CODEX_TURN' }

    return [pscustomobject]@{
        status = 'CODEX_EXECUTION'
        stage = $stage
        next_action = $nextAction
        last_heartbeat = [DateTimeOffset]::Now.ToString('o')
        process_alive = $false
        transport_connected = $false
        resume_after = $null
    }
}

function Test-BridgeExecutorSuccess {
    param($Result)

    if ($null -eq $Result) { return $false }
    if ($null -ne $Result.PSObject.Properties['completed']) {
        return [bool]$Result.completed
    }
    if ($null -ne $Result.PSObject.Properties['exit_code']) {
        return ([int]$Result.exit_code -eq 0)
    }
    return $true
}

function Invoke-BridgeExecutorTurn {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)][string]$Prompt,
        [Parameter(Mandatory = $true)]$Context,
        [Parameter(Mandatory = $true)]$ApprovalPolicy,
        [Parameter(Mandatory = $true)][scriptblock]$AppServerInvoker,
        [Parameter(Mandatory = $true)][scriptblock]$AppServerRecover,
        [Parameter(Mandatory = $true)][scriptblock]$ExecFallback,
        [scriptblock]$ApprovalAuditSink
    )

    $approvalHandler = New-BridgeAppServerApprovalHandler -Policy $ApprovalPolicy -AuditSink $ApprovalAuditSink
    $recoveryCount = 0
    $watchdogAction = 'CONTINUE'
    $lastError = $null

    try {
        $appResult = & $AppServerInvoker $Prompt $Context $approvalHandler
        if (Test-BridgeExecutorSuccess -Result $appResult) {
            return New-BridgeExecutorResult -Status 'PASS' -Transport 'codex_app_server' -RecoveryCount 0 -WatchdogAction 'CONTINUE' -NextAction 'RESULT_COLLECT' -Raw $appResult -ErrorMessage $null
        }
        $lastError = 'Codex App Server returned an incomplete or failed turn.'
    } catch {
        $lastError = $_.Exception.Message
    }

    $watchdogState = Get-BridgeExecutorWatchdogState -Context $Context
    $watchdog = Get-BridgeWatchdogDecision -State $watchdogState -Now ([DateTimeOffset]::Now) -StallSeconds 300
    $watchdogAction = [string]$watchdog.action

    if ($watchdogAction -eq 'RECOVER_EXECUTOR') {
        $recovered = $false
        try {
            $recovered = [bool](& $AppServerRecover $Context)
        } catch {
            $lastError = $_.Exception.Message
            $recovered = $false
        }

        if ($recovered) {
            $recoveryCount++
            try {
                $retryResult = & $AppServerInvoker $Prompt $Context $approvalHandler
                if (Test-BridgeExecutorSuccess -Result $retryResult) {
                    return New-BridgeExecutorResult -Status 'PASS' -Transport 'codex_app_server' -RecoveryCount $recoveryCount -WatchdogAction $watchdogAction -NextAction 'RESULT_COLLECT' -Raw $retryResult -ErrorMessage $null
                }
                $lastError = 'Recovered Codex App Server returned an incomplete or failed turn.'
            } catch {
                $lastError = $_.Exception.Message
            }
        }
    }

    try {
        $fallbackResult = & $ExecFallback $Prompt $Context
        if ($null -ne $fallbackResult -and $null -ne $fallbackResult.PSObject.Properties['exit_code'] -and [int]$fallbackResult.exit_code -eq 0) {
            return New-BridgeExecutorResult -Status 'PASS' -Transport 'codex_exec_fallback' -RecoveryCount $recoveryCount -WatchdogAction $watchdogAction -NextAction 'RESULT_COLLECT' -Raw $fallbackResult -ErrorMessage $null
        }
        if ($null -ne $fallbackResult -and $null -ne $fallbackResult.PSObject.Properties['output']) {
            $lastError = (@($fallbackResult.output) -join "`n")
        } else {
            $lastError = 'Codex exec fallback failed.'
        }
    } catch {
        $fallbackResult = $null
        $lastError = $_.Exception.Message
    }

    return New-BridgeExecutorResult -Status 'BLOCKED_EXECUTOR_TRANSPORT' -Transport 'not_started' -RecoveryCount $recoveryCount -WatchdogAction $watchdogAction -NextAction 'GPT_REVIEW_BLOCKED' -Raw $fallbackResult -ErrorMessage $lastError
}

Export-ModuleMember -Function New-BridgeAppServerApprovalHandler, Invoke-BridgeExecutorTurn
