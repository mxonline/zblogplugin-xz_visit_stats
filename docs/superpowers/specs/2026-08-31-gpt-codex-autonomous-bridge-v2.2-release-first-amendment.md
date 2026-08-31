# GPT-Codex Autonomous Bridge v2.2 — Release-First Continuous Loop Amendment

Date: 2026-08-31
Parent specs:
- `2026-08-30-gpt-codex-autonomous-bridge-design.md`
- `2026-08-30-gpt-codex-autonomous-bridge-v2.1-amendment.md`
- `2026-08-30-gpt-codex-autonomous-bridge-zero-touch-amendment.md`

## Amendment scope

This amendment makes continuous GPT↔Codex execution a state-machine invariant rather than a prompt convention.

The bridge must never treat a Codex turn terminal event as the user task terminal event. A Codex turn terminal event transitions to `RESULT_COLLECT`, then `GPT_REVIEW`, then automatic redispatch according to the GPT decision.

## New invariants

```text
SUCCESS_TERMINAL_STATE = PLUGIN_RELEASED
CODEX_TURN_TERMINAL_IS_INTERMEDIATE = TRUE
CODEX_UI_DEPENDENCY = FORBIDDEN
MANUAL_CONTINUE = FORBIDDEN
MANUAL_RESULT_RELAY = FORBIDDEN
```

Legal terminal states for a whole run:

- `PLUGIN_RELEASED`
- `BLOCKED`
- `FAILED_SAFE` only when the controller itself cannot safely continue and all automatic recovery paths are exhausted.

`CODEX_TURN_COMPLETED`, `TEST_PASS`, `CI_PASS`, `T4_COMPLETE`, `RELEASE_READY` are never whole-run terminal states.

## Required control loop

```text
Dispatch Codex turn
→ monitor App Server events
→ terminal turn event
→ collect stdout/stderr/tool/evidence
→ normalize/redact result
→ persist evidence
→ invoke GPT controller
→ validate GPT decision schema
→ transition state
→ auto-dispatch next Codex turn or gate
→ repeat
```

No user-facing pause is permitted between these transitions.

## GPT decision schema extension

Every controller result must contain:

```json
{
  "decision": "NEXT_STAGE|REPAIR|REVERIFY|RETRY_INFRA|RELEASE_READY|BLOCKED",
  "reason": "...",
  "next_stage": "...",
  "codex_instruction": "...",
  "required_gates": [],
  "evidence_required": [],
  "safety_class": "ROUTINE|ELEVATED|BLOCKING",
  "reuse_same_thread": true
}
```

A decision that does not validate must be retried/repaired by the controller adapter; it must not be passed to the user for interpretation.

## Executor handoff violation detector

Bridge must scan normalized Codex output for responsibility-transfer patterns such as:

- asking the user to run commands;
- asking the user to open Codex UI;
- asking for manual approval for an already authorized reversible operation;
- asking the user to copy results to GPT;
- asking the user to provide “next step” merely to continue routine work.

Detection result: `EXECUTOR_HANDOFF_VIOLATION`.

Response:

1. do not surface the handoff as the normal next action;
2. send the violation + current evidence to GPT;
3. GPT rewrites the instruction as a self-contained executable Codex turn;
4. Bridge redispatches automatically.

## Approval Proxy

Bridge.Approval must consume App Server approval requests.

Policy layers:

1. Project hard safety (`AGENTS.md`).
2. Requirement Gate authorized scope.
3. Lane policy.
4. Expected Diff policy.
5. Current gate permissions.

Routine reversible operations that are already authorized are auto-approved programmatically. Unsafe/out-of-scope requests are auto-denied and classified `BLOCKED`; they are never delegated to the user through Codex UI.

## Watchdog

Add a supervisor independent from the turn dispatcher.

Minimum timers/state:

- `last_event_at`
- `last_progress_at`
- `turn_started_at`
- `stage_timeout_seconds`
- `heartbeat_seconds`
- `infra_retry_count`
- `process_restart_count`

Recovery order:

1. inspect App Server process;
2. inspect buffered stderr/events;
3. reconnect existing thread when supported;
4. restart App Server and resume thread/state;
5. fallback to programmatic `codex exec` with a full machine-generated handoff;
6. if no programmatic executor transport is available, persist `BLOCKED_EXECUTOR_TRANSPORT`.

Never instruct the user to recover via Codex UI.

## Thread continuity policy

Default: same Codex thread for adjacent repair/next-stage turns to preserve execution context.

Create a new thread automatically when:

- repeated repair loops show likely context contamination;
- protocol/session recovery cannot resume old thread;
- context budget risk is detected;
- GPT explicitly sets `reuse_same_thread=false`.

New thread handoff must contain only safe, necessary state:

- business goal and accepted requirement envelope;
- branch and exact HEAD;
- current stage/lane;
- completed/verified gates;
- Expected Diff;
- failure evidence IDs;
- attempted repairs and outcomes;
- explicit forbidden/repeat-protected stages;
- next executable instruction.

## Release-First gates

New mandatory development admission sequence:

```text
RESUME_GATE
→ REQUIREMENT_GATE
→ REUSE_GATE (if applicable)
→ CHANGE_IMPACT_GATE
→ BASELINE_INHERITANCE_GATE
→ EXPECTED_DIFF_GATE
→ LANE_ROUTE
→ DISPATCH
```

New candidate/release chain:

```text
CANDIDATE_COMMIT
→ EXPECTED_DIFF_VERIFY
→ LOCAL_RUNTIME_SAFETY_GATE
→ LOCAL_RUNTIME
→ DB/SQL/EXPLAIN
→ EXACT_SHA_CI
→ CANDIDATE_ZIP/MANIFEST/SHA256
→ FINAL_RUNTIME
→ RELEASE_GATE
→ ROLLBACK_GATE
→ VERSION_CONSISTENCY_GATE
→ TAG
→ GITHUB_RELEASE
→ RELEASE_ARTIFACT_SHA256_VERIFY
→ NOTION/PROJECT_STATE
→ PLUGIN_RELEASED
```

## Evidence requirements

Every Codex turn and GPT decision is correlated by:

```text
run_id
request_id
thread_id
turn_id
stage
candidate_id
head_sha
```

Evidence Ledger must make it possible to reconstruct the full sequence without chat history.

## Activation test

Before v2.2 policy activation, run an end-to-end acceptance scenario where:

- user submits exactly one requirement;
- at least two Codex turns execute automatically;
- first terminal Codex event triggers GPT review automatically;
- GPT decision triggers a second Codex turn automatically;
- a simulated executor handoff violation is detected and self-repaired;
- App Server is intentionally interrupted and Watchdog restores programmatic execution;
- no Codex UI interaction occurs;
- flow proceeds through real local runtime and exact-SHA CI;
- final legitimate release is created and verified;
- terminal state is `PLUGIN_RELEASED`.
