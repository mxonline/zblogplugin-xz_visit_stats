# Z-Blog Unattended Development Runtime Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the repository's fragmented v1.3/v2.0 state handling with one version-independent, evidence-bound runtime state contract for unattended Z-Blog development while preserving the existing six-gate development flow.

**Architecture:** Add a small Python runtime/state library plus a Windows PowerShell adapter. Runtime files live under `.development/runtime/<execution_id>/`; Notion stays the project/PRD/writeback control plane, real Git/CI/local-runtime results remain factual evidence, and legacy `.codex-state.json` remains compatibility-only. Existing development and release gates are reused rather than duplicated.

**Tech Stack:** Python 3 standard library, PowerShell 5.1-compatible wrapper, Git/GitHub Actions, existing PHP/PHPUnit/Semgrep CI.

**Spec:** `docs/superpowers/specs/2026-09-09-unattended-development-runtime-design.md`

## Global Constraints

- Do not change Z-Blog plugin product behavior, database schema, or v3.0 release semantics.
- Keep the existing six full-development gates exactly: Notion Context, Codex Development, Local Runtime, GitHub CI, Release Gate, Notion Writeback.
- No PASS without resolvable evidence.
- CI PASS must be bound to the exact current head SHA.
- `.codex-state.json`, `.codex/tasks.json`, and `dev-v1.3.ps1` are legacy/non-canonical for new runs.
- Direct Codex workspace execution remains primary; do not resurrect a competing scheduler/runner architecture.
- Use only Python standard library for the runtime helper.

---

### Task 1: Canonical runtime schema and validation

**Files:**
- Create: `scripts/dev_runtime.py`
- Create: `tests/test_dev_runtime.py`

**Interfaces:**
- Produces `new_state()`, `new_evidence_index()`, `validate_bundle()`, `runtime_paths()`, `next_state()`, `new_event()`.
- Later tasks consume these functions for persistence, CLI, and completion evaluation.

- [ ] Write failing tests proving the repository lacks a version-independent runtime with a stable `DEV-*` identity, revisioned state, six gates, evidence binding, legal terminal states, and path-traversal protection.
- [ ] Run the new Python test in CI and confirm RED because `scripts/dev_runtime.py` does not exist.
- [ ] Implement the minimal schema constructors/validators.
- [ ] Re-run and make the runtime schema tests GREEN.

### Task 2: Atomic persistence, append-only events, and cold resume

**Files:**
- Modify: `scripts/dev_runtime.py`
- Modify: `tests/test_dev_runtime.py`

**Interfaces:**
- Produces `write_snapshot(root, state, evidence_index)`, `append_event(root, execution_id, event)`, `load_bundle(root, execution_id)`, `register_evidence(...)`.

- [ ] Add RED tests for atomic write/cold-load, monotonic event sequence, wrong-run event rejection, evidence reference resolution, and preserving existing JSONL rows.
- [ ] Implement only the required file I/O using temporary-file replace for JSON snapshots and append mode for JSONL.
- [ ] Re-run tests to GREEN.

### Task 3: Deterministic resume, Git/CI reconciliation, and six-gate completion

**Files:**
- Modify: `scripts/dev_runtime.py`
- Modify: `tests/test_dev_runtime.py`

**Interfaces:**
- Produces `reconcile_git(state, branch, head_sha, dirty)`, `evaluate_next_action(state, evidence_index)`, `evaluate_completion(state, evidence_index)`.

- [ ] Add RED tests for exact-head CI PASS, stale CI → `RUN_GITHUB_CI`, Git mismatch → `RECONCILE_GIT`, gate order, BLOCKED fail-closed behavior, COMPLETE with Release Gate NOT_READY, and RELEASED requiring tag/release/zip evidence.
- [ ] Implement deterministic evaluator and completion logic.
- [ ] Re-run tests to GREEN.

### Task 4: Version-independent Windows adapter

**Files:**
- Create: `scripts/dev-flow.ps1`
- Create: `tests/test_dev_flow_contract.py`
- Modify: `.github/workflows/codex-workspace-check.yml`

