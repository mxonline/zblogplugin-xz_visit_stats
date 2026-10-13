# xz_visit_stats Project State

## State contract

This is the human/agent-readable checkpoint for the current major-version flow. At every task start, reconcile it against the real Git working tree, remote branch, CI and runtime evidence. Real Git/runtime/CI evidence has higher authority than any recorded SHA here.

## Current project

- Project: `xz_visit_stats`
- Target major version: `4.0.0`
- Repository: `mxonline/zblogplugin-xz_visit_stats`
- Development branch: `feature/visit-stats-4.0`
- Current phase: `T5 — Windows local final acceptance and release preparation`
- Phase status: `T5 REOPENED — review fixes and runtime evidence verified; final authenticated admin UI recheck remains externally blocked` (T4 remains complete; the former T5 PASS is historical)
- Release Gate: `BLOCKED` — current admin visual/responsive evidence cannot be refreshed without an authenticated local Z-Blog admin session. Candidate source exact-SHA CI Run `38353324295` passed.
- PR/Merge/Tag/Release: authorized by the current user only after all existing documented release gates pass; none has occurred

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
- T5 began from authoritative baseline `af3cc7137555257c379877cb0d34ce7478220576`; exact baseline Actions Run `38296309009` succeeded. Candidate source commit `13def711c5fbc52de223d260b0315a3596570f8e` was pushed and exact-SHA GitHub Actions Run `38304017846` completed successfully. The official T5 task and implementation-plan status were updated and read back. See the T5 acceptance record below for the local runtime, tests, data preservation, and candidate package evidence.

## T5 local acceptance and release preparation — 2026-10-13

- Reused the established Windows test site; no parallel runtime, reset/clean, force push, merge, tag, release, production deployment, database restore, or App Centre upload. IIS 10, PHP `8.3.8`, Z-BlogPHP `173540`, MySQL `5.7.38-log`.
- Existing T2/T3/T4 plugin backups and the `6,993,139`-byte database backup remain preserved. Before installing the T5 ZIP, copied the prior runtime plugin tree to `D:\wwwroot\xz_visit_stats-runtime-backups\xz_visit_stats.backup-t5-candidate-20261013-041500` (92 files).
- v3-to-v4 fresh install, synthetic v3-history migration, idempotent repeat, and deactivate/reactivate used the real MySQL 5.7 connection/adapter with isolated temporary tables; persistent DDL was zero. The current site was already on schema `4.0.0`, so no destructive downgrade was performed.
- Real plugin-manager disable/enable completed successfully. Disabled front-end requests did not create visit rows; re-enable preserved historical data. The final v4.0.0 candidate ZIP was then installed into the existing local plugin path. A read-only schema audit after installation confirms the plugin remains enabled, Beacon remains disabled, and historical counts remain raw logs `313`, sessions `12`, session pages `25`, events `2`; no test export tasks or IP rules remain.
- Isolated metric fixtures matched direct SQL and UI: total PV `5`, human PV `4`, UV `3`, IP `3`, spider PV `1`, 4xx `1`; session bounce `50%`, lifecycle dwell `20,000 ms`; event total `3`, unique visitors `2`, mean `1.50`. IP filter, drill-down, allowed event parameters, CSV permission/ownership, and privacy checks passed.
- All ten v4 views rendered; responsive checks at 680/800/1100 px passed. Direct missing-path 404 is short-circuited by IIS before PHP; the report's 404 metric was verified with the isolated fixture. This collector-path limitation is documented in `docs/v4.0.0/T5-ACCEPTANCE-2026-10-13.md`.
- Test rows and artifacts were removed by exact markers/IDs; the non-test raw-log count remained `313`. Existing history and all T2/T3/T4 backups remain.
- Automated checks: PHPUnit `69 tests / 494 assertions`; all 16 asset JavaScript files passed Node syntax; `scripts/local-verify.ps1 -SkipHttp` passed PHP lint, PHPUnit, and diff checks. PHP logs are routed to syslog (no file configured); the Windows Application log had zero PHP/FastCGI/IIS warning/error events in the reviewed 24-hour window. The available IIS W3C log's historical 5xx routes are described in the acceptance record.
- Candidate artifact: `dist/xz_visit_stats-v4.0.0.zip`, SHA-256 `03DA00ACED38BA77B2CA0C59DCC51422456DD0830A999FF777B4D930EC0748E1`; 95 allowlisted entries, 0 missing, 0 extra, source/archive hashes match. Required runtime library and licenses, Apache and IIS export protections are included; the exact ZIP is installed and its 95 file hashes match the source. Post-install Safe Mode/read-only audit confirms 313 logs, 12 sessions, 25 session pages, 2 events, 0 export tasks; no secrets or visitor-level rows were exported.
- Exact candidate source SHA `13def711c5fbc52de223d260b0315a3596570f8e` / GitHub Actions Run `38304017846` are successful and `head_sha` matches. Release Dry Run result is `可发布`; no conflicting v4.0.0 tag or release record exists. Release Gate is `PASS`; formal status remains `NOT RELEASED`. Notion task and implementation-plan readbacks succeeded. No merge, tag, GitHub Release, production deployment, database restore, or App Centre upload occurred.

## T5 review recheck — 2026-10-13 (supersedes the previous PASS decision)

