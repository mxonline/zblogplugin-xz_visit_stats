# xz_visit_stats Known Failure Patterns

This file records only failures or state-drift patterns that have been observed in the real project. It is not a list of hypothetical risks.

Before adding an entry, record the symptom, observed cause, safe response and verification evidence. Planned fixes do not count as resolved failures.

## KF-001 — Notion/GitHub state drift and invalid commit reference

### Symptom

Project coordination data referenced a commit that did not exist in the real repository, while the actual branch and Git history had moved elsewhere.

### Observed cause

Planning/writeback state was treated as execution truth without reconciling it against the real Git repository.

### Safe response

- Inspect the real repository and branch first.
- Resolve the actual commit/branch from GitHub or the local Git working tree.
- Correct Notion after Git truth is established.
- Never continue development from an unverified commit identifier.

### Verified project evidence

During v4.0 T2, the previous `1be51a6` reference was identified as invalid and removed from the project status. The real `feature/visit-stats-4.0` branch was then used as the execution baseline.

### Prevention rule

Notion is a coordination/writeback layer, not the highest-authority source for Git state.

## KF-002 — Legacy `.codex-state.json` can be stale during a new major-version flow

### Symptom

The repository's `.codex-state.json` reports completed v1.3-era tasks and `current: 5`, while the real v4.0 project is already in T3.

### Observed cause

The legacy controller state file belongs to an older task chain and was not designed to represent the current v4 phase model.

### Safe response

- Do not use `.codex-state.json` as the authoritative v4 project state.
- Use real Git state plus `knowledge/PROJECT-STATE.md`, current PRD/design and the current `.codex-tasks/` handoff.
- Leave the legacy file intact unless the controller that consumes it is deliberately migrated and regression-tested.

### Prevention rule

A state file is authoritative only for the workflow that owns and updates it. New major-version state must not silently inherit an older controller's state model.

## KF-003 — Local environment path drift

### Symptom

Older workspace guidance lists the typical Z-Blog path as `D:\wwwroot\xinzhao_net`, while the audited v4 Windows environment is `D:\wwwroot\www.xzhao.net`.

### Observed cause

Environment defaults survived after the real local test-site layout changed.

### Safe response

- Detect the real Z-Blog root, plugin root and PHP CLI before runtime work.
- Treat paths in documentation as defaults or historical context unless the current task has re-verified them.
- Never deploy by blindly copying to a path from an older document.

### Verified v4 environment

- Z-Blog root: `D:\wwwroot\www.xzhao.net`
- Plugin root: `D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats`
- PHP CLI: `D:\BtSoft\php\83\php.exe`
- Local site: `http://127.0.0.1`
- PHP: `8.3.8`
- Z-BlogPHP: `173540`
- MySQL: `5.7.38-log`

### Prevention rule

Runtime paths are discovered facts, not permanent constants.

## KF-004 — `vs_DurationMs` semantic misuse risk

### Symptom

A server processing-duration field can be mistaken for page dwell/session duration when designing visitor behavior metrics.

### Observed cause

The existing v3 name contains “Duration”, while v4 introduces true page lifecycle/dwell semantics.

### Safe response

- Keep `vs_DurationMs` as server processing time only.
- Derive visitor dwell from validated client lifecycle/Beacon data.
- Allow dwell to remain unavailable/NULL when trustworthy lifecycle evidence does not exist.
- Never backfill historical v3 bounce/dwell with server processing time.

### Prevention rule

Metric names do not define semantics. The audited data contract and PRD do.

## KF-005 — Schema assumptions are unsafe before real-environment audit

### Symptom

Repository-level expectations can differ from the real installed plugin database, including legacy/historical tables.

### Observed cause

The installed Z-Blog environment contains accumulated schema/history that cannot be reconstructed safely from current source files alone.

### Safe response

- For migration work, use the real read-only schema audit as the migration input.
- Preserve existing historical tables unless an explicit, reviewed migration says otherwise.
- Stop on same-name structural conflicts; do not automatically DROP/recreate.

### Verified v4 audit baseline

T2 audited 9 plugin-related tables and a 288-row main log using 29 read-only queries in Safe Mode. The audit performed no DDL/DML and exported no visitor-level or secret material.

### Prevention rule

Real audited schema outranks repository assumptions for migration safety.
