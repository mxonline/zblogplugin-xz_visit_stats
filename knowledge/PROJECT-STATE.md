# xz_visit_stats Project State

## State contract

This is the human/agent-readable checkpoint for the current major-version flow. At every task start, reconcile it against the real Git working tree, remote branch, CI and runtime evidence. Real Git/runtime/CI evidence has higher authority than any recorded SHA here.

## Current project

- Project: `xz_visit_stats`
- Target major version: `4.0.0`
- Repository: `mxonline/zblogplugin-xz_visit_stats`
- Development branch: `feature/visit-stats-4.0`
- Current phase: `T4 — analytics/admin reports, filters and session drill-down`
- Phase status: `T4 COMPLETE / STOPPED BEFORE T5` (Tasks 1–10 complete; runtime, database, tests, push, exact-SHA CI and Notion status readback verified)
- Release Gate: `NOT READY`
- PR/Merge/Tag/Release: not authorized during T4

## Verified T2 baseline

- T2 completion commit: `1390098c8621836e40dd5bfd408a220a18148704`.
- GitHub Actions `32943912935`: PASS.
- Real Windows audit: PHP `8.3.8`, Z-BlogPHP `173540`, MySQL `5.7.38-log`, plugin `3.0.0`.
- Audited plugin-related tables: 9; raw visit log baseline: 288 rows.
- Safe Mode/read-only audit: PASS; no DDL/DML and no secret/visitor-level export.
- Migration design: `docs/v4.0.0/MIGRATION-DESIGN-v1.0.md`.

## Verified T3 completion — 2026-08-27

- T3 implementation commit: `521d68b0d4e67d07b3e7657c502f292295c366f3` (`feat(v4): add visit session collection foundation`).
- Verified-delivery documentation commit: `95cab5150a75580c8d459002abfd184860a2e156`.
- GitHub Actions `33005202172` for the implementation SHA: `success`.
- GitHub Actions `33005418559` for the verified-delivery SHA: `success`.
- Automated verification: PHPUnit `21 tests / 93 assertions` PASS; non-vendor PHP syntax PASS; `assets/rum.js` syntax PASS; diff/secrets/release-file checks PASS.
- Runtime backup before T3 deployment: `D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats.t3-backup-20260827-012911`.
- Real local runtime: `D:\wwwroot\www.xzhao.net`, IIS, PHP `8.3.8`, MySQL `5.7.38-log`, local site `http://127.0.0.1`.
- v4 migration ran twice successfully and stored `db_version=4.0.0`; all six v4 tables and required indexes were observed.
- Original 9 plugin tables remain. Raw visit rows increased from 288 to 297 only through explicit local verification traffic; no historical row/table was deleted or cleared.
- Session/page lifecycle behavior verified: same-session page sequence, PageCount, NULL dwell without exit, 1250ms lifecycle dwell after Beacon, single-page completed bounce, multi-page non-bounce.
- `vs_DurationMs` remains server processing time and never supplies visitor dwell.
- Event allowlist/privacy behavior verified, including same-origin/cross-origin behavior.
- IPv4 single/CIDR and IPv6 single/CIDR filters were verified; matched requests did not create raw log/session/page/event rows.
- `mlocati/ip-lib` `1.22.0` is pinned; runtime bundle keeps required source/license without requiring Composer autoload on the deployed plugin.
- T3 Notion task is marked complete by the ChatGPT controller.

## T4 Reuse Gate — 2026-08-27

Decision: **USE EXISTING + BUILD PROJECT-SPECIFIC**.

Evidence: `docs/v4.0.0/REUSE-GATE-T4-v1.0.md`.

- USE existing Z-BlogPHP admin shell, permission/CSRF APIs, query/filter helpers and keyset pagination.
- USE existing vendored Apache ECharts for all T4 charts; do not add a second chart engine.
- USE existing vendored Alpine.js for lightweight admin interaction; do not add Vue/React/Svelte.
- BUILD project-specific session/event report queries, drill-downs, content/directory/visitor reports, column chooser and export-task orchestration.
- KEEP existing bounded CSV-safe export behavior for compatibility while using the v4 export-task table for controlled v4 task workflows.
- Do not add DataTables/grid frameworks, datepicker libraries or generic queue/worker frameworks during T4 without a new Reuse Gate.
- Matomo and 51.LA remain reference-only for information architecture/semantics; no import/fork/code copy.

## T4 persisted progress — 2026-10-13

