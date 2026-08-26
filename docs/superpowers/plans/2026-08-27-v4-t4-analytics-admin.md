# xz_visit_stats v4.0 T4 Analytics Admin Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the v4 analytics/admin experience for overview, realtime, trend, visit detail/session drill-down, source, content, visitor, spider, event and settings/data-management reports on top of the verified T3 data model.

**Architecture:** Preserve the current Z-BlogPHP admin shell and v3 report/query code. Add focused v4 query modules for sessions/events/content and compose them in `main.php`; reuse the already-vendored ECharts and Alpine.js assets. Common charts use rollups/indexed bounded queries, raw lists use keyset/cursor pagination, and legacy v3 views/deep links remain compatible.

**Tech Stack:** Z-BlogPHP, PHP 8.3 verified runtime, MySQL 5.7 verified runtime, ECharts already vendored, Alpine.js already vendored, PHPUnit, existing GitHub Actions.

**Spec:** `docs/v4.0.0/PRD-v1.0.md`, `docs/v4.0.0/MIGRATION-DESIGN-v1.0.md`, `docs/v4.0.0/REUSE-GATE-T4-v1.0.md`.

## Global Constraints

- Development branch: `feature/visit-stats-4.0`; start from the real remote HEAD, never rewind to a recorded baseline.
- T3 implementation `521d68b0d4e67d07b3e7657c502f292295c366f3` and verified-delivery chain through `95cab5150a75580c8d459002abfd184860a2e156` remain authoritative historical evidence.
- Preserve all v3 raw logs and the nine audited historical plugin tables; no destructive DDL/DML.
- `vs_DurationMs` is server processing time only. Visitor dwell/session duration must come from v4 session/page lifecycle fields.
- Historical ranges without v4 lifecycle data show `unavailable`/`partial`; never invent zero dwell or zero bounce.
- Do not implement channel tracking, heatmaps, screen recording or a visual event editor.
- Do not add another chart library, frontend framework, grid library, datepicker or queue framework without a new recorded Reuse Gate.
- Only root/admin-authorized users can access report export, data-management and settings actions; state-changing POST operations require CSRF protection.
- Raw visit/session/event lists must be index-backed and keyset/cursor paginated; do not use unbounded OFFSET or full-table scans in normal admin requests.
- T4 must be deployed to the verified local Windows Z-Blog test runtime and exercised before it can be marked complete.
- T4 must not merge to `main`, tag or release. Release Gate remains `NOT READY` until T5.

---

### Task 1: Freeze T4 report contracts and baseline tests

**Files:**
- Create: `inc/v4_report_filters.php`
- Create: `tests/V4ReportFiltersTest.php`
- Modify: `include.php`

**Interfaces:**
- Consumes: existing `xz_visit_stats_v3_filters()`, `xz_visit_stats_v2_range()`, query sanitizers and PRD time/filter semantics.
- Produces: `xz_visit_stats_v4_report_filters(array $source): array` and `xz_visit_stats_v4_report_availability(array $filters, array $coverage): array`.

- [ ] **Step 1: Reconcile the working tree** — run `git status --short`, current branch/HEAD, `git fetch origin`, inspect remote HEAD, preserve unrelated uncommitted edits, and read `AGENTS.md`, `knowledge/PROJECT-STATE.md`, `knowledge/INDEX.md`, T4 Reuse Gate and this plan.
- [ ] **Step 2: Write failing filter tests** covering `today`, `yesterday`, `7d`, `30d`, custom start/end, invalid ranges, device, new/returning visitor, source, country/province, visit type and cursor/page-size bounds.
- [ ] **Step 3: Run the focused tests** and confirm RED for missing v4 filter functions, not for broken fixtures.
- [ ] **Step 4: Implement the minimal v4 filter adapter** by reusing existing v3 sanitizers; do not create a second independent date parser.
- [ ] **Step 5: Add availability semantics** that can return `available`, `partial` or `unavailable` for lifecycle-dependent metrics based on v4 session coverage rather than silently returning zero.
- [ ] **Step 6: Run focused PHPUnit and PHP syntax checks**, then commit the coherent report-contract change.

