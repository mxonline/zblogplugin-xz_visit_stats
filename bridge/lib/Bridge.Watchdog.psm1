Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-BridgeWatchdogValue {
    param(
        $Object,
        [Parameter(Mandatory = $true)][string]$Name
    )

    if ($null -eq $Object) { return $null }
    if ($null -eq $Object.PSObject.Properties[$Name]) { return $null }
    return $Object.$Name
}

function New-BridgeWatchdogDecision {
    param(
        [Parameter(Mandatory = $true)][string]$Action,
        [Parameter(Mandatory = $true)][string]$ReasonCode,
        [Parameter(Mandatory = $true)]$State
    )

    return [pscustomobject]@{
        action = $Action
        reason_code = $ReasonCode
        stage = [string](Get-BridgeWatchdogValue -Object $State -Name 'stage')
        next_action = [string](Get-BridgeWatchdogValue -Object $State -Name 'next_action')
    }
}

function Get-BridgeWatchdogDecision {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]$State,
        [Parameter(Mandatory = $true)][DateTimeOffset]$Now,
        [int]$StallSeconds = 300
    )

    $status = [string](Get-BridgeWatchdogValue -Object $State -Name 'status')
    if ($status -eq 'QUOTA_EXHAUSTED') {
        $resumeAfterRaw = [string](Get-BridgeWatchdogValue -Object $State -Name 'resume_after')
        if (-not [string]::IsNullOrWhiteSpace($resumeAfterRaw)) {
            $resumeAfter = [DateTimeOffset]::Parse($resumeAfterRaw)
            if ($Now -lt $resumeAfter) {
                return New-BridgeWatchdogDecision -Action 'WAIT_QUOTA' -ReasonCode 'QUOTA_WINDOW_ACTIVE' -State $State
            }
            return New-BridgeWatchdogDecision -Action 'RESUME_CHECKPOINT' -ReasonCode 'QUOTA_WINDOW_ELAPSED' -State $State
        }
    }

    $processAlive = [bool](Get-BridgeWatchdogValue -Object $State -Name 'process_alive')
    if (-not $processAlive) {
        return New-BridgeWatchdogDecision -Action 'RECOVER_EXECUTOR' -ReasonCode 'APP_SERVER_EXITED' -State $State
    }

    $transportConnected = [bool](Get-BridgeWatchdogValue -Object $State -Name 'transport_connected')
    if (-not $transportConnected) {
        return New-BridgeWatchdogDecision -Action 'RECOVER_EXECUTOR' -ReasonCode 'APP_SERVER_DISCONNECTED' -State $State
    }

    $heartbeatRaw = [string](Get-BridgeWatchdogValue -Object $State -Name 'last_heartbeat')
    if (-not [string]::IsNullOrWhiteSpace($heartbeatRaw)) {
        $heartbeat = [DateTimeOffset]::Parse($heartbeatRaw)
        if (($Now - $heartbeat).TotalSeconds -gt $StallSeconds) {
            return New-BridgeWatchdogDecision -Action 'RECOVER_TURN' -ReasonCode 'TURN_STALLED' -State $State
        }
    }

    return New-BridgeWatchdogDecision -Action 'CONTINUE' -ReasonCode 'HEALTHY' -State $State
}

Export-ModuleMember -Function Get-BridgeWatchdogDecision