**Interfaces:**
- PowerShell commands: `new`, `status`, `resume`, `transition`, `evidence`, `gate`, `reconcile`, `evaluate`.
- Adapter delegates state logic to `scripts/dev_runtime.py`; it does not own a second state model.

- [ ] Add RED contract tests requiring a non-v1.3 adapter, runtime path, no hardcoded `feature/visit-stats-1.3`, and no manual approval requirement for ordinary transitions.
- [ ] Implement the wrapper and Windows parser check.
- [ ] Re-run contract and Windows workflow checks to GREEN.

### Task 5: Make the runtime the documented execution authority

**Files:**
- Modify: `AGENTS.md`
- Modify: `README-AUTOMATION.md`
- Modify: `.codex/workflow.md`
- Create: `docs/DEVELOPMENT-RUNTIME.md`
- Create: `tests/test_dev_runtime_authority_docs.py`

**Interfaces:**
- Documentation must route new full-development runs through `.development/runtime/<execution_id>/state.json` and preserve the six-gate contract.

- [ ] Add RED docs tests proving current docs still expose v1.3/v2.0 state as if it were the active automation path.
- [ ] Update docs with the authority split and explicit legacy boundaries.
- [ ] Ensure current v4 statement that old `.codex-state.json` is not authoritative remains compatible.
- [ ] Re-run docs tests to GREEN.

### Task 6: CI integration and full regression

**Files:**
- Modify: `.github/workflows/code-check.yml`
- Modify: `.github/workflows/codex-workspace-check.yml`

**Interfaces:**
- Both relevant CI environments execute the runtime contract tests; existing PHP checks remain unchanged.

- [ ] Add the Python runtime tests to CI without weakening PHP Syntax, UI Terminology, PHPUnit, Semgrep, or PowerShell checks.
- [ ] Open a draft PR and observe the full workflows.
- [ ] Fix real failures from logs and rerun until all applicable checks are GREEN.

### Task 7: Generalize the proven contract to the shared Z-Blog standard

**Files (repository `mxonline/xinzhou-code-standard`):**
- Modify: `zblog/Z-Blog插件完整开发流程-v2.0.md`
- Modify: `zblog/Z-Blog插件完整开发流程-v2.0-验收规范.md`
- Modify: `zblog/Z-Blog完整开发流程硬门禁-v1.0.md`
- Add a focused runtime-state contract document if needed rather than duplicating the implementation.

**Interfaces:**
- Shared standards point to the generic contract (`DEV run + state/events/evidence`) without hard-coding the xz_visit_stats implementation path as mandatory for unrelated repositories.

- [ ] Update only after the xz_visit_stats reference implementation is GREEN.
- [ ] Preserve all existing six-gate and four-real-run acceptance requirements.
- [ ] Review the standards diff for accidental workflow weakening.

### Task 8: Merge, main verification, and Notion Source-of-Truth writeback

**Files/Systems:**
- GitHub PR in `mxonline/zblogplugin-xz_visit_stats`
- GitHub standard update in `mxonline/xinzhou-code-standard`
- Notion pages: Z-Blog full-development flow, unattended executor/direct-workspace execution, acceptance spec, and relevant project control page.

- [ ] Run verification-before-completion and code-review checks.
- [ ] Merge only with GREEN PR checks.
- [ ] Verify post-merge `main` checks against the exact merge SHA.
- [ ] Write real PR/merge/CI evidence and canonical-runtime authority to Notion.
- [ ] Refetch Notion pages to verify persistence.
- [ ] Report terminal state only when GitHub and Notion evidence both support it.

## Self-review

- Spec coverage: runtime identity, state, events, evidence, Git/CI reconciliation, six gates, legacy boundaries, interruption resume, failure recovery, and Notion writeback are each assigned to a task.
- No placeholders remain.
- Interface names are consistent across tasks.
- The plan changes execution-state infrastructure only; it does not change product features or database behavior.