### Task 2: Session/event data-access modules

**Files:**
- Create: `inc/v4_sessions_query.php`
- Create: `inc/v4_events_query.php`
- Create: `tests/V4SessionsQueryTest.php`
- Create: `tests/V4EventsQueryTest.php`
- Modify: `include.php`

**Interfaces:**
- Produces:
  - `xz_visit_stats_v4_session_summary(array $filters): array`
  - `xz_visit_stats_v4_session_trend(array $filters, string $grain): array`
  - `xz_visit_stats_v4_session_rows(array $filters, string $cursor, int $limit): array`
  - `xz_visit_stats_v4_session_detail(int $sessionId): ?array`
  - `xz_visit_stats_v4_event_summary(array $filters, int $limit = 50): array`
  - `xz_visit_stats_v4_event_rows(array $filters, string $cursor, int $limit): array`
  - `xz_visit_stats_v4_event_detail(int $eventId): ?array`

- [ ] **Step 1: Write failing query-contract tests** using the project DB test double/fixtures for completed single-page bounce, completed multi-page non-bounce, active/incomplete session, NULL dwell, event unique visitor and event average-per-user cases.
- [ ] **Step 2: Verify RED**, then implement session summary/trend queries using indexes defined in the v4 migration design.
- [ ] **Step 3: Implement keyset session rows** ordered by stable `(se_StartedAt,se_ID)` or an equivalently indexed deterministic cursor; no OFFSET pagination.
- [ ] **Step 4: Implement session detail** returning session header plus ordered page sequence from `xz_visit_stats_session_pages`; expose no raw cookie/session secret and mask VisitorHash for display.
- [ ] **Step 5: Implement event summary/rows/detail** with allowlisted event params only and index-backed filters by name/session/visitor/path/time.
- [ ] **Step 6: Add EXPLAIN-oriented test/diagnostic helpers** for representative MySQL 5.7 queries so runtime verification can confirm index use.
- [ ] **Step 7: Run focused tests, full PHPUnit and syntax checks**, then commit.

### Task 3: Overview and trend reports

**Files:**
- Create: `inc/v4_reports.php`
- Create: `tests/V4ReportsTest.php`
- Modify: `main.php`
- Modify: `assets/overview.js`
- Modify: `assets/admin.css`

**Interfaces:**
- Produces:
  - `xz_visit_stats_v4_overview(array $filters): array`
  - `xz_visit_stats_v4_trend_report(array $filters, string $grain): array`
- Reuses: v3 PV/UV/IP/source/page/spider/error rollups and T4 session query module.

- [ ] **Step 1: Write failing report-composition tests** asserting the overview payload contains PV, UV, IP, session count, bounce rate, average visitor dwell, new/returning split, source, popular pages, entry pages, geo, device/browser/OS and explicit availability metadata.
- [ ] **Step 2: Implement overview composition** without copying the v3 query logic; call existing v2/v3 summary functions and the new v4 session summary.
- [ ] **Step 3: Implement trend grain validation** for hour/day/week/month. Use existing hourly/daily rollups for PV/UV/IP and bounded/indexed v4 session queries for session metrics. Do not scan the raw visit table for routine 7d/30d charts.
- [ ] **Step 4: Add comparison payloads** for previous comparable period where current v3 comparison utilities support it; label unavailable comparisons rather than fabricating them.
- [ ] **Step 5: Update the overview UI** to show the six PRD KPIs and dimension blocks. ECharts remains the only chart engine.
- [ ] **Step 6: Add the dedicated `trend` view** with metric/grain controls and device/new-returning/source/geo filters.
- [ ] **Step 7: Verify JS syntax, PHP syntax and PHPUnit**, then commit.

### Task 4: Realtime and session drill-down

