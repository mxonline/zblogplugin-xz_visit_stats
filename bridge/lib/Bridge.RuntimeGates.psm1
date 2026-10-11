Set-StrictMode -Version Latest
$ErrorActionPreference='Stop'
Import-Module (Join-Path $PSScriptRoot 'Bridge.Common.psm1')
Import-Module (Join-Path $PSScriptRoot 'Bridge.Evidence.psm1')

function Invoke-BridgeLiveGate {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory=$true)][string]$Gate,
        [Parameter(Mandatory=$true)]$Context,
        [Parameter(Mandatory=$true)][string]$WorkspaceRoot,
        [Parameter(Mandatory=$true)][string]$EvidenceRoot,
        [Parameter(Mandatory=$true)][string]$Repository
    )
    $sha=[string]$Context.head_sha
    $gitSha=(& git -C $WorkspaceRoot rev-parse HEAD 2>$null | Out-String).Trim()
    if ($LASTEXITCODE -ne 0 -or $gitSha -ne $sha) {
        return [pscustomobject]@{status='BLOCKED';reason='WORKTREE_SHA_MISMATCH';observed_sha=$gitSha;evidence_id=$null}
    }
    $status='BLOCKED'
    $reason='GATE_EXECUTOR_NOT_AVAILABLE'
    $details=$null
    if ($Gate -eq 'GITHUB_CI') {
        if ($null -eq (Get-Command gh -ErrorAction SilentlyContinue)) {
            $reason='GITHUB_CLI_MISSING'
        } else {
            $json=& gh run list --repo $Repository --commit $sha --limit 50 --json workflowName,headSha,status,conclusion 2>&1
            if ($LASTEXITCODE -eq 0) {
                try {
                    $runs=@(($json | Out-String | ConvertFrom-Json))
                    $relevant=@($runs | Where-Object { $_.headSha -eq $sha -and $_.workflowName -eq 'Code Quality Check' })
                    $passed=@($relevant | Where-Object { $_.status -eq 'completed' -and $_.conclusion -eq 'success' })
                    if ($passed.Count -gt 0) { $status='PASS';$reason='EXACT_SHA_CI_VERIFIED' }
                    else { $reason='EXACT_SHA_CI_NOT_SUCCESSFUL' }
                    $details=[pscustomobject]@{observed_runs=$relevant.Count;successful_runs=$passed.Count}
                } catch { $reason='GITHUB_CI_JSON_INVALID' }
            } else { $reason='GITHUB_API_UNAVAILABLE' }
        }
    } elseif ($Gate -eq 'UNIT_TEST') {
        $local=Join-Path $WorkspaceRoot 'scripts/local-verify.ps1'
        $phpunitBat=Join-Path $WorkspaceRoot 'vendor/bin/phpunit.bat'
        $phpunitPhar=Join-Path $WorkspaceRoot 'phpunit.phar'
        $phpunitCmd=Get-Command phpunit -ErrorAction SilentlyContinue
        if (-not (Test-Path -LiteralPath $local -PathType Leaf)) {
            $reason='UNIT_TEST_SCRIPT_MISSING'
        } elseif (-not (Test-Path -LiteralPath $phpunitBat) -and -not (Test-Path -LiteralPath $phpunitPhar) -and $null -eq $phpunitCmd) {
            $reason='PHPUNIT_NOT_INSTALLED'
        } else {
            & powershell -NoProfile -ExecutionPolicy Bypass -File $local -SkipHttp *> $null
            $code=$LASTEXITCODE
            if ($code -eq 0) {$status='PASS';$reason='LOCAL_UNIT_TESTS_EXECUTED'}
            else {$reason='LOCAL_UNIT_TESTS_FAILED'}
            $details=[pscustomobject]@{exit_code=$code;command='scripts/local-verify.ps1 -SkipHttp'}
        }
    } else {
        $supported=@('LOCAL_RUNTIME','SQL_EXPLAIN','FINAL_RUNTIME','RELEASE_GATE','ROLLBACK_GATE','VERSION_CONSISTENCY_GATE','T4_VERIFIED','T5_VERIFIED')
        if ($Gate -notin $supported) {
            $reason='UNKNOWN_GATE'
        } else {
            # A dedicated verifier must exist on the *actual Windows site*. The
            # bridge never uses cloud CI or a bare HTTP response as runtime proof.
            $verifier=Join-Path $WorkspaceRoot ('scripts/runtime-gates/'+$Gate+'.ps1')
            if (-not (Test-Path -LiteralPath $verifier -PathType Leaf)) {
                $reason='REAL_RUNTIME_VERIFIER_MISSING'
            } else {
                $proofFile=Join-Path $EvidenceRoot ('live-'+$Gate+'-'+$sha+'.json')
                if (Test-Path -LiteralPath $proofFile) { Remove-Item -LiteralPath $proofFile -Force }
                & powershell -NoProfile -ExecutionPolicy Bypass -File $verifier -ExpectedSha $sha -EvidencePath $proofFile *> $null
                $code=$LASTEXITCODE
                if ($code -eq 0 -and (Test-Path -LiteralPath $proofFile -PathType Leaf)) {
                    try {
                        $proof=Read-BridgeJson -Path $proofFile
                        $environment=[string]$proof.environment
                        $observed=[string]$proof.observed_sha
                        $checks=@($proof.checks)
                        $count=@($checks | Where-Object { $_.status -eq 'PASS' -and -not [string]::IsNullOrWhiteSpace([string]$_.evidence) }).Count
                        if ($observed -eq $sha -and $environment -eq 'windows_local_zblog' -and $checks.Count -gt 0 -and $count -eq $checks.Count) {
                            $status='PASS';$reason='REAL_RUNTIME_PROOF_VERIFIED'
                        } else { $reason='RUNTIME_PROOF_INCOMPLETE' }
                        $details=[pscustomobject]@{verifier=$Gate;observed_sha=$observed;environment=$environment;checks=$checks.Count;passed_checks=$count}
                    } catch { $reason='RUNTIME_PROOF_INVALID' }
                } else { $reason='RUNTIME_VERIFIER_FAILED' }
            }
        }
    }
    $record=[pscustomobject]@{
        request_id=[string]$Context.request_id;gate=$Gate;stage=[string]$Context.stage
        branch=[string]$Context.branch;head_sha=$sha;environment='windows_local_bridge'
        command=('Verify '+$Gate);status=$status
        result=[pscustomobject]@{reason=$reason;details=$details}
    }
    $evidence=Write-BridgeEvidence -EvidenceRoot $EvidenceRoot -Record $record
    return [pscustomobject]@{status=$status;reason=$reason;observed_sha=$gitSha;evidence_id=$evidence.evidence_id}
}
Export-ModuleMember -Function Invoke-BridgeLiveGate
