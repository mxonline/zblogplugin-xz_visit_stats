Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Get-BridgeHandoffValue {
    param(
        $Object,
        [Parameter(Mandatory = $true)][string]$Name
    )

    if ($null -eq $Object) { return $null }
    if ($null -eq $Object.PSObject.Properties[$Name]) { return $null }
    return $Object.$Name
}

function New-BridgeHandoffResult {
    param(
        [Parameter(Mandatory = $true)][string]$Status,
        [Parameter(Mandatory = $true)][string]$ReasonCode,
        [Parameter(Mandatory = $true)]$Handoff
    )

    return [pscustomobject]@{
        status = $Status
        reason_code = $ReasonCode
        handoff = $Handoff
    }
}

function Test-BridgeHandoff {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory = $true)]$Current,
        [Parameter(Mandatory = $true)]$Incoming
    )

    $currentBranch = [string](Get-BridgeHandoffValue -Object $Current -Name 'branch')
    $incomingBranch = [string](Get-BridgeHandoffValue -Object $Incoming -Name 'branch')
    if ($incomingBranch -ne $currentBranch) {
        return New-BridgeHandoffResult -Status 'BLOCKED_STALE_HANDOFF' -ReasonCode 'BRANCH_MISMATCH' -Handoff $Current
    }

    $currentSha = [string](Get-BridgeHandoffValue -Object $Current -Name 'head_sha')
    $incomingSha = [string](Get-BridgeHandoffValue -Object $Incoming -Name 'head_sha')
    if ($incomingSha -ne $currentSha) {
        return New-BridgeHandoffResult -Status 'BLOCKED_STALE_HANDOFF' -ReasonCode 'HEAD_SHA_MISMATCH' -Handoff $Current
    }

    $currentThread = [string](Get-BridgeHandoffValue -Object $Current -Name 'thread_id')
    $incomingThread = [string](Get-BridgeHandoffValue -Object $Incoming -Name 'thread_id')
    if (-not [string]::IsNullOrWhiteSpace($currentThread) -and $incomingThread -ne $currentThread) {
        return New-BridgeHandoffResult -Status 'BLOCKED_STALE_HANDOFF' -ReasonCode 'THREAD_ID_MISMATCH' -Handoff $Current
    }

    $currentVerified = @((Get-BridgeHandoffValue -Object $Current -Name 'verified_gates'))
    $incomingVerified = @((Get-BridgeHandoffValue -Object $Incoming -Name 'verified_gates'))
    foreach ($gate in $currentVerified) {
        if ($null -eq $gate) { continue }
        if ($incomingVerified -notcontains [string]$gate) {
            return New-BridgeHandoffResult -Status 'BLOCKED_VERIFIED_REGRESSION' -ReasonCode 'VERIFIED_GATE_DROPPED' -Handoff $Current
        }
    }

    $nextAction = [string](Get-BridgeHandoffValue -Object $Incoming -Name 'next_action')
    if ($nextAction -match '(?i)ask\s+user|copy\s+command|codex\s+ui') {
        return New-BridgeHandoffResult -Status 'BLOCKED_ZERO_TOUCH_REGRESSION' -ReasonCode 'MANUAL_USER_HANDOFF_FORBIDDEN' -Handoff $Current
    }

    return New-BridgeHandoffResult -Status 'PASS' -ReasonCode 'HANDOFF_VALID' -Handoff $Incoming
}

Export-ModuleMember -Function Test-BridgeHandoff
