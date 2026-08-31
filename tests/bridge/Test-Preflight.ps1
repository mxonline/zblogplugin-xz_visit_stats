. "$PSScriptRoot/Assert.ps1"

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.Preflight.psm1" -Force

$temp = Join-Path ([System.IO.Path]::GetTempPath()) ("xz-bridge-preflight-" + [Guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $temp -Force | Out-Null

try {
    & git -C $temp init | Out-Null
    & git -C $temp config user.email 'bridge-test@example.invalid'
    & git -C $temp config user.name 'Bridge Test'

    New-Item -ItemType Directory -Path (Join-Path $temp 'knowledge') -Force | Out-Null
    New-Item -ItemType Directory -Path (Join-Path $temp '.codex-tasks') -Force | Out-Null

    Set-Content -LiteralPath (Join-Path $temp 'AGENTS.md') -Encoding UTF8 -Value '# fixture'
    Set-Content -LiteralPath (Join-Path $temp '.codex-state.json') -Encoding UTF8 -Value '{"current":99}'
    Set-Content -LiteralPath (Join-Path $temp '.codex-tasks/08-v4-t4-analytics-admin.md') -Encoding UTF8 -Value '# T4 fixture'
    Set-Content -LiteralPath (Join-Path $temp 'knowledge/PROJECT-STATE.md') -Encoding UTF8 -Value @'
# Project State

## Verified T2 baseline
PASS

## Verified T3 completion
PASS

- Current phase: `T4 — analytics/admin reports, filters and session drill-down`
- Phase status: `IN PROGRESS / CODEX HANDOFF READY`
'@

    & git -C $temp add .
    & git -C $temp commit -m 'fixture baseline' | Out-Null

    $clean = Invoke-BridgePreflight -RepositoryRoot $temp -SkipCredentialChecks -SkipRuntimeChecks
    Assert-Equal 'PASS' $clean.status 'clean fixture preflight'
    Assert-Equal 'T4_ANALYTICS_ADMIN' $clean.resume_stage 'resume current T4'
    Assert-False $clean.legacy_codex_state_authoritative 'legacy state must never be authoritative'
    Assert-Equal 0 $clean.dirty_paths.Count 'clean fixture has no dirty paths'

    Set-Content -LiteralPath (Join-Path $temp 'unrelated-user-work.txt') -Encoding UTF8 -Value 'do not discard'
    $dirty = Invoke-BridgePreflight -RepositoryRoot $temp -SkipCredentialChecks -SkipRuntimeChecks
    Assert-Equal 'BLOCKED_WORKTREE' $dirty.status 'dirty worktree must block mutation'
    Assert-True ($dirty.dirty_paths.Count -gt 0) 'dirty path evidence required'
    Assert-PathExists (Join-Path $temp 'unrelated-user-work.txt') 'preflight must not clean user work'

    Write-Host 'PASS: bridge preflight and T4 resume gate'
} finally {
    Remove-Item -LiteralPath $temp -Recurse -Force -ErrorAction SilentlyContinue
}
