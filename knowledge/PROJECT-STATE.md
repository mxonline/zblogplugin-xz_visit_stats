# xz_visit_stats Project State

## State contract

This is the human/agent-readable checkpoint for the current major-version flow. At every task start, reconcile it against the real Git working tree, remote branch, CI and runtime evidence. Real Git/runtime/CI evidence has higher authority than any recorded SHA here.

## Current project

- Project: `xz_visit_stats`
- Target major version: `4.0.0`
- Repository: `mxonline/zblogplugin-xz_visit_stats`
- Development branch: `feature/visit-stats-4.0`
- Current phase: `T4 — analytics/admin reports, filters and session drill-down`
- Phase status: `IN PROGRESS / CODEX HANDOFF READY`
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

Execute `.codex-tasks/08-v4-t4-analytics-admin.md` from the real latest `feature/visit-stats-4.0` HEAD. Complete plan Tasks 1–10, perform real local admin/runtime and MySQL `EXPLAIN` verification, push the implementation, obtain exact-SHA CI success, update this state with observed evidence, and hand the final six-gate report to ChatGPT for Notion writeback. Stop before T5/merge/tag/release.

## Update discipline

Record only observed facts. Planned commands are not completion evidence. Add reusable knowledge/failure entries only when verified. Do not mark T4 complete until implementation, authorized admin UI/runtime checks, SQL/semantics checks and exact-SHA CI have all passed.