**Files:**
- Modify: `main.php`
- Modify: `inc/realtime.php`
- Create: `assets/v4_sessions.js`
- Modify: `assets/admin.css`
- Create: `tests/V4SessionDrilldownTest.php`

**Interfaces:**
- Reuses `xz_visit_stats_v4_session_rows()` and `xz_visit_stats_v4_session_detail()`.
- Produces an admin-only session detail endpoint/view through `main.php` parameters; no public session-detail endpoint.

- [ ] **Step 1: Add failing tests** for 5/15/30-minute active windows and session-detail ordering.
- [ ] **Step 2: Extend realtime payloads** to include current source, entry page, latest page, browser/device, region and stable internal Session ID for admin drill-down.
- [ ] **Step 3: Add session drill-down UI** as a same-page drawer/panel or dedicated `session` admin view using Alpine.js. Display started/last seen, source snapshot, entry/exit, page count, lifecycle dwell and ordered page sequence.
- [ ] **Step 4: Clearly separate server response time from visitor dwell** in labels and code. A row may show both only when each source field is independently available.
- [ ] **Step 5: Ensure raw `se_SessionKey` is never rendered** and VisitorHash is masked/truncated for display.
- [ ] **Step 6: Run tests and syntax checks**, then commit.

### Task 5: Visit detail, source, content and visitor reports

**Files:**
- Create: `inc/v4_content_query.php`
- Create: `inc/v4_visitors_query.php`
- Create: `tests/V4ContentQueryTest.php`
- Create: `tests/V4VisitorsQueryTest.php`
- Modify: `main.php`
- Modify: `assets/v3_admin.js` or create `assets/v4_admin.js` if responsibilities would otherwise become mixed
- Modify: `assets/admin.css`

**Interfaces:**
- Produces:
  - `xz_visit_stats_v4_content_report(array $filters): array`
  - `xz_visit_stats_v4_directory_report(array $filters): array`
  - `xz_visit_stats_v4_host_report(array $filters): array`
  - `xz_visit_stats_v4_visitor_report(array $filters): array`

- [ ] **Step 1: Extend visit-record tests** so the admin table can present visit time, masked visitor ID, IP according to `ip_mode`, geo, browser, OS, device, source type/domain, entry/current page, keyword, session page count, lifecycle dwell and HTTP status.
- [ ] **Step 2: Add column chooser state** using Alpine/localStorage with an allowlisted column set. Column preferences contain only column names/booleans and no visitor data.
- [ ] **Step 3: Preserve current keyset record pagination and saved filters**; add a session drill-down link for rows that can be associated with a v4 session.
- [ ] **Step 4: Enhance source report** with direct/search/external/AI/UTM groupings, search-engine/source-domain/keyword details and existing AI/UTM fields. Do not create a standalone channel-tracking product module.
- [ ] **Step 5: Implement content report** for page ranking, entry pages, directory rules and host/domain analysis. Directory queries must use bounded/indexable path/PathKey logic and must not re-evaluate every historical row on each request.
- [ ] **Step 6: Implement visitor report** for new/returning, depth/page count, device/OS/browser, screen/viewport/language, geo, carrier when available, and IP under current privacy mode.
- [ ] **Step 7: Preserve legacy `pages`, `ip`, `environment`, `campaign`, `errors` and `performance` deep links** either as compatible views or explicit aliases/subviews; do not silently remove v3 capabilities.
- [ ] **Step 8: Run tests and syntax checks**, then commit.

### Task 6: Spider and event analytics

**Files:**
- Modify: `main.php`
- Modify: `assets/spider.js`
- Create or modify: `assets/v4_events.js`
- Create: `tests/V4AdminEventsTest.php`

**Interfaces:**
- Reuses existing bot classification and T4 event query module.

