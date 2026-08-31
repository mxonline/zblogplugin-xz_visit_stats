# GPT-Codex Autonomous Bridge v2.2 — Quota Checkpoint & Auto-Resume Plan

> Extends the approved v2.2 implementation. This task must be completed before default autonomous activation.

**Goal:** Make zero-quota conditions restart-safe and resumable without user Codex UI interaction or manual GPT↔Codex relay.

**Spec:** `docs/superpowers/specs/2026-08-31-gpt-codex-autonomous-bridge-v2.2-quota-resume-amendment.md`

## Task 30: Implement quota checkpoint and auto-resume

**Files:**
- Create: `bridge/schemas/checkpoint.schema.json`
- Create: `bridge/lib/Bridge.Quota.psm1`
- Create: `tests/bridge/Test-Quota.ps1`
- Modify: `bridge/lib/Bridge.State.psm1`
- Modify: `bridge/lib/Bridge.Orchestrator.psm1`
- Modify: `bridge/lib/Bridge.Gpt.psm1`
- Modify: `bridge/lib/Bridge.AppServer.psm1`
- Modify: `scripts/gpt-codex-bridge.ps1`
- Modify: `scripts/install-bridge-task.ps1`
- Modify: `tests/bridge/Run-All.ps1`
- Modify: `.github/workflows/bridge-check.yml`

### Required interfaces

Implement:

```text
Get-QuotaClassification
New-QuotaCheckpoint
Save-QuotaCheckpointAtomic
Get-QuotaCheckpoint
Enter-QuotaWait
Test-QuotaAvailable
Resume-FromQuotaCheckpoint
```

Add legal lifecycle transitions:

```text
GPT_REVIEW/CODEX_RUNNING/TASK_DISPATCH/RETRY_INFRA
→ QUOTA_CHECKPOINT
→ WAITING_QUOTA
→ QUOTA_RECHECK
→ RESUME_GATE
→ AUTO_RESUME
```

No legal transition from `WAITING_QUOTA` directly to `PLUGIN_RELEASED`.

### Step 1 — failing tests

Create tests proving:

- explicit GPT insufficient/zero quota → `QUOTA_EXHAUSTED_GPT`;
- explicit Codex zero quota → `QUOTA_EXHAUSTED_CODEX`;
- ordinary transient rate limit with retry metadata → `QUOTA_TEMPORARY_RATE_LIMIT` or existing infra retry according to policy;
- checkpoint includes request/stage/branch/HEAD/thread/evidence/next action but no secrets;
- dirty paths remain untouched;
- zero quota never maps to `FAILED`;
- zero quota never returns a Codex UI/manual relay instruction.

Run and require FAIL before implementation.

### Step 2 — checkpoint schema

Checkpoint schema requires:

```text
schema_version
request_id
project
business_goal
lane
current_stage
stage_status
branch
head_sha
candidate_id
worktree_path
dirty_paths
last_verified_gate
verified_gates
pending_gates
thread_id
turn_id
controller_previous_response_id
last_codex_result_id
pending_gpt_decision
pending_codex_instruction
code_repair_round
infra_retry_count
evidence_ids
rollback_target
quota_provider
quota_classification
quota_reset_at
next_action
checkpoint_created_at
```

Sensitive fields are forbidden.

### Step 3 — atomic checkpoint

Use the existing atomic JSON helper. Checkpoint first, then transition to `WAITING_QUOTA`. A process crash between quota detection and checkpoint completion must leave the previous valid state intact.

### Step 4 — provider classification

Prefer structured error codes/status/metadata. Do not rely on fuzzy text when structured evidence is available.

Distinguish:

```text
RETRYABLE_INFRA
QUOTA_EXHAUSTED_GPT
QUOTA_EXHAUSTED_CODEX
QUOTA_EXHAUSTED_PROVIDER
```

Persist reset/retry time when available.

### Step 5 — GPT-side suspension

If GPT quota is exhausted after Codex result collection, preserve the Codex result/evidence and set:

```text
next_action = GPT_REVIEW
```

Do not dispatch another mutating Codex turn until GPT review is available.

### Step 6 — Codex-side suspension

If Codex quota is exhausted after GPT already generated the next instruction, persist:

```text
pending_gpt_decision
pending_codex_instruction
next_action = TASK_DISPATCH
```

When quota returns, reuse the same thread when safe; otherwise create a new thread from sanitized handoff.

### Step 7 — no destructive workspace handling

Tests must assert no invocation of:

```text
git reset
git clean
stash drop/clear
force push
```

Quota handling leaves task-owned worktree content untouched and records dirty paths.

### Step 8 — automatic recheck/resume

When `quota_reset_at` exists, schedule the next recheck from it. Otherwise use bounded increasing backoff. The installed Scheduled Task/service path must call `resume` at startup/logon and recognize `WAITING_QUOTA`.

Recovery path:

```text
WAITING_QUOTA
→ QUOTA_RECHECK PASS
→ RESUME_GATE
→ real Git/runtime/CI/evidence reconciliation
→ AUTO_RESUME
→ saved next_action
```

### Step 9 — crash/restart test

Synthetic integration test:

```text
Codex turn result
→ GPT quota zero
→ checkpoint
→ process termination
→ restart
→ quota available
→ GPT review
→ Bridge dispatches next Codex turn
```

A second fixture tests Codex quota zero with a saved `pending_codex_instruction`.

No user input may occur between suspension and resumed second turn.

### Step 10 — CI visibility

Add `Test quota checkpoint/resume` as an independent Bridge CI step so failures are identifiable without full log parsing.

### Step 11 — activation requirement

v2.2 default activation must require Quota Checkpoint acceptance evidence in addition to existing Zero-Touch acceptance gates.

The final activation report must state:

```text
QUOTA_CHECKPOINT: PASS
QUOTA_AUTO_RESUME: PASS
CODEX_UI_DEPENDENCY: NONE
```

### Step 12 — commit and exact-SHA CI

Run all Bridge tests, secret scan and exact-SHA Bridge CI. Do not mark quota recovery READY without evidence.
