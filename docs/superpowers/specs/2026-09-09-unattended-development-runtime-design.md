# Z-Blog Unattended Development Runtime Design

## Goal

Turn the existing partial Z-Blog unattended-development mechanisms into one version-independent, machine-readable execution contract that can recover a `DEV-YYYYMMDD-NNN` run after chat, Codex, PowerShell, or Windows interruption without treating chat memory, legacy `.codex-state.json`, or stale Notion status text as execution truth.

## Existing baseline

The repository already contains three useful but inconsistent layers:

1. `AGENTS.md` defines the current direct-Codex workspace architecture and the mandatory six-gate full-development contract.
2. `dev-v1.3.ps1` + `.codex-state.json` provide a legacy v1.3 local queue and manual `approve` checkpoint.
3. `.codex/tasks.json` / `.codex/workflow.md` retain older v2.0 planning data.

The current v4 project documentation already states that `.codex-state.json` is not a v4 authority. Therefore this design must not promote that file into a new global state store.

## Authority split

### Canonical machine execution state

Git repository runtime files are the canonical accepted execution state for a development run:

```text
.development/runtime/<execution_id>/
  state.json
  events.jsonl
  evidence/index.json
```

`execution_id` is the existing Z-Blog run identifier, for example `DEV-20260909-001`. No second run identity is introduced.

### Real-world facts

Runtime state cannot manufacture facts. The following remain factual evidence and outrank state strings when they conflict:

- real Git branch / commit / working-tree state;
- current PR and exact-head GitHub Actions results;
- actual local Z-Blog / PHP / database / HTTP / log verification;
- real Tag / GitHub Release / ZIP artifacts;
- actual Notion fetch/writeback results.

A conflict between canonical state and real evidence is fail-closed and requires reconciliation.

### Notion

Notion remains the human/project control plane: project page, PRD, requirements, decisions, progress summary, release records, and final writeback. It is not the sole machine execution-position authority.

### Legacy files

- `.codex-state.json`: v1.3 compatibility/history only.
- `.codex/tasks.json`: legacy planning input only.
- `dev-v1.3.ps1`: legacy v1.3 compatibility entry only.

New unattended runs must use the version-independent runtime contract.

## Canonical state schema

`state.json` schema version 1 contains at least:

- `schema_version=1`
- `execution_id`
- `project`
- `repository`
- `current_version`
- `target_version`
- `task_type`
- `task_summary`
- `status`
- `current_phase`
- `next_action`
- `state_revision >= 1`
- `branch`
- `base_branch`
- `head_sha`
- `pr_number` / `pr_url` nullable
- `ci` summary bound to an exact SHA
- `gates`
- `blocked` nullable
- `notion` projection refs
- `created_at`
- `updated_at`

Machine statuses are normalized as:

`IDENTIFIED / CONTEXT_RESTORED / ANALYZING / PRD_UPDATED / DEVELOPING / TESTING / FIXING / CI_VERIFYING / RELEASE_PREPARING / COMPLETED / BLOCKED / CANCELED`

These map to the existing Chinese workflow states; they do not create a second business workflow.

## Six-gate state

`gates` has exactly the six existing full-development gates:

- `notion_context`
- `codex_development`
- `local_runtime`
- `github_ci`
- `release_gate`
- `notion_writeback`

Allowed gate states:

- normal gates: `PENDING / PASS / BLOCKED`
- `local_runtime` and `github_ci`: additionally `NOT_REQUIRED`
- `release_gate`: additionally `NOT_READY`

Every non-pending gate result must contain one or more `evidence:<id>` references. `PASS` without evidence is invalid.

## Evidence index

`evidence/index.json` is bound to the same `execution_id` and stores lightweight evidence identities, not copied external artifacts.

Each evidence item includes:

- `id`
- `type`
- `result`
- `ref`
- `observed_at`
- optional `sha`, `run_id`, `details`

Examples include Notion page/readback refs, local-runtime logs, commit SHAs, PR refs, exact-head CI runs, release refs, and test outputs.

Any `evidence:<id>` referenced by `state.json` must resolve in the same run's evidence index.

## Event ledger

`events.jsonl` is append-only. Each event includes:

- `seq` monotonic from 1
- `execution_id`
- `state_revision`
- `event`
- `phase`
- `time`
- `data`

Existing rows are never rewritten by the runtime helper.

## Resume contract

Cold resume performs:

1. load `state.json`, `evidence/index.json`, `events.jsonl`;
2. validate run identity, revision, evidence references, gate evidence, event sequence, and legal statuses;
3. reconcile real Git facts supplied by the controller (`branch`, `head_sha`, dirty state);
4. validate any recorded CI PASS is tied to the exact current head SHA;
5. determine one deterministic `next_action`.

If the runtime bundle is missing for a new task, action is `BOOTSTRAP_RUNTIME`.

If identity/evidence/revision/gate data conflict, action is `VERIFY_RUNTIME_STATE`.

If stored Git/CI facts are stale, action is `RECONCILE_GIT` or `VERIFY_GITHUB_CI`, not a guessed continuation.

## Deterministic next actions

The runtime evaluator may return:

- `RUN_NOTION_CONTEXT`
- `RUN_CODEX_DEVELOPMENT`
- `RUN_LOCAL_RUNTIME`
- `RUN_GITHUB_CI`
- `RUN_RELEASE_GATE`
- `RUN_NOTION_WRITEBACK`
- `RECONCILE_GIT`
- `VERIFY_RUNTIME_STATE`
- `COMPLETE`
- `BLOCKED`

This is an execution router only. It does not replace PRD/task-specific development logic.

## Completion contract

`FINAL=COMPLETE` is allowed only when:

- notion_context = PASS;
- codex_development = PASS;
- local_runtime = PASS or valid NOT_REQUIRED;
- github_ci = PASS or valid NOT_REQUIRED;
- release_gate = PASS or NOT_READY;
- notion_writeback = PASS;
- no gate is BLOCKED;
- `blocked` is null;
- all evidence refs validate;
- CI PASS, when present, matches current `head_sha`.

`RELEASE=RELEASED` is separate and requires real Tag + GitHub Release + formal ZIP evidence. A development task may be COMPLETE while release is NOT RELEASED when the Release Gate is legitimately NOT_READY.

## Persistence and interruption behavior

Every accepted transition writes `state.json` and `evidence/index.json` atomically and appends an event. The helper itself does not fabricate Git commits; normal Codex/Git delivery commits runtime changes together with the accepted development checkpoint. This provides immediate local restart from the working tree and cross-session/cross-machine recovery once the checkpoint enters Git history.

## Compatibility

- No Z-Blog product behavior, database schema, collector hot path, or v3.0 release artifact is changed by this work.
- No new external database/service is introduced.
- Existing six-gate semantics are preserved.
- The old v1.3 automation remains callable for historical v1.3 work but is explicitly non-canonical for new runs.
- Direct Codex workspace execution remains the preferred executor; this work adds deterministic state/recovery, not a third competing runner architecture.