- [ ] **Step 1: Add failing spider report tests** for crawler type, count, IP presentation, URL, last visit and status-code filters while retaining Googlebot/Baiduspider/bingbot compatibility.
- [ ] **Step 2: Implement spider view improvements** without changing collector classification semantics unless a failing test proves a bug.
- [ ] **Step 3: Add event dashboard** with event total, trigger count, unique triggering users, average triggers/user, event-name ranking and keyset event detail list.
- [ ] **Step 4: Add event detail** showing only allowlisted stored params and linked session/path when available.
- [ ] **Step 5: Show the supported code API example** based on `window.xzVisitStatsEvent(name, params)` but do not build a visual event editor.
- [ ] **Step 6: Run tests and syntax checks**, then commit.

### Task 7: Settings, IP-filter CRUD, directory rules and controlled exports

**Files:**
- Create: `inc/v4_admin_actions.php`
- Create: `inc/v4_export.php`
- Create: `tests/V4AdminActionsTest.php`
- Create: `tests/V4ExportTest.php`
- Modify: `main.php`
- Modify: `inc/settings.php`

**Interfaces:**
- Produces:
  - `xz_visit_stats_v4_ip_filter_create/update/delete(...)`
  - `xz_visit_stats_v4_directory_rule_create/update/delete(...)`
  - `xz_visit_stats_v4_export_request(int $userId, array $filters): array`
  - `xz_visit_stats_v4_export_run(int $taskId): array`
  - `xz_visit_stats_v4_export_status(int $userId, int $taskId): ?array`

- [ ] **Step 1: Write failing admin-action tests** for permission, CSRF, invalid CIDR/IP, duplicate normalized rules, directory include/exclude rule validation and unauthorized export access.
- [ ] **Step 2: Implement IP-filter CRUD** on the T3 table using existing `mlocati/ip-lib` adapter for normalization/matching; do not duplicate CIDR parsing.
- [ ] **Step 3: Implement directory-rule CRUD** with deterministic order and safe pattern length/type validation.
- [ ] **Step 4: Implement export-task state machine** `pending -> running -> completed|failed`, server-generated filenames, dedicated plugin export directory, bounded filters/row counts and root/admin ownership checks.
- [ ] **Step 5: Keep the existing bounded direct CSV path for backward compatibility** but route new v4 large/admin export behavior through the task table. CSV cells continue to use the existing formula-injection-safe escaping.
- [ ] **Step 6: Add settings sections** for collection, Beacon, IP filters, spider policy, retention, export/data management, privacy explanation and maintenance state.
- [ ] **Step 7: Add cleanup rules for expired export files/tasks** that only touch the dedicated export directory and exact task-owned files.
- [ ] **Step 8: Run focused/full tests and syntax checks**, then commit.

### Task 8: Information architecture and UI consistency pass

**Files:**
- Modify: `main.php`
- Modify: `assets/admin.css`
- Modify/create: `assets/v4_admin.js`
- Modify: `config/ui-terminology.json` only if new visible terminology needs CI coverage
- Create: `tests/V4AdminUiContractTest.php`

**Interfaces:**
- Primary T4 views: `overview`, `realtime`, `trend`, `records`, `source`, `content`, `visitors`, `spider`, `events`, `settings`.
- Legacy aliases remain accepted for old v3 URLs.

- [ ] **Step 1: Add a failing UI contract test** for the primary view allowlist, legacy aliases and no forbidden product modules (`channel tracking`, heatmap, screen recording, visual event editor).
- [ ] **Step 2: Refactor only the routing/navigation boundary needed to make the primary information architecture clear**; do not rewrite the Z-Blog admin shell.
- [ ] **Step 3: Standardize page structure**: title/time range, KPI cards, chart/dimension section, filter bar, table/ranking and drill-down action.
- [ ] **Step 4: Verify responsive overflow/table behavior** and loading/empty/partial-data states. Empty data and pre-v4 unavailable metrics must be visually distinct.
- [ ] **Step 5: Ensure all output is escaped** and external referer/path text cannot inject markup.
- [ ] **Step 6: Run UI terminology checks, JS syntax, PHP syntax and PHPUnit**, then commit.

