Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot 'Bridge.Common.psm1')

function Save-BridgeRun {
    param([Parameter(Mandatory=$true)][string]$Path,[Parameter(Mandatory=$true)]$State)
    $State.updated_at=[DateTime]::UtcNow.ToString('o')
    Write-BridgeJsonAtomic -Path $Path -Value $State
}
function Get-BridgeSafeResult {
    param($Result)
    $json=$Result | ConvertTo-Json -Depth 32 -Compress
    $safe=Protect-BridgeEvidence -Text $json
    return ($safe | ConvertFrom-Json)
}
function Get-BridgeResultProperty {
    param($Object,[string]$Name)
    if ($null -eq $Object -or $null -eq $Object.PSObject.Properties[$Name]) { return $null }
    return $Object.$Name
}
function Set-BridgeRunBlocked {
    param($State,[string]$Path,[string]$Reason)
    $State.status='BLOCKED'
    $State.reason=$Reason
    $State.next_action='RESUME_GATE_AFTER_BLOCKER_RESOLVED'
    Save-BridgeRun -Path $Path -State $State
    return $State
}
function Invoke-BridgeAutonomousRun {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)][string]$BusinessGoal,
        [Parameter(Mandatory=$true)][string]$RequestId,
        [Parameter(Mandatory=$true)][string]$Stage,
        [Parameter(Mandatory=$true)][string]$Branch,
        [Parameter(Mandatory=$true)][string]$HeadSha,
        [Parameter(Mandatory=$true)][string]$CheckpointPath,
        [Parameter(Mandatory=$true)][string]$InitialPrompt,
        [Parameter(Mandatory=$true)][string[]]$RequiredGates,
        [Parameter(Mandatory=$true)][scriptblock]$CodexExecutor,
        [Parameter(Mandatory=$true)][scriptblock]$GptReviewer,
        [Parameter(Mandatory=$true)][scriptblock]$GateVerifier,
        [Parameter(Mandatory=$true)][scriptblock]$ReleasePublisher,
        [int]$MaxSteps=100
    )
    if ($HeadSha -notmatch '^[0-9a-f]{40}$') { throw 'Expected exact Git SHA.' }
    if ($MaxSteps -lt 1) { throw 'MaxSteps must be positive.' }
    if (Test-Path -LiteralPath $CheckpointPath) {
        $state=Read-BridgeJson -Path $CheckpointPath
        if ($state.request_id -ne $RequestId -or $state.business_goal -ne $BusinessGoal -or $state.branch -ne $Branch) {
            throw 'Existing checkpoint identity differs; refusing to overwrite another execution.'
        }
        if ($state.status -eq 'PLUGIN_RELEASED' -or $state.status -eq 'BLOCKED') { return $state }
        if ($state.status -eq 'WAITING_QUOTA') {
            $state.status='IN_PROGRESS'
            $state.reason=$null
            Save-BridgeRun -Path $CheckpointPath -State $state
        }
        if ($state.phase -eq 'CODEX_RUNNING') {
            # A crashed executor may have already edited files. Do not replay blindly:
            # dispatch a reconciliation-only instruction that preserves prior work.
            $state.pending_prompt='Inspect the actual Git tree and current runtime first. Reconcile the previous incomplete turn and continue the same request without overwriting work. ' + $state.pending_prompt
            $state.phase='DISPATCH'
            Save-BridgeRun -Path $CheckpointPath -State $state
        }
    } else {
        $state=[pscustomobject]@{
            schema_version='1.0'; request_id=$RequestId; business_goal=$BusinessGoal
            stage=$Stage; branch=$Branch; head_sha=$HeadSha
            required_gates=@($RequiredGates); phase='DISPATCH'; status='IN_PROGRESS'
            pending_prompt=$InitialPrompt; last_execution=$null; last_decision=$null
            controller_response_id=$null; thread_id=$null
            codex_turns=0; gpt_reviews=0; user_followups=0; repair_round=0
            verified_gates=@(); release_evidence=$null
            reason=$null; next_action='DISPATCH_CODEX'; updated_at=$null
        }
        Save-BridgeRun -Path $CheckpointPath -State $state
    }

    for ($step=0;$step -lt $MaxSteps;$step++) {
        switch ([string]$state.phase) {
            'DISPATCH' {
                if ([string]::IsNullOrWhiteSpace([string]$state.pending_prompt)) {
                    return Set-BridgeRunBlocked -State $state -Path $CheckpointPath -Reason 'EMPTY_CODEX_INSTRUCTION'
                }
                $state.phase='CODEX_RUNNING'
                $state.next_action='EXECUTE_PROGRAMMATIC_CODEX'
                Save-BridgeRun -Path $CheckpointPath -State $state
                try {
                    $context=[pscustomobject]@{request_id=$RequestId;business_goal=$BusinessGoal;branch=$Branch;stage=$state.stage;head_sha=$state.head_sha;thread_id=$state.thread_id}
                    $execution=& $CodexExecutor $state.pending_prompt $context
                } catch {
                    $message=[string]$_.Exception.Message
                    if ($message -match '(?i)BRIDGE_QUOTA_EXHAUSTED|insufficient_quota|quota.exhausted') {
                        $state.status='WAITING_QUOTA'; $state.phase='DISPATCH'
                        $state.reason='EXECUTOR_QUOTA_EXHAUSTED'; $state.next_action='AUTO_RESUME_WHEN_QUOTA_AVAILABLE'
                        Save-BridgeRun -Path $CheckpointPath -State $state
                        return $state
                    }
                    return Set-BridgeRunBlocked -State $state -Path $CheckpointPath -Reason ('EXECUTOR_FAILURE: '+(Protect-BridgeEvidence $message))
                }
                if ($null -eq $execution -or -not [bool](Get-BridgeResultProperty $execution 'completed')) {
                    return Set-BridgeRunBlocked -State $state -Path $CheckpointPath -Reason 'EXECUTOR_INCOMPLETE'
                }
                $state.last_execution=Get-BridgeSafeResult $execution
                $observed=Get-BridgeResultProperty $execution 'observed_sha'
                if ($null -ne $observed -and ([string]$observed) -match '^[0-9a-f]{40}$' -and $state.head_sha -ne [string]$observed) {
                    $state.head_sha=[string]$observed
                    $state.verified_gates=@()
                }
                $thread=Get-BridgeResultProperty $execution 'thread_id'
                if (-not [string]::IsNullOrWhiteSpace([string]$thread)) { $state.thread_id=[string]$thread }
                $state.codex_turns=[int]$state.codex_turns+1
                $state.phase='GPT_REVIEW';$state.next_action='ANALYZE_EXECUTOR_RESULT'
                Save-BridgeRun -Path $CheckpointPath -State $state
            }
            'GPT_REVIEW' {
                $context=[pscustomobject]@{request_id=$RequestId;business_goal=$BusinessGoal;branch=$Branch;stage=$state.stage;head_sha=$state.head_sha;controller_response_id=$state.controller_response_id}
                try { $decision=& $GptReviewer $state.last_execution $context } catch {
                    $message=[string]$_.Exception.Message
                    if ($message -match '(?i)BRIDGE_QUOTA_EXHAUSTED|insufficient_quota|quota.exhausted') {
                        $state.status='WAITING_QUOTA';$state.reason='GPT_QUOTA_EXHAUSTED'
                        $state.next_action='AUTO_RESUME_GPT_REVIEW'
                        Save-BridgeRun -Path $CheckpointPath -State $state
                        return $state
                    }
                    return Set-BridgeRunBlocked -State $state -Path $CheckpointPath -Reason ('GPT_REVIEW_FAILED: '+(Protect-BridgeEvidence $message))
                }
                if ($null -eq $decision) {
                    return Set-BridgeRunBlocked -State $state -Path $CheckpointPath -Reason 'EMPTY_GPT_DECISION'
                }
                $state.last_decision=Get-BridgeSafeResult $decision
                $responseId=Get-BridgeResultProperty $decision 'response_id'
                if (-not [string]::IsNullOrWhiteSpace([string]$responseId)) { $state.controller_response_id=[string]$responseId }
                $state.gpt_reviews=[int]$state.gpt_reviews+1
                $state.phase='GPT_DECISION'
                Save-BridgeRun -Path $CheckpointPath -State $state
            }
            'GPT_DECISION' {
                $decision=[string]$state.last_decision.decision
                if ($decision -in @('NEXT_STAGE','REPAIR')) {
                    $prompt=[string]$state.last_decision.codex_prompt
                    if ([string]::IsNullOrWhiteSpace($prompt)) {
                        return Set-BridgeRunBlocked -State $state -Path $CheckpointPath -Reason 'MISSING_CODEX_REDISPATCH'
                    }
                    if ($decision -eq 'REPAIR') { $state.repair_round=[int]$state.repair_round+1 }
                    $state.pending_prompt=$prompt
                    $state.phase='DISPATCH'
                    $state.next_action='AUTO_REDISPATCH_CODEX'
                    Save-BridgeRun -Path $CheckpointPath -State $state
                } elseif ($decision -in @('REVERIFY','RELEASE_READY')) {
                    $state.phase='GATE_VERIFY'
                    $state.next_action='VERIFY_REQUIRED_GATES'
                    Save-BridgeRun -Path $CheckpointPath -State $state
                } elseif ($decision -eq 'BLOCKED') {
                    return Set-BridgeRunBlocked -State $state -Path $CheckpointPath -Reason 'GPT_DECLARED_REAL_BLOCKER'
                } elseif ($decision -eq 'RETRY_INFRA') {
                    $prompt=[string]$state.last_decision.codex_prompt
                    if ([string]::IsNullOrWhiteSpace($prompt)) { $prompt=$state.pending_prompt }
                    $state.pending_prompt=$prompt
                    $state.phase='DISPATCH'
                    Save-BridgeRun -Path $CheckpointPath -State $state
                } else {
                    return Set-BridgeRunBlocked -State $state -Path $CheckpointPath -Reason ('UNSUPPORTED_GPT_DECISION: '+$decision)
                }
            }
            'GATE_VERIFY' {
                if ([string]$state.stage -match '(?i)^T4(?:_|$)' -and $state.last_decision.decision -eq 'RELEASE_READY') {
                    return Set-BridgeRunBlocked -State $state -Path $CheckpointPath -Reason 'T4_RELEASE_FORBIDDEN_UNTIL_T5_VERIFIED'
                }
                $proofs=New-Object System.Collections.Generic.List[string]
                foreach ($gate in @($state.required_gates)) {
                    $context=[pscustomobject]@{request_id=$RequestId;branch=$Branch;stage=$state.stage;head_sha=$state.head_sha}
                    try { $gateResult=& $GateVerifier $gate $context } catch {
                        return Set-BridgeRunBlocked -State $state -Path $CheckpointPath -Reason ('GATE_EXECUTION_ERROR: '+$gate)
                    }
                    $actualSha=[string](Get-BridgeResultProperty $gateResult 'observed_sha')
                    $evidence=[string](Get-BridgeResultProperty $gateResult 'evidence_id')
                    if ([string](Get-BridgeResultProperty $gateResult 'status') -ne 'PASS' -or $actualSha -ne $state.head_sha -or [string]::IsNullOrWhiteSpace($evidence)) {
                        return Set-BridgeRunBlocked -State $state -Path $CheckpointPath -Reason ('GATE_NOT_VERIFIED: '+$gate)
                    }
                    [void]$proofs.Add($gate)
                }
                $state.verified_gates=@($proofs)
                $state.phase='RELEASE'
                $state.next_action='PUBLISH_AFTER_VERIFIED_GATES'
                Save-BridgeRun -Path $CheckpointPath -State $state
            }
            'RELEASE' {
                $context=[pscustomobject]@{
                    request_id=$RequestId;branch=$Branch;stage=$state.stage;head_sha=$state.head_sha
                    required_gates=$state.required_gates; verified_gates=$state.verified_gates
                }
                try { $published=& $ReleasePublisher $context } catch {
                    return Set-BridgeRunBlocked -State $state -Path $CheckpointPath -Reason ('RELEASE_FAILED: '+(Protect-BridgeEvidence $_.Exception.Message))
                }
                $required=@('release_created','tag_verified','zip_hash_verified','local_runtime_verified','ci_verified','rollback_verified','notion_verified','project_state_verified')
                $missing=@($required | Where-Object { (Get-BridgeResultProperty $published $_) -ne $true })
                if ((Get-BridgeResultProperty $published 'status') -ne 'PASS' -or (Get-BridgeResultProperty $published 'release_sha') -ne $state.head_sha -or $missing.Count -gt 0) {
                    return Set-BridgeRunBlocked -State $state -Path $CheckpointPath -Reason ('RELEASE_EVIDENCE_INCOMPLETE: '+($missing -join ','))
                }
                $state.release_evidence=Get-BridgeSafeResult $published
                $state.phase='PLUGIN_RELEASED'
                $state.status='PLUGIN_RELEASED'
                $state.next_action='NONE'
                Save-BridgeRun -Path $CheckpointPath -State $state
                return $state
            }
            default { return Set-BridgeRunBlocked -State $state -Path $CheckpointPath -Reason ('UNKNOWN_RUN_PHASE: '+[string]$state.phase) }
        }
    }
    $state.status='CHECKPOINTED'
    $state.next_action='AUTO_CONTINUE_FROM_CHECKPOINT'
    Save-BridgeRun -Path $CheckpointPath -State $state
    return $state
}
Export-ModuleMember -Function Invoke-BridgeAutonomousRun