- Independent code review invalidated the earlier Release Gate PASS. The official T5 Notion task was set back to in progress; the existing implementation plan and this repository state are being continued. No duplicate task, Runtime, or Gate was created.
- Confirmed review defects in code: stale browser session key could be reused after timeout and first-page lifecycle key was not synchronized; trend UV/IP were not exact at week/month grain; v4 sessions/pages/events were omitted from retention; overview lacked new/returning counts; leading CSV controls bypassed formula detection; a PHP 7.1-incompatible JSON flag was used; trend UI omitted dimensions.
- Implemented TDD fixes so far: server-authoritative session IDs plus `Set-Cookie` sync; exact per-grain traffic distinct aggregation; report filters are applied to session metrics via recorded visit rows; v4 retention/manual purge coverage and confirmation text; overview new/returning classification with the v3-history caveat; trend controls; control/whitespace-safe CSV prefixing; guarded JSON flag. Focused suites currently pass (PHPUnit 37 tests / 218 assertions across six focused files). These are not the final full regression result.
- Added `scripts/t5-isolated-mysql-migration.ps1` and ran it against the established Windows Z-BlogPHP `173540`, PHP `8.3.8`, MySQL `5.7.38-log` adapter. Fresh install created all 12 expected plugin tables; a cloned v3-compatible raw-log schema with one synthetic history row migrated to v4, the migration repeated successfully, schema compatibility remained true, the row was unchanged, and all random-prefix test tables were removed. No production/history tables were written or dropped.
- At the time of this checkpoint, the modified source had not yet been synchronized to the runtime, fully regression-tested, packaged, pushed, or checked by exact-SHA CI. That checkpoint is superseded by the final recheck below.
- The local runtime Safe Mode audit confirms current MySQL `5.7.38-log`, PHP `8.3.8`, Z-BlogPHP `173540`; optional local GeoIP data is not configured. Carrier/operator reporting remains unavailable without a local source and must not call an external lookup.

## T5 final review recheck — 2026-10-13

- Candidate source commit: `fb5bf02e68a1485aa3c6ac34cd2fcf958702caf8`, pushed normally to `feature/visit-stats-4.0`. GitHub Actions Run `38353324295` completed `success` with exact `head_sha` match. PHP Syntax, UI Terminology, PHPUnit, Semgrep, and the report-only PHPStan job completed. The PHPStan annotations are on unmodified legacy files and are not release-blocking under the existing report-only policy.
- Existing branch `feature/visit-stats-4.0`; source checkpoint before final documentation/commit: `a8f866df014a8555477b7abf3af59d5f275f17f7`. T4 remains complete at `af3cc7137555257c379877cb0d34ce7478220576`, exact CI Run `38296309009` success. No reset, clean, force push, T4 rerun, merge, tag, release, production operation, or App Centre upload occurred.
- Review fixes are in the source: database-authoritative session keys and cookie synchronization, exact grain-specific trend UV/IP, session report filters and v4 retention, bounded new/returning classification with v3-history caveat, CSV control-prefix neutralization, PHP 7.1-safe JSON flags, and trend filter controls. Full PHPUnit is `78 tests / 543 assertions` PASS; PHP 8.3 repository syntax PASS; PHP 7.1 syntax PASS for 43 runtime PHP files; Node syntax PASS for all 16 shipped JavaScript files.
- The final installed release candidate has 94 allowlisted ZIP entries and SHA-256 `477EA666FD9DF3CF675B614135A6A6C9901D41FE654B2013B4F5D66B9F75F0BB`. ZIP paths exactly match the allowlist and archived hashes match source. Installation copied all 94 files after hashing a 101-file pre-install runtime backup; 7 additional runtime files remain untouched.
- Real MySQL 5.7 isolated lifecycle/migration recheck passed: all 12 expected tables on fresh install and v3 upgrade; exact 30-column v3.0.0 log schema; one synthetic history row's ID/path/path-key/visit-time unchanged across migration/repeat; no historical rows touched; all temporary-prefix tables removed. Disable/enable hooks passed; while disabled, the homepage returned 200 with zero marked tracking rows.
- Final IIS/PHP HTTP smoke returned homepage 200, page-type 404, and crawler/referer 200. Direct SQL on isolated marked requests returned PV 2 / human PV 1 / bot PV 1 / UV 1 / IP 1. Exact cleanup removed 2 logs, 2 pages, 2 sessions and left zero marked rows.
- A same-browser two-page lifecycle probe reused one session key; direct SQL returned PV 2 / UV 1 / IP 1 / one session / two pages; first-page RUM/lifecycle dwell was recorded once at 7,007 ms; one event stored only allowlisted `action`, `category`, and `label`. Cleanup returned all marked log/page/session/event/RUM counts to zero, and Beacon was restored disabled.
- Read-only MySQL 5.7 audit after all probes: plugin `4.0.0` enabled; Beacon 0; retention 180 days; automatic cleanup 0; IP mode full; no GeoIP/operator database configured. Historical counts remain raw logs 313, sessions 12, pages 25, events 2, RUM 6, exports 0, IP rules 0. T2/T3/T4 code and database backups remain preserved.
- Six MySQL EXPLAIN probes used the expected indexes for log time, visitor/time, IP/time, active sessions, page-log drill-down, and event time. The small local row count supports plan verification, not large-load latency claims.
- Admin visual/responsive recheck after review fixes remains unverified: earlier 680/800/1100 px results belong to the superseded candidate. The current browser has no authenticated admin session. No login automation or credentials were used. This is the current external blocker.
- Final Release Dry Run result: `不可发布` because the post-review authenticated admin visual/responsive recheck remains blocked. Exact source CI passed as recorded above. Release Gate is `BLOCKED`; formal status remains `NOT RELEASED`. No PR merge, tag, GitHub Release, production deployment/database operation, or App Centre upload is permitted while blocked.

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
