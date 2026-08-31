. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Evidence.psm1" -Force
Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Gates.psm1" -Force

$temp = Join-Path ([IO.Path]::GetTempPath()) ('xz-bridge-gates-' + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $temp -Force | Out-Null
$sha = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'

try {
    $passExecutor = {
        param($Context)
        return [pscustomobject]@{
            exit_code = 0
            output = '21 tests / 93 assertions PASS'
            observed_sha = $Context.head_sha
            details = [pscustomobject]@{ assertions = 93 }
        }
    }

    $unit = Invoke-BridgeGate `
        -Gate 'UNIT_TEST' `
        -RequestId 'REQ-GATE-1' `
        -Stage 'T4_ANALYTICS_ADMIN' `
        -Branch 'feature/visit-stats-4.0' `
        -HeadSha $sha `
        -Environment 'windows_local' `
        -Command 'php vendor/bin/phpunit' `
        -EvidenceRoot $temp `
        -Executor $passExecutor
    Assert-Equal 'PASS' $unit.status 'unit test adapter passes'
    Assert-True (-not [string]::IsNullOrWhiteSpace($unit.evidence_id)) 'unit PASS has evidence id'
    $unitEvidence = Test-GateEvidence -GateResult $unit -EvidenceRoot $temp -ExpectedSha $sha
    Assert-True $unitEvidence.valid 'unit evidence validates against exact SHA'

    $wrongRuntime = Invoke-BridgeGate `
        -Gate 'LOCAL_RUNTIME' `
        -RequestId 'REQ-GATE-2' `
        -Stage 'T4_ANALYTICS_ADMIN' `
        -Branch 'feature/visit-stats-4.0' `
        -HeadSha $sha `
        -Environment 'github_actions' `
        -Command 'curl http://127.0.0.1' `
        -EvidenceRoot $temp `
        -Executor $passExecutor
    Assert-Equal 'BLOCKED' $wrongRuntime.status 'CI cannot substitute for local Z-Blog runtime'
    Assert-Equal 'LOCAL_RUNTIME_REQUIRES_LOCAL_ZBLOG' $wrongRuntime.reason_code 'local runtime environment guard'

    $localRuntime = Invoke-BridgeGate `
        -Gate 'LOCAL_RUNTIME' `
        -RequestId 'REQ-GATE-3' `
        -Stage 'T4_ANALYTICS_ADMIN' `
        -Branch 'feature/visit-stats-4.0' `
        -HeadSha $sha `
        -Environment 'local_zblog_windows' `
        -Command 'powershell scripts/local-verify.ps1' `
        -EvidenceRoot $temp `
        -Executor $passExecutor
    Assert-Equal 'PASS' $localRuntime.status 'real local runtime environment accepted'

    $sqlExecutor = {
        param($Context)
        return [pscustomobject]@{
            exit_code = 0
            output = 'EXPLAIN uses idx_session_last_seen'
            observed_sha = $Context.head_sha
            details = [pscustomobject]@{ uses_expected_index = $true; rows_estimate = 12 }
        }
    }
    $sql = Invoke-BridgeGate `
        -Gate 'SQL_EXPLAIN' `
        -RequestId 'REQ-GATE-4' `
        -Stage 'T4_ANALYTICS_ADMIN' `
        -Branch 'feature/visit-stats-4.0' `
        -HeadSha $sha `
        -Environment 'local_zblog_windows' `
        -Command 'EXPLAIN SELECT ...' `
        -EvidenceRoot $temp `
        -Executor $sqlExecutor
    Assert-Equal 'PASS' $sql.status 'SQL explain local evidence accepted'

    $mismatchExecutor = {
        param($Context)
        return [pscustomobject]@{
            exit_code = 0
            output = 'CI passed for another commit'
            observed_sha = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'
            details = [pscustomobject]@{ run_id = 123 }
        }
    }
    $ciMismatch = Invoke-BridgeGate `
        -Gate 'GITHUB_CI' `
        -RequestId 'REQ-GATE-5' `
        -Stage 'T4_ANALYTICS_ADMIN' `
        -Branch 'feature/visit-stats-4.0' `
        -HeadSha $sha `
        -Environment 'github_actions' `
        -Command 'verify exact SHA CI' `
        -EvidenceRoot $temp `
        -Executor $mismatchExecutor
    Assert-Equal 'BLOCKED' $ciMismatch.status 'CI for another SHA cannot pass current gate'
    Assert-Equal 'EXACT_SHA_MISMATCH' $ciMismatch.reason_code 'CI mismatch classified'

    $ci = Invoke-BridgeGate `
        -Gate 'GITHUB_CI' `
        -RequestId 'REQ-GATE-6' `
        -Stage 'T4_ANALYTICS_ADMIN' `
        -Branch 'feature/visit-stats-4.0' `
        -HeadSha $sha `
        -Environment 'github_actions' `
        -Command 'verify exact SHA CI' `
        -EvidenceRoot $temp `
        -Executor $passExecutor
    Assert-Equal 'PASS' $ci.status 'exact-SHA CI passes'

    $failExecutor = {
        param($Context)
        return [pscustomobject]@{
            exit_code = 1
            output = 'focused test failed'
            observed_sha = $Context.head_sha
            details = [pscustomobject]@{ failed = 1 }
        }
    }
    $failed = Invoke-BridgeGate `
        -Gate 'UNIT_TEST' `
        -RequestId 'REQ-GATE-7' `
        -Stage 'T4_ANALYTICS_ADMIN' `
        -Branch 'feature/visit-stats-4.0' `
        -HeadSha $sha `
        -Environment 'windows_local' `
        -Command 'php vendor/bin/phpunit --filter focused' `
        -EvidenceRoot $temp `
        -Executor $failExecutor
    Assert-Equal 'REPAIRABLE' $failed.status 'gate execution failure returns repairable state'
    Assert-Equal 'GPT_REVIEW_REPAIR' $failed.next_action 'repairable gate returns GPT repair action'
    Assert-True (-not [string]::IsNullOrWhiteSpace($failed.evidence_id)) 'failed gate also preserves evidence'

    Write-Host 'PASS: evidence-bound gate adapter contract'
} finally {
    Remove-Item -LiteralPath $temp -Recurse -Force -ErrorAction SilentlyContinue
}