### Task 9: Windows runtime verification and performance gate

**Files:**
- Modify only if failures prove a code issue; update `scripts/local-verify.ps1` only when the generic verifier itself is wrong.
- Update after evidence: `knowledge/PROJECT-STATE.md`.

- [ ] **Step 1: Record source worktree status and back up the currently deployed runtime plugin** to a timestamped sibling directory before synchronization.
- [ ] **Step 2: Deploy only xz_visit_stats to `D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats`**, excluding Git metadata/tests/dev-only junk while preserving required vendored runtime licenses/sources.
- [ ] **Step 3: Verify plugin/admin boot** on `http://127.0.0.1`, with no new PHP Fatal/Warning/SQL errors caused by T4.
- [ ] **Step 4: Verify each primary T4 page** with an authorized local admin session: overview, realtime, trend, records, source, content, visitors, spider, events, settings. If no authorized local session is available, do not claim the UI gate passed.
- [ ] **Step 5: Verify filters and drill-down** for today/yesterday/7d/30d/custom, device/source/geo/new-returning, record -> session, realtime -> session and event -> session/path where available.
- [ ] **Step 6: Verify data semantics directly against SQL** for representative PV/UV/IP/session/bounce/dwell/event values. Confirm pre-v4 lifecycle metrics show partial/unavailable instead of zero.
- [ ] **Step 7: Verify IP-filter and directory-rule CRUD** using exact temporary test rules; remove only the rules created by this test.
- [ ] **Step 8: Verify controlled export** with an authorized admin, safe filename/location, status transitions, CSV injection escaping and inability of another/unauthorized user to download the task.
- [ ] **Step 9: Run `EXPLAIN` on representative 30d session/event/detail queries** and confirm expected indexes are used; no normal report may perform an unbounded raw-table scan. Record timings for the audited local dataset without pretending they predict production scale.
- [ ] **Step 10: Re-run v3 regression probes** for existing PV/UV/IP/spider/RUM/errors/performance/source pages and confirm v3 raw/history counts never decrease.
- [ ] **Step 11: If any runtime check fails**, use systematic debugging: preserve evidence, find root cause, make the smallest fix, rerun focused tests and the affected runtime gate.

### Task 10: Delivery, CI and controller handoff

**Files:**
- Update: `knowledge/PROJECT-STATE.md`
- Add reusable failure/knowledge entries only when supported by observed evidence.

- [ ] **Step 1: Run final local gates**: `git diff --check`, changed/full PHP syntax as appropriate, JS syntax for changed JS, PHPUnit, existing UI terminology check, secrets/generated-junk scan, and risk-driven Semgrep/PHPStan according to `AGENTS.md`.
- [ ] **Step 2: Review the final diff** for unrelated changes, duplicate libraries, forbidden modules, leaked credentials/visitor data and accidental runtime export files.
- [ ] **Step 3: Update project state** with exact implementation commits, local runtime evidence, query/performance evidence and the fact that T4 remains unreleased.
- [ ] **Step 4: Commit/push T4 implementation to `feature/visit-stats-4.0`**.
- [ ] **Step 5: Observe GitHub Actions** for the exact pushed SHA. On failure, read the real logs, fix locally, rerun tests and push again until CI passes or a genuine blocker is identified.
- [ ] **Step 6: Do not merge/tag/release.** T4 completion moves the project to T5 release preparation only.
- [ ] **Step 7: Return the mandatory six-gate report** with real evidence. ChatGPT controller performs final Notion writeback and only then marks the T4 task complete.

## Self-review coverage

This plan covers every PRD T4 surface: overview, realtime, trend, visit detail, source, content/directory/host, visitor, spider, events, settings/IP filtering/data management/export, filters, columns, session drill-down, privacy, bounded/indexed queries and real Windows verification. Existing v3 errors/performance/AI/UTM capabilities are preserved rather than removed. Explicit exclusions remain excluded.
