# xz_visit_stats v4.0 — T3 Reuse Gate v1.0

Date: 2026-08-27
Branch: `feature/visit-stats-4.0`
Phase: T3 — session, page lifecycle and event collection foundation

## Decision

**BUILD + SELECTIVE REUSE**

This gate is prospective only. It does not rewind or invalidate the completed T2 audit/migration design or the existing T3 handoff.

## Scope evaluated

T3 needs decisions for:

- session identification and timeout semantics;
- page sequence and browser lifecycle/dwell semantics;
- code event collection;
- IPv4/IPv6 single-address and CIDR filtering;
- session/event metric aggregation and query contracts;
- Z-BlogPHP runtime integration and migration behavior.

## Candidate 1 — Z-BlogPHP official runtime and existing plugin framework

Source:

- https://github.com/zblogcn/zblogphp
- https://github.com/zblogcn/docs-zblogphp

Decision: **USE**

Use the existing Z-BlogPHP plugin hooks, database/config APIs and the repository's current `inc/upgrade/` migration framework. Do not introduce a second framework for plugin lifecycle, persistence or migrations.

Reasoning:

- This is the native runtime boundary of the plugin.
- T2 real-environment audit already validated the current Z-BlogPHP/MySQL/PHP integration.
- Replacing native integration would add risk without adding T3 capability.

## Candidate 2 — mlocati/ip-lib

Source:

- https://github.com/mlocati/ip-lib
- pinned candidate release: `1.22.0`

Decision: **REUSE for IP/CIDR parsing and matching only**

Observed candidate properties:

- MIT license.
- PHP requirement `>=5.3.3`.
- No external runtime dependencies.
- Supports IPv4, IPv6 and CIDR/range handling.
- Test workflow and coverage are maintained.
- Latest release observed during this gate: `1.22.0`, published 2025-10-15.
- Repository is active, not archived, and reported zero open issues at gate time.

Integration boundary:

- Reuse only normalized address/range parsing and membership checks needed by `xz_visit_stats_ip_filters`.
- Wrap the library behind the plugin's own small IP-filter adapter so the collector does not depend on third-party classes throughout the codebase.
- Pin the version used by the plugin.
- Bundle the required library files and MIT license in the distributable plugin so production does not require running Composer.
- Do not use the library for trusted-proxy selection or visitor-IP extraction; those remain governed by the plugin's existing security boundary.

Acceptance additions:

- IPv4 single IP and CIDR tests.
- IPv6 single IP and CIDR tests.
- equivalent IPv6 textual forms normalize to one rule identity.
- invalid ranges fail closed without affecting the collector.
- filtered requests must not write main log/session/page/event rows.

## Candidate 3 — Matomo

Source:

- https://github.com/matomo-org/matomo

Decision: **REFERENCE ONLY — DO NOT IMPORT OR FORK**

Observed candidate properties:

- Mature and actively maintained analytics platform.
- GPL-3.0 licensed.
- Very large application/repository with its own architecture, database model, UI, tracker and release lifecycle.

Useful reference areas:

- separation between raw visit/action data and report aggregation;
- visitor/session semantics;
- event metrics and privacy boundaries;
- incremental reporting rather than scanning raw history for every dashboard request.

Why it is not reused directly:

- importing or forking Matomo would be disproportionate for a lightweight Z-Blog plugin;
- GPL-3.0 code reuse would introduce distribution/license obligations that are unnecessary for T3;
- its schema, runtime and operational model do not match the audited Z-BlogPHP plugin environment;
- it would increase package size, upgrade complexity and collector hot-path risk.

No Matomo source code should be copied into `xz_visit_stats` under this gate.

## T3 implementation decision by subsystem

| Subsystem | Decision | Boundary |
| --- | --- | --- |
| Z-Blog hooks/config/DB integration | USE | Existing Z-Blog/plugin APIs |
| v4 migration | BUILD | Extend existing `inc/upgrade/`; no second migration system |
| Session identity/timeout/sequence | BUILD | Project-specific v4 schema and PRD semantics |
| Page lifecycle/dwell/bounce | BUILD | Native browser lifecycle/Beacon + project-specific validation |
| Code events | BUILD | Minimal allowlisted event contract defined by v4 PRD |
| IPv4/IPv6/CIDR matching | REUSE | `mlocati/ip-lib` 1.22.0 behind plugin adapter |
| Session/event rollups | BUILD | Incremental/indexed queries matching audited MySQL 5.7 environment |
| Analytics architecture | REFERENCE | Matomo concepts only; no code import/fork |

## Safety and compatibility constraints carried forward

- Preserve all v3 raw logs and historical tables.
- `vs_DurationMs` remains server processing duration and must never become visitor dwell time.
- v4 migration remains idempotent and must stop on incompatible same-name structures.
- IP filtering executes only after trusted-proxy/client-IP resolution and before writing main log/session/page/event data.
- No channel tracking, heatmaps or screen recording.
- T3 still requires real Windows Z-Blog runtime verification.
- No PR merge, tag or release in T3; Release Gate remains `NOT READY`.

## Execution handoff

Local Codex should now continue `.codex-tasks/07-v4-t3-foundation.md` from the current remote branch HEAD, load this gate as T3 implementation input, perform RED → GREEN → REFACTOR, deploy only to the authorized local Z-Blog test runtime, fix/retest failures, push the verified T3 implementation, wait for CI, and write observed results back to `knowledge/PROJECT-STATE.md` and Notion.
