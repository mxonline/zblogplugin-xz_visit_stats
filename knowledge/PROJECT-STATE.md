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
- Phase status: `IMPLEMENTED / VERIFIED LOCALLY / GIT DELIVERY PENDING`
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

## T3 Reuse Gate — 2026-08-27

The prospective Reuse Gate required by `AGENTS.md` has been completed for the unresolved T3 subsystem decisions.

- Decision: `BUILD + SELECTIVE REUSE`.
- Evidence: `docs/v4.0.0/REUSE-GATE-T3-v1.0.md`.
- Gate commit: `2151da072d9748e61d8489f795835be935c9a8ac` (the branch may have advanced after this commit; always inspect real HEAD).
- USE: existing Z-BlogPHP hooks/config/database APIs and current `inc/upgrade/` framework.
- BUILD: project-specific v4 session identity, page lifecycle/dwell/bounce semantics, code events and session/event rollups.
- REUSE: `mlocati/ip-lib` `1.22.0` only for normalized IPv4/IPv6/CIDR parsing and matching, behind the plugin's IP-filter adapter; pin/bundle the library and MIT license so production does not require Composer.
- REFERENCE ONLY: Matomo analytics architecture/semantics; do not import, fork or copy Matomo code.
- This gate does not mark T3 implementation or Windows runtime verification complete.

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
- `knowledge/REUSE-GATE.md`
- `docs/v4.0.0/REUSE-GATE-T3-v1.0.md`
- `docs/v4.0.0/PRD-v1.0.md`
- `docs/v4.0.0/GAP-ANALYSIS-v1.0.md`
- `docs/v4.0.0/SCHEMA-AUDIT-v1.0.md`
- `docs/v4.0.0/MIGRATION-DESIGN-v1.0.md`
- `.codex-tasks/07-v4-t3-foundation.md`

## T3 implementation and local runtime verification — 2026-08-27

- Development Git worktree: `D:\wwwroot\xinzhao_net\zb_users\plugin\xz_visit_stats\source`; runtime deployment copy: `D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats`. The former is the only Git worktree; the latter is a local IIS/Z-Blog test deployment.
- The runtime copy was backed up before deployment to `D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats.t3-backup-20260827-012911` (59 files, 1,571,651 bytes), then only the plugin was synchronized. No Z-Blog core or other plugin was touched.
- Two earlier deployment attempts created no process and made no file change because Codex execution approval timed out. That was an execution-approval boundary, not a Windows ACL, PHP, database, Composer or plugin-code failure. The authorized deployment subsequently completed; source/target hashes were verified for the incrementally changed files.
- T3-A through T3-F are implemented with the recorded `BUILD + SELECTIVE REUSE` decision. `mlocati/ip-lib` `1.22.0` is pinned in `composer.json`/`composer.lock`; only its runtime source plus `LICENSE.txt` are packaged, and the IP adapter loads it without requiring Composer on the runtime host.
- Automated evidence: PHPUnit `21 tests / 93 assertions` PASS with no warning; all non-vendor PHP files PASS syntax check; `assets/rum.js` PASS Node syntax check. `tests/UpgradeFrameworkTest.php` now accurately declares its MySQL test-double driver type, removing the prior `XzVisitStatsTestDb::$type` warning. `scripts/local-verify.ps1` was corrected to run the configured test suite instead of the nonexistent `default` suite.
- Runtime migration evidence: upgrade ran twice with `true` results and stored `db_version=4.0.0`. Read-only schema audit report `C:\Users\chenz\AppData\Local\Temp\schema-audit-20260827-014357.json` observed all six v4 tables and required indexes: sessions, session pages, events, directory rules, export tasks and IP filters.
- v3 protection evidence: the original 9 plugin tables remain. The main raw log baseline was 288 rows and is now 297 only from explicit local verification requests; no historical row was deleted or cleared. Existing `keywords`, `page_uv`, and `pages` tables remain present.
- Runtime behavior observed: homepage `200`, 404 probe `404`, and Baiduspider/referer probe `200`. A two-page identifiable test session reused one Session, produced sequence 1 then 2 and PageCount 2. Without an exit Beacon dwell remained `NULL`; a lifecycle Beacon set a 1250 ms v4 dwell value while `vs_DurationMs` remained outside visitor-dwell logic. Expired single-page session became bounce; multi-page session did not. A valid event stored only allowlisted params. Cross-origin events returned 204 but did not persist; same-origin events persisted. Beacon setting was restored to its original `0` after tests.
- IPv4 single, IPv4 CIDR, IPv6 single and IPv6 CIDR rules were each tested against the local host. Each request returned normally but did not increase main-log, Session, Page or Event counts; the exact temporary test rule was then removed.
- Runtime log review: IIS worker was active, no Nginx process was active, the unauthenticated plugin-admin request reached the normal Z-Blog permission page rather than a PHP Fatal/Error, and no xz_visit_stats Fatal/SQL error was found in available PHP/Z-Blog logs. A CLI-only deprecation from unrelated plugin `TCad` (`include.php:17`) was observed and not changed.
- Git commit/push, GitHub CI confirmation and controller-side Notion writeback remain pending; do not treat this checkpoint as final delivery evidence.

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

1. inspect the final staged diff, secrets and generated-file boundary;
2. commit and push verified T3 work to `feature/visit-stats-4.0`;
3. inspect the resulting GitHub Actions run and repair/retest/re-push if necessary;
4. update this file with the delivery commit and CI evidence;
5. write the same verified result back to the T3 Notion task through the controller;
6. keep Release Gate `NOT READY`; do not merge, tag or release.

## Update discipline

Only write observed facts here. Do not record planned commands as completed work, and do not mark T3 complete until the implementation, required runtime checks and Git/CI evidence have actually been observed.
