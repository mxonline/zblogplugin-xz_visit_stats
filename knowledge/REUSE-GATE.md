# xz_visit_stats Reuse Gate

## Purpose

Before Codex designs or writes a new reusable subsystem, algorithm or third-party integration, check official Z-Blog resources and maintained open-source implementations first. The goal is to avoid unnecessary greenfield code while keeping the plugin lightweight and compatible with the real Z-Blog runtime.

This gate is additive only. It does not replace the current v4 PRD, T2/T3 evidence, `.codex-tasks/` handoff, test gates, local Z-Blog verification, CI or release gates.

## Progress-preservation rule

- Current `feature/visit-stats-4.0` history and verified T2/T3 evidence remain valid.
- Do not rewind, delete or redo completed work simply because this gate was introduced after v4 work had started.
- Apply the gate prospectively to unresolved T3/T4 implementation choices and all future non-trivial feature work.
- Existing v3/v4 data/schema/runtime compatibility rules remain authoritative.
- Release Gate stays `NOT READY` until the existing v4 acceptance path passes; this gate does not change that state.

## When required

Run the gate before:

- adding a new third-party PHP/JS library;
- implementing generic UA/device/bot detection, CIDR/IP matching, export, queue/job, charting, cache or parsing logic;
- designing a new analytics/session/event subsystem where mature reference implementations exist;
- replacing an existing internal implementation with an external library;
- copying or adapting a substantial algorithm from another project.

Small project-specific glue, already-frozen migrations, tests and bug fixes may proceed without a new gate when no reusable subsystem decision is involved.

## Search order

1. Z-BlogPHP official source, documentation and app ecosystem.
2. Maintained PHP/analytics/security libraries on GitHub.
3. Mature analytics systems as architecture/reference evidence.
4. Custom implementation only after the above are evaluated.

## Evaluation criteria

For each serious candidate record:

- exact capability and reuse boundary;
- license compatibility with plugin distribution;
- recent commit/release activity;
- issue/PR maintenance health;
- CI/test status;
- dependency and security risk;
- PHP 8.3 compatibility and the project-supported PHP/Z-Blog range;
- MySQL 5.7 compatibility where relevant;
- Windows/Nginx/PHP FastCGI runtime fit where relevant;
- effect on collector hot-path performance and plugin package size;
- privacy/data-model implications;
- integration effort and long-term maintenance cost.

Star count is supporting evidence only.

## Decision

End every gate with one explicit result:

- `USE` — use the official/mature solution as the main implementation.
- `REUSE` — use a focused component/library inside xz_visit_stats.
- `FORK` — fork only when long-term ownership/divergence is justified.
- `BUILD` — implement project-specific code because candidates do not fit compatibility, safety, size or maintenance constraints.

Subsystems may produce a combined result such as `BUILD + SELECTIVE REUSE`.

## Project-specific rule

For analytics architecture, large projects such as Matomo may be used as references without importing their heavyweight architecture. Prefer selective reuse of small, well-maintained components for generic problems. Preserve xz_visit_stats' own Z-Blog integration, schema/migration discipline and lightweight admin experience.

## Evidence and writeback

Record the decision in the active task/design evidence and corresponding Notion task/project page. A Reuse Gate decision is input to the existing implementation task, not a new release or development phase.