- Tasks 1–8 are complete locally and must not be repeated. Their commits remain in order: Task 1 `1118ea73cfdb654f17b6d1b027b74b0e5456fcc7`; Task 2 `b617098e224039ed540d55b6322e8645a63633b0`; Task 3 `d8d5ba82ab602872382db468999e8ba40c808eb7`; Task 4 `a137761ce8227325b0ddb5ca35fcb92df7fe6d2f`; Task 5 `0fbea898b5227189babc427645be684bfd7e2d6e`; Task 6 `bc2f32a9961c74e683c0f2a2e7d1703bdd7b1168`; Task 7 `22266d8002b4774bbe8263c57c6ede835b3dea2c`; Task 8 `28f014b639338aa7ea888b0aeed2203a07a59639`.
- The initial Task 10 fetch observed remote head `cd695153c511fc54f66106d41268f0908fa04d42`; local Task 1–8 history contained it as an ancestor. T4 delivery commit `5f3cf89c21fb5b1fa2b6a0c01c063b31a2db9a79` contains the Task 9 fixes/tests/evidence. The delivery checkpoint `e39d3b318ccf4b258c0049a63da63cf024fd4ffa` was pushed normally. GitHub API readback confirms `feature/visit-stats-4.0` points to that SHA and its exact-SHA Actions run passed. No reset, rebase, force push, or worktree cleanup was performed.
- Runtime paths: source `D:\wwwroot\xinzhao_net\zb_users\plugin\xz_visit_stats\source`; Windows test site `D:\wwwroot\www.xzhao.net`; runtime plugin `D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats`. A pre-deploy plugin backup is retained at `D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats.backup-t4-session-link-20261013-023259` (92 files). The filtered runtime has 92 files, all matching the source package files; its vendor directory contains only locked `mlocati/ip-lib 1.22.0` and the MIT `LICENSE.txt`. No PHPUnit/PHPStan/dev packages or export CSVs are deployed.
- Database rollback evidence: pre-Task 9 logical backup `D:\wwwroot\xz_visit_stats-db-backups\zblog-before-t4-task9-20261012-224016.sql`, 6,993,139 bytes, SHA-256 `815FEC0A0459C0B5EDDD26C5126C006D484FA4680EE26E3BB3A2058247D3ACD2`. The MySQL client is present. No restore was executed against the running test database; if recovery is required, use the retained dump only in an isolated recovery/test schema before any deliberate cutover.
- Local runtime: IIS 10 / W3SVC Running, PHP `8.3.8`, Z-BlogPHP `173540`, MySQL service Running / server `5.7.38-log`; `http://127.0.0.1/` returned HTTP 200. The repository local verifier passed its homepage (200), page-type 404 (404), and crawler/referer (200) probes after fixing PowerShell 7 HTTP status handling in `scripts/local-verify.ps1`.
- Task 9 is locally PASS. All ten T4 primary admin pages rendered under the existing authorized session without PHP/SQL errors; legacy `pages`, `ip`, `environment`, `campaign`, `errors`, `performance`, `source`, `spider`, and `overview` compatibility pages also rendered. Existing T4 filter-range, visitor filter, keyset pagination, IP/directory-rule CRUD, controlled export, unauthorized-download denial, privacy, and realtime-to-session checks remain verified. Event detail path and session links were clicked in the real UI and reached filtered records and session detail pages. The temporary test rules, export task and generated file are absent after cleanup.
- The real local MySQL 5.7 audit compared 15 plugin tables against the pre-Task 9 baseline: all nine legacy/v3 tables remain and no existing table row count decreased. All six v4 tables and every required index are present. The v4 migration had already been run twice idempotently in this Task 9 session; no v3 table was dropped or rebuilt. The new unfiltered event-time index is `(ev_TriggeredAt, ev_ID)`.
- Real `EXPLAIN` results (bounded local test dataset): 30-day sessions used `xzvs_session_time` (`range`, estimated 4 rows; 0.254 ms, 4 result IDs); 30-day events used `xzvs_event_time` (`range`, estimated 1 row; 0.823 ms, no recent event IDs); session detail used `PRIMARY` (`const`, 0.178 ms); session-page detail used `xzvs_page_sequence` (`ref`, 0.157 ms); record/session linkage used `xzvs_page_log` (`ref`, 0.096 ms). No representative query did an unbounded raw-table scan. Timings describe only this local dataset.
- Final local automated gates: PHPUnit `69 tests / 494 assertions` PASS; all `66` non-vendor PHP files syntax PASS; all `16` asset JavaScript files syntax PASS; PowerShell verifier syntax PASS; UI Terminology PASS; `git diff --check` exit 0; secret-pattern scan of 12 changed/new files found no hits; no unexpected temp/log/backup/export artifacts are in the change set. PHPStan is configured report-only in CI: local run reports 67 existing diagnostics (including Z-Blog bootstrap/global stubs and unchanged legacy comparisons); no new diagnostic points to the Task 9 collector-link or page-size fix. Semgrep is delegated to the required GitHub CI workflow.
- Task 10 delivery is complete at checkpoint `e39d3b318ccf4b258c0049a63da63cf024fd4ffa`. GitHub Actions Run `38295559861` (Code Quality Check, push event) has `status=completed`, `conclusion=success`, and exact `head_sha=e39d3b318ccf4b258c0049a63da63cf024fd4ffa`. PHPStan remains report-only; its observed diagnostics are documented above and did not fail CI.
- Notion writeback: the implementation-plan overall status and T4 task status were updated; the T4 task acceptance checklist and status were read back successfully. The compact public delivery status and exact-SHA CI evidence were also read back. A detailed local-path/runtime payload was declined by the destination safety review and was not retried; detailed runtime/database evidence remains in this repository file.
- T5 has not started. Release Gate remains `NOT READY`; no merge, tag, or release has occurred.

