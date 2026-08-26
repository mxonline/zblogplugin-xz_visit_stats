# Z-Blog Development Knowledge for xz_visit_stats

This document contains reusable engineering rules for this plugin. It is intentionally narrower than the PRD and broader than a single task handoff.

## Runtime and compatibility

- Treat the real installed Z-BlogPHP environment as the runtime compatibility boundary for major-version work.
- Detect the current Z-Blog root, plugin root, PHP CLI and database version before runtime-sensitive work.
- Never modify `zb_system` core files as part of plugin development.
- Never edit unrelated plugins or site files.
- Keep the Git working tree separate from the deployed runtime copy; deploy a tested copy into the local Z-Blog environment for runtime verification.

Current v4 audited environment:

```text
Z-Blog root: D:\wwwroot\www.xzhao.net
Plugin root: D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats
PHP CLI:     D:\BtSoft\php\83\php.exe
Local site:  http://127.0.0.1
PHP:         8.3.8
Z-BlogPHP:   173540
MySQL:       5.7.38-log
```

These values are verified for the current v4 local environment but must still be re-detected when a future task may run on another machine/site.

## Database and migration rules

- Preserve historical plugin data by default.
- Use real schema audit output as migration input when available.
- Reuse the existing `inc/upgrade/` upgrade path instead of creating a second untracked migration system.
- Migrations must be idempotent.
- Before creating a table/index/column, check whether it already exists and whether the structure matches the expected contract.
- If a same-name object exists with an incompatible structure, stop with a clear error. Do not silently DROP/recreate it.
- Do not delete, rename or reinterpret v3 historical tables simply to make v4 cleaner.
- For v4, legacy `keywords`, `page_uv`, `pages` and existing raw visit history are preservation-sensitive.

## Metrics semantics

- `vs_DurationMs` is server processing duration only.
- Visitor dwell/page duration must come from trustworthy page lifecycle/Beacon semantics.
- Missing lifecycle evidence should remain unavailable/NULL rather than be fabricated from server timing.
- Bounce must be defined from completed/expired session semantics, not from server request duration.
- Historical v3 data without lifecycle evidence must not be presented as if it had v4 dwell/bounce fidelity.
- Every dashboard metric should have a traceable definition, source table/field and aggregation rule before UI implementation.

## Collector and hot-path rules

- Request collection is performance-sensitive.
- Keep collector responsibilities separated as complexity grows; avoid turning `inc/collector.php` into a single all-purpose module.
- Perform IP filtering after trusted-proxy/client-IP resolution but before writing the main log, session, page or event records.
- Reject or bound untrusted payload fields before persistence.
- Do not accept arbitrary JSON, cookies, tokens, private headers or unconstrained client parameters into analytics/event storage.
- For session/page lifecycle writes, design idempotency so retries and duplicated Beacon delivery do not create duplicate page sequence records.

## Session and page lifecycle rules

For v4 foundation work:

- Session keys must not expose raw visitor identifiers.
- Consecutive visits for the same visitor inside the configured timeout belong to the same session.
- A visit after timeout creates a new session.
- Entry-source snapshot belongs to the first page/session entry and must not be overwritten by later pages.
- Page sequence must be monotonic within a session.
- Client lifecycle duration must be validated for negative, impossible, future or excessive values.
- If a trustworthy leave/lifecycle event never arrives, dwell may remain NULL.
- A single-page session becomes a bounce only after the session can be considered complete/expired under the documented rule.

## Events and privacy

- v4 event collection is code-based instrumentation, not a visual event editor.
- Event names and parameter keys require explicit length/character constraints.
- Event parameters must be allowlisted and payload size bounded.
- Sensitive values such as raw IP, cookies, tokens, complete UA and unrestricted Referer data must not be copied into event params.
- High-cardinality arbitrary event params should not automatically become indexes.

## Query and rollup rules

- Preserve existing v3 PV/UV/IP/spider/error/server-duration statistics while adding v4 session/event semantics.
- Avoid full-table scans in normal admin/dashboard request paths.
- Prefer indexed queries and hour/day incremental rollups for common charts when the dataset can grow.
- Small-data direct queries may be used to validate metric semantics, but should not become the unbounded production path.
- Return explicit `unavailable`/`partial` semantics when old data cannot support a v4 metric.

## Testing strategy

Use the smallest test set that proves the current change, with full runtime verification when the behavior depends on Z-Blog or the database.

For non-trivial production changes:

1. Inspect current code/state.
2. Add or identify a failing regression/behavior test when appropriate.
3. Implement the smallest coherent fix/feature.
4. Run targeted unit/static checks.
5. Run real Z-Blog runtime checks for schema, Hooks, collector, endpoints, permissions, runtime queries and major-version work.
6. Inspect logs and database assertions directly rather than trusting UI output alone.
7. Inspect the final diff for unrelated changes and secrets.
8. Push and verify CI when the workflow requires Git delivery.

PHPStan, Semgrep and full suites are risk/release driven rather than mandatory for every small edit.

## Release discipline

- Major/intermediate implementation phases may legitimately finish with `Release Gate: NOT READY`.
- `NOT READY` means the release gate was evaluated, not skipped.
- Do not merge, tag or create a GitHub Release until required local runtime checks and CI evidence satisfy the release plan.
- Only a real tag + GitHub Release + formal release artifact can be reported as released.

## Notion and GitHub roles

- Git/runtime evidence is execution truth.
- Notion stores plans, task state, decisions and writeback evidence.
- When Notion and Git disagree, reconcile Notion to the real Git/runtime state rather than changing Git to fit stale Notion text.
- Every complete-flow task must finish with evidence-backed Notion writeback.

## Agent-method references

External AI Agent/Coding Agent material may be consulted selectively for context management, memory, evaluation, TDD, debugging and multi-agent orchestration. Use it as method guidance only.

A practical routing principle is:

- context problem → consult context-management material;
- long-term memory/state problem → consult memory/state material;
- Coding Agent workflow problem → consult coding-agent material;
- quality/evaluation problem → consult eval/testing material;
- repeated-failure learning problem → consult self-improvement material;
- parallelizable independent work → consult multi-agent material.

Do not load an entire external book into context by default. Load the smallest relevant section, then apply it under this project's real constraints.
