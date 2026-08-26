# xz_visit_stats Knowledge Router

## Purpose

This directory is the project knowledge layer for Codex and ChatGPT. It does not replace the real repository, runtime verification, PRD, Git history, or Notion. Its job is to route an agent to the smallest set of authoritative material needed for the current task and to prevent stale state from becoming execution truth.

Do not preload the whole repository or every historical document. Read only the route relevant to the current task.

## Authority order

When sources disagree, use this order:

1. Real current Git working tree, current branch, current commit, runtime output, database/runtime checks and CI evidence.
2. `knowledge/PROJECT-STATE.md` after reconciling it with item 1.
3. Current version PRD, migration/design documents and current `.codex-tasks/` task.
4. Root `AGENTS.md` hard rules and release gates.
5. Project knowledge documents in this directory.
6. Notion project/task pages for planning, coordination and writeback.
7. Historical docs, previous task notes and external references.

A lower-ranked source must never override observed runtime or current Git state.

## Mandatory startup route

For every non-trivial task:

1. Read `AGENTS.md`.
2. Read `knowledge/PROJECT-STATE.md`.
3. Inspect real Git state: branch, HEAD, worktree changes and current version metadata.
4. Use the routing table below to load only the required domain knowledge and task documents.
5. If the observed Git/runtime state differs from `PROJECT-STATE.md`, trust the observed state and update `PROJECT-STATE.md` before calling the task complete.
6. Before inventing a new fix for a repeated failure, search `knowledge/KNOWN-FAILURES.md`.

## Routing table

### New feature or major-version implementation

Read:

- `knowledge/ZBLOG-DEVELOPMENT-KNOWLEDGE.md`
- current PRD under `docs/`
- current implementation/migration design under `docs/`
- current `.codex-tasks/` handoff
- affected source and tests

For v4.0 T3 specifically, also read:

- `docs/v4.0.0/PRD-v1.0.md`
- `docs/v4.0.0/GAP-ANALYSIS-v1.0.md`
- `docs/v4.0.0/SCHEMA-AUDIT-v1.0.md`
- `docs/v4.0.0/MIGRATION-DESIGN-v1.0.md`
- `.codex-tasks/07-v4-t3-foundation.md`

### Database, schema, migration or upgrade work

Read:

- `knowledge/ZBLOG-DEVELOPMENT-KNOWLEDGE.md`
- `knowledge/KNOWN-FAILURES.md`
- real schema/audit evidence for the target version
- migration design
- existing `inc/upgrade/` implementation and related tests

Hard rule: never infer production schema from repository assumptions when a real audited schema is available.

### Collector, request lifecycle, session, IP, Referer, UA, bot or event work

Read:

- `knowledge/ZBLOG-DEVELOPMENT-KNOWLEDGE.md`
- current collector/session/event PRD sections
- affected Hooks and collector code
- runtime testing rules in `AGENTS.md`

Treat request-path performance and privacy as release-relevant concerns.

### Statistics, metrics, query or dashboard work

Read:

- metric definitions in the current PRD/design
- schema and index design
- existing rollup/query code
- `knowledge/ZBLOG-DEVELOPMENT-KNOWLEDGE.md`

Never reinterpret `vs_DurationMs` as visitor dwell time.

### Bug fix or regression

Read:

- `knowledge/KNOWN-FAILURES.md`
- affected tests and code
- most recent relevant commit/diff
- current runtime evidence if the bug is runtime-dependent

Add a new failure pattern only after the root cause and the validating fix are actually observed.

### CI failure

Read:

- actual failing workflow logs
- relevant local test output
- `knowledge/KNOWN-FAILURES.md`

Do not guess from the workflow name alone. Fix the root cause, rerun the smallest relevant local check, then push and verify CI.

### Release or release preparation

Read:

- `AGENTS.md`
- `docs/RELEASE.md`
- release-document rules
- `knowledge/PROJECT-STATE.md`
- current CI/runtime evidence

Do not merge, tag or release while Release Gate is `NOT READY`.

## Knowledge writeback rule

After a task produces verified new knowledge:

- update `PROJECT-STATE.md` for branch, phase, verified commit, gates and next action;
- update `KNOWN-FAILURES.md` only for a confirmed failure pattern with an observed root cause and validated resolution;
- update `ZBLOG-DEVELOPMENT-KNOWLEDGE.md` only for reusable engineering rules, not one-off task notes;
- write the result back to the corresponding Notion task/project page.

Do not write guesses, planned commands or unexecuted verification results as project knowledge.

## External reference policy

External engineering material, including AI Agent books, articles and framework documentation, may be used as a method reference when it helps with context management, testing, agent orchestration or evaluation. External material is advisory only. It must not override this repository's real runtime constraints, Z-Blog compatibility requirements, current PRD or safety boundaries.