## T4 scope

Primary v4 admin information architecture:

- `overview` — PV/UV/IP/session/bounce/visitor dwell plus dimensions
- `realtime` — 5/15/30-minute active visitor/session view
- `trend` — hour/day/week/month trends and comparable-period data
- `records` — visit detail, filters, columns, controlled export, session drill-down
- `source` — direct/search/external/AI/UTM analysis
- `content` — page, entry, directory and host/domain analysis
- `visitors` — new/returning, depth, device/environment/geo/IP privacy views
- `spider` — crawler type/count/IP/URL/status/detail
- `events` — event totals, triggers, unique users, per-user average and detail
- `settings` — collection/Beacon/IP filters/spider policy/retention/export/privacy/maintenance

Legacy v3 `pages`, `ip`, `environment`, `campaign`, `errors` and `performance` capabilities/deep links must remain compatible; T4 may reorganize them but must not silently remove them.

Explicit exclusions remain channel tracking, heatmaps, screen recording, visual event editor and 51.LA commercial quota logic.

## T4 execution entrypoint

Read and execute:

- `AGENTS.md`
- `knowledge/INDEX.md`
- `knowledge/KNOWN-FAILURES.md`
- `knowledge/ZBLOG-DEVELOPMENT-KNOWLEDGE.md`
- `knowledge/REUSE-GATE.md`
- `docs/v4.0.0/PRD-v1.0.md`
- `docs/v4.0.0/MIGRATION-DESIGN-v1.0.md`
- `docs/v4.0.0/REUSE-GATE-T4-v1.0.md`
- `docs/superpowers/plans/2026-08-27-v4-t4-analytics-admin.md`
- `.codex-tasks/08-v4-t4-analytics-admin.md`

Local Codex should reconcile to the real latest remote HEAD and execute the entire T4 task without step-by-step user prompts. The local Codex lacking a Notion connector is not a blocker; ChatGPT controller owns final Notion context/writeback.

## Hard constraints

- Preserve v3 raw logs/historical tables and T3 v4 data.
- Never reinterpret `vs_DurationMs` as visitor dwell.
- Historical ranges without v4 lifecycle coverage must display partial/unavailable, not fabricated zero.
- Do not render raw `se_SessionKey`; VisitorHash display must be masked/truncated.
- IP display follows configured privacy mode.
- Raw visit/session/event lists use indexed keyset/cursor pagination; normal admin requests must not perform unbounded full-table scans.
- Real Windows Z-Blog admin/runtime verification is mandatory for T4 completion.
- GitHub CI cannot substitute for the local admin/runtime gate.
- T4 completion does not authorize merge/tag/release; it advances to T5 only.

## Next action

T4 is complete and this execution stops here as requested. The current task must not enter T5 or create a merge, tag, or release. If continuing later, begin T5 only after a separate explicit request; do not repeat T4 Tasks 1–10.

## Update discipline

Record only observed facts. Planned commands are not completion evidence. Add reusable knowledge/failure entries only when verified. Do not mark T4 complete until implementation, authorized admin UI/runtime checks, SQL/semantics checks and exact-SHA CI have all passed.
