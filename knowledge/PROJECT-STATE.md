# xz_visit_stats Project State

## State contract

This file is the human/agent-readable project-state checkpoint for the current major-version flow. It must be reconciled against the real Git working tree, GitHub branch and runtime evidence at task start.

Do not treat a commit hash in this file as proof that it is still the branch HEAD. Branch HEAD is mutable and must be observed from Git.

## Current project

- Project: `xz_visit_stats`
- Target major version: `4.0.0`
- Repository: `mxonline/zblogplugin-xz_visit_stats`
- Development branch: `feature/visit-stats-4.0`
- Current phase: `T3 — session, page lifecycle and event collection foundation`
- Phase status: `IN PROGRESS / CODEX HANDOFF READY`
- Release Gate: `NOT READY`
- PR/Merge/Tag/Release: not authorized for the current T3 phase

## Last verified implementation baseline

T2 completion baseline:

- Commit: `1390098c8621836e40dd5bfd408a220a18148704`
- GitHub Actions: `32943912935` — PASS
- T2 real Windows schema audit: PASS
- Migration design: complete

T3 handoff baseline:

- Commit: `15c3d633d55b619617da24ff48620e9b005fd1c6`
- Commit purpose: add `.codex-tasks/07-v4-t3-foundation.md`
- GitHub Actions: `32944582076` — PASS
- This commit prepares T3 execution; it is not evidence that T3 implementation/runtime verification is complete.

A documentation-only knowledge-layer bootstrap was added after the T3 handoff. At the start of any new execution, inspect the actual current branch HEAD rather than assuming either baseline above is still HEAD.

## T2 verified runtime environment

```text
Z-Blog root: D:\wwwroot\www.xzhao.net
Plugin root: D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats
PHP CLI:     D:\BtSoft\php\83\php.exe
Local site:  http://127.0.0.1
PHP:         8.3.8
Z-BlogPHP:   173540
MySQL:       5.7.38-log
Plugin:      3.0.0
```

T2 audit evidence:

- 9 plugin-related tables
- main visit log: 288 rows
- 29 read-only queries
- Safe Mode: PASS
- plugin/theme loading disabled during audit: PASS
- no DDL/DML
- no password/token/cookie/private-key/visitor-level export

## Current T3 scope

T3 must implement and verify the foundation for:

- v4 idempotent schema additions
- session identification
- page sequence and page lifecycle
- trustworthy visitor dwell semantics
- bounce/page-depth foundation
- code events
- IPv4/IPv6 IP filtering
- query/rollup foundation for session/event metrics
- targeted automated tests
- real Windows Z-Blog runtime verification

Explicit exclusions remain:

- channel tracking
- heatmaps
- screen recording
- T4 final analytics/admin UI

## Current T3 execution entrypoint

Read and execute:

- `AGENTS.md`
- `knowledge/INDEX.md`
- `knowledge/ZBLOG-DEVELOPMENT-KNOWLEDGE.md`
- `knowledge/KNOWN-FAILURES.md`
- `docs/v4.0.0/PRD-v1.0.md`
- `docs/v4.0.0/GAP-ANALYSIS-v1.0.md`
- `docs/v4.0.0/SCHEMA-AUDIT-v1.0.md`
- `docs/v4.0.0/MIGRATION-DESIGN-v1.0.md`
- `.codex-tasks/07-v4-t3-foundation.md`

## Important current constraints

- Preserve v3 raw logs and historical tables.
- Never reinterpret `vs_DurationMs` as visitor dwell time.
- v4 migration must be idempotent and stop on incompatible same-name structures.
- Local runtime verification is mandatory for T3 because it touches schema, collector/request lifecycle and runtime behavior.
- GitHub CI cannot replace local Z-Blog runtime verification.
- T3 completion must not merge/tag/release; Release Gate remains `NOT READY` unless a later explicitly authorized phase changes it.

## Known state drift

- Root `.codex-state.json` currently represents an older v1.3-era controller/task chain. It is not the authoritative v4 state source.
- Older `AGENTS.md` environment defaults pointed at `D:\wwwroot\xinzhao_net`; the v4 audit verified `D:\wwwroot\www.xzhao.net`. Current agent rules and knowledge now use the verified v4 environment while still requiring detection before runtime work.

## Next action

Local Codex should continue T3 from `.codex-tasks/07-v4-t3-foundation.md` without step-by-step user prompts:

1. reconcile local Git/worktree with the current remote branch;
2. run T3 with TDD and the migration safety rules;
3. perform required real Windows Z-Blog runtime verification;
4. fix failures and re-test;
5. push the T3 implementation branch work and verify CI;
6. keep Release Gate `NOT READY` unless explicitly authorized otherwise;
7. update this file with the real implementation commit, runtime evidence, CI run and next phase;
8. write the same verified result back to the T3 Notion task.

## Update discipline

Only write observed facts here. Do not record planned commands as completed work, and do not mark T3 complete until the implementation, required runtime checks and Git/CI evidence have actually been observed.
