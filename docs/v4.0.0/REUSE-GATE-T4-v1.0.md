# xz_visit_stats v4.0 — T4 Reuse Gate v1.0

## 1. Gate scope

T4 implements the analytics/admin experience on top of the verified T3 session, page lifecycle, event and IP-filter foundation. This gate evaluates generic UI/chart/table/export choices before implementation. It does not reopen T2/T3 and does not authorize merge, tag or release.

## 2. Existing project assets

The current `feature/visit-stats-4.0` branch already ships and uses:

- `assets/echarts.min.js` for analytics charts;
- `assets/alpine.min.js` for lightweight admin interactivity;
- `assets/filter.js`, `assets/admin.js`, `assets/admin.css` and existing v3 page-specific scripts;
- server-side filter/query helpers in `inc/query.php`, `inc/query_v2.php`, `inc/v3_query.php`;
- cursor/keyset access for visit records;
- an existing bounded CSV export path in `main.php`;
- Z-BlogPHP admin shell, permissions and CSRF facilities.

T4 must extend these assets rather than introduce a parallel frontend stack.

## 3. Candidate evaluation

### Apache ECharts

- Capability: KPI/trend/dimension charts.
- Upstream: `apache/echarts`.
- License: Apache-2.0.
- Maintenance: active in 2026.
- Project fit: already vendored, already integrated, no runtime package manager required.
- Decision: **USE EXISTING**. Do not add Chart.js/ApexCharts/Highcharts or another chart engine.
- Distribution requirement: preserve/verify required third-party license/notice material in the plugin package.

### Alpine.js

- Capability: small admin-side state, toggles, column chooser, drawer/modal state and progressive interaction.
- Upstream: `alpinejs/alpine`.
- License: MIT.
- Maintenance: active in 2026.
- Project fit: already vendored and lightweight.
- Decision: **USE EXISTING**. Do not introduce Vue/React/Svelte for T4.
- Distribution requirement: preserve/verify required third-party license notice.

### DataTables / grid frameworks

- Capability: client-side table sorting/filtering/pagination.
- Fit: poor for large raw visit/session datasets because T4 requires server-side filters, keyset/cursor pagination and controlled columns; would duplicate existing query contracts and increase JS/package weight.
- Decision: **BUILD PROJECT-SPECIFIC** server-rendered/table UI with Alpine state where needed. No new grid framework.

### Queue/job framework for exports

- Capability: background export execution.
- Fit: a generic PHP queue would add worker/runtime/deployment requirements that do not match a lightweight Z-Blog plugin. T3 already created `xz_visit_stats_export_tasks` and the project already has bounded synchronous CSV export semantics.
- Decision: **BUILD PROJECT-SPECIFIC** export task state machine using the existing v4 export table and Z-Blog request/admin execution boundaries. No Redis/RabbitMQ/Symfony Messenger/Laravel queue dependency.

### Date picker / filter libraries

- Existing filter controls and native date inputs are sufficient for today/yesterday/7d/30d/custom ranges.
- Decision: **USE EXISTING / NATIVE**. No new datepicker dependency.

### Matomo / 51.LA

- Capability: mature analytics architecture and reporting semantics.
- Fit: too large and incompatible as an embedded Z-Blog subsystem; 51.LA is the product benchmark, not a code dependency.
- Decision: **REFERENCE ONLY** for information architecture, drill-down semantics and privacy boundaries. Do not import, fork or copy code.

## 4. Final Reuse Gate decision

**USE EXISTING + BUILD PROJECT-SPECIFIC**

- USE existing Z-BlogPHP admin shell, permission/CSRF APIs, ECharts, Alpine, current filter/query helpers and current CSV-safe escaping.
- BUILD v4 session/event report queries, drill-down contracts, directory-rule UI, visitor/content/source/event report composition, column preferences and export-task orchestration inside xz_visit_stats.
- REFERENCE ONLY Matomo/51.LA semantics.
- ADD NO new frontend framework, chart library, table/grid library, datepicker or queue framework during T4 unless a new Reuse Gate is explicitly recorded first.

## 5. Hard constraints carried from T3

- Preserve all v3 raw logs and historical tables.
- `vs_DurationMs` remains server processing time and must never be displayed/calculated as visitor dwell.
- Visitor dwell/bounce/page depth come only from v4 Session/Page lifecycle data.
- Historical v3 periods without lifecycle data must show unavailable/partial rather than fabricated zero values.
- VisitorHash remains irreversible and must not be exposed as a reusable cross-site identifier.
- Raw-list queries use indexes and keyset/cursor pagination; common charts use rollups/indexed bounded queries.
- T4 requires real Windows Z-Blog admin/runtime verification before completion.
- Release Gate remains `NOT READY`; no merge/tag/release in T4.
