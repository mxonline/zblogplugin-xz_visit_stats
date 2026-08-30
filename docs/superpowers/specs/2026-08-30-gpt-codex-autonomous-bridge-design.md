# GPT-Codex Autonomous Development Bridge Design

## Purpose

Build a local autonomous control plane for `xz_visit_stats` so one user instruction can drive the existing Z-Blog plugin development workflow without manually copying Codex output to GPT or GPT instructions back to Codex.

The Bridge is an orchestration layer around the existing project workflow. It must not replace the v4 PRD, Reuse Gate, T4 task, project knowledge, local runtime verification, GitHub CI, release rules, or Notion records.

The first production takeover target is the current `feature/visit-stats-4.0` flow at T4. T2 and T3 remain verified historical evidence and must never be replayed merely because the Bridge is introduced.

## Current authoritative project state

At design time:

- Repository: `mxonline/zblogplugin-xz_visit_stats`
- Development branch: `feature/visit-stats-4.0`
- Target version: `4.0.0`
- Current phase: `T4 — analytics/admin reports, filters and session drill-down`
- T4 state: `IN PROGRESS / CODEX HANDOFF READY`
- T4 execution task: `.codex-tasks/08-v4-t4-analytics-admin.md`
- T4 Release Gate: `NOT READY`
- T4 may not merge, tag, or release.
- A dedicated T5 task does not yet exist and must be added before autonomous release execution is enabled.

Real Git, real local runtime, and exact-SHA CI evidence always override persisted controller state.

## Goals

The Bridge must support this closed loop:

```text
User intent
→ resume verified project state
→ GPT controller decision
→ Codex App Server turn
→ code/test/runtime/CI evidence
→ GPT review
→ repair or next stage
→ repeat
→ final runtime verification
→ Release Gate
→ merge/tag/GitHub Release/formal ZIP
→ Notion writeback
→ COMPLETE
```

The normal path must not require the user to:

- copy Codex output into ChatGPT;
- copy GPT prompts into Codex;
- approve ordinary reversible code/test operations;
- manually decide which failed test to rerun;
- manually relay GitHub CI failures back to the coding agent;
- manually restart from T2/T3 after a Bridge restart.

## Non-goals

The Bridge does not automatically:

- deploy to the production website;
- modify the production database;
- upload to the Z-Blog application center;
- bypass branch protection, release gates, local runtime gates, or security checks;
- fabricate Notion, GitHub, runtime, test, or release evidence;
- auto-resolve credentials that do not exist;
- use the legacy `.codex-state.json` as the v4 controller state.

## Architecture

### 1. Local Bridge Controller

The Bridge runs locally in the authorized Windows development environment and owns the orchestration state machine.

Primary implementation language: PHP CLI, using the already verified PHP 8.3 local runtime. This avoids adding a Python/Node runtime requirement to the control plane.

Responsibilities:

- read Bridge configuration and state;
- reconcile Git/workspace reality before every stage;
- call the OpenAI Responses API for GPT controller decisions;
- launch and supervise `codex app-server` as a long-running child process;
- send/receive JSONL protocol messages over stdio;
- preserve one Codex thread for continuation turns within a live run;
- collect structured evidence from Codex and deterministic local gates;
- classify PASS / REPAIR / ADVANCE / BLOCKED / RELEASE_READY;
- persist atomic checkpoints after every meaningful state transition;
- recover from Bridge, Codex, terminal, network, or machine interruption.

### 2. GPT Controller

The GPT controller is the reasoning/control layer, not the shell executor.

It receives a bounded evidence package containing:

- current phase/task;
- branch and HEAD;
- relevant project-state excerpt;
- Codex result summary;
- changed files;
- actual tests executed and results;
- local-runtime evidence;
- SQL/EXPLAIN evidence when required;
- GitHub CI evidence;
- release-gate evidence;
- previous repair fingerprints.

It must respond using strict JSON Schema Structured Outputs.

Allowed decisions:

```text
CONTINUE_CODEX
REPAIR
RUN_GATE
ADVANCE_PHASE
PREPARE_RELEASE
EXECUTE_RELEASE
BLOCKED
COMPLETE
```

The GPT controller never receives or emits plaintext secrets.

### 3. Codex App Server Client

The Bridge uses `codex app-server` as the primary coding-agent integration.

Protocol expectations follow the current Codex App Server contract:

```text
initialize
initialized
thread/start
turn/start
stream notifications
turn/completed | turn/failed | turn/cancelled
```

Transport is line-delimited JSON over stdin/stdout. Stderr is diagnostics only and must never be parsed as protocol data.

The Bridge should tolerate backward-compatible field-shape differences by extracting logical IDs/outcomes rather than depending on one exact nested response shape.

`codex exec` is retained only as an explicitly configured fallback transport when App Server preflight fails for a known compatibility reason. It is not the normal production transport.

### 4. Deterministic Gate Runner

Deterministic checks should not consume GPT calls when a direct command can answer the question reliably.

Examples:

- `git status`, branch, HEAD and diff checks;
- PHP syntax checks;
- PHPUnit;
- changed-JS syntax checks;
- project scripts such as `scripts/local-verify.ps1`;
- MySQL `EXPLAIN` evidence collection;
- local HTTP/runtime smoke checks;
- `gh`/GitHub exact-SHA CI state;
- release file/version consistency checks;
- formal ZIP whitelist/exclusion verification.

Codex may initiate these as part of its task, but the Bridge must independently reconcile critical evidence before advancing a hard gate.

## State model

Committed repository files describe the controller contract. Mutable runtime state is local and ignored by Git.

Committed:

```text
bridge/config.example.json
bridge/state.example.json
bridge/schemas/controller-decision.schema.json
bridge/schemas/codex-result.schema.json
bridge/prompts/gpt-controller.md
bridge/prompts/codex-executor.md
bridge/phases/v4.0.json
scripts/gpt-codex-bridge.ps1
scripts/gpt-codex-bridge.php
```

Ignored runtime state:

```text
bridge/runtime/state.json
bridge/runtime/request.json
bridge/runtime/result.json
bridge/runtime/runs/
bridge/runtime/snapshots/
.env.bridge
```

### State fields

`bridge/runtime/state.json` must include at least:

```text
schema_version
project
workspace
branch
target_version
run_id
status
current_phase
current_stage
current_task
stage_status
completed_stages
verified_stages
failed_or_blocked_stage
head_sha
last_verified_sha
codex_thread_id
codex_turn_id
last_response_id
repair_round
repair_fingerprints
release_gate
release_state
notion_state
next_action
rollback_target
last_updated
```

Writes must be atomic: write to a temporary file, flush, then replace the previous state.

## Authority and Resume Gate

Authority order:

```text
1. Real local Git/worktree/runtime/DB evidence
2. Real GitHub remote/CI/release evidence
3. bridge/runtime/state.json
4. knowledge/PROJECT-STATE.md
5. current PRD/design/task/handoff files
6. Notion
7. historical chat or legacy controller files
```

At every start and restart the Bridge runs a Resume Gate.

Rules:

- If the recorded HEAD differs from real HEAD, reconcile before execution.
- Never reset, clean, force-checkout, or discard unrelated dirty work automatically.
- When a dirty tree exists, record `git status`, create a local diff/untracked snapshot, and let Codex reconcile it within the authorized task.
- If `knowledge/PROJECT-STATE.md` says T2/T3 verified and real evidence does not contradict it, keep them locked.
- If T4 is incomplete, resume T4 instead of reconstructing earlier phases.
- A completed exact-SHA gate is reused unless later changes invalidate it.
- After restart, a new Codex thread may be created using the persisted evidence/handoff if the prior live App Server process is gone; correctness must not depend on recovering an in-memory thread.

## Project phase routing for v4.0

`bridge/phases/v4.0.json` defines ordered phase progression.

Initial route:

```text
T4_ANALYTICS_ADMIN
  task: .codex-tasks/08-v4-t4-analytics-admin.md
  release_allowed: false

T5_FINAL_VERIFICATION_RELEASE_PREP
  task: .codex-tasks/09-v4-t5-final-release.md
  release_allowed: false until Release Gate PASS

RELEASE
  release_allowed: true only after T5 VERIFIED and Release Gate PASS
```

The Bridge must not invent a release phase simply because T4 finishes. T5 must be represented by a committed task with explicit acceptance criteria derived from the existing PRD, testing rules, and `docs/RELEASE.md`.

## T4 takeover policy

The first real autonomous run must use action `RESUME`, not `REBUILD`.

It must preserve:

- T2 schema audit evidence;
- T3 migration/session/page/event/IP-filter implementation and verification evidence;
- existing v3 historical tables and data;
- current T4 Reuse Gate decision;
- T4 prohibition on merge/tag/release.

The Bridge gives Codex the existing T4 task and instructs it to reconcile the latest real remote/local HEAD before continuing.

T4 completion requires the existing task's full acceptance criteria, including real Windows Z-Blog admin/runtime verification and representative MySQL 5.7 `EXPLAIN` checks. GitHub CI cannot substitute for that runtime gate.

## T5 and release policy

T5 must perform a final release-quality pass and produce observed evidence for:

- target version `4.0.0` consistency;
- complete T4 functionality and v3 compatibility;
- final Windows Z-Blog runtime regression;
- schema/data preservation;
- security/permission/CSRF/privacy checks relevant to v4;
- release-level PHPUnit/PHP/JS/static checks;
- exact-SHA GitHub CI success;
- release documentation consistency;
- formal ZIP contents and exclusions;
- release dry run.

Only after T5 is VERIFIED may Release Gate become `PASS`.

Formal release order remains:

```text
verified development branch
→ release dry run PASS
→ merge target branch
→ verify merged SHA as required
→ tag v4.0.0
→ create formal ZIP
→ create GitHub Release
→ verify Tag + Release + ZIP
→ Notion writeback
```

`RELEASED` may only be recorded after real Tag, GitHub Release and formal ZIP all exist and are verified.

## Repair loop

Failures that are normally repairable must not be surfaced to the user immediately.

Examples:

- test failure;
- PHP/JS syntax failure;
- local HTTP 500;
- SQL error;
- wrong or missing index usage;
- CI failure;
- release-document mismatch;
- packaging failure.

Loop:

```text
collect actual failure
→ normalize failure fingerprint
→ GPT root-cause/recovery decision
→ Codex focused repair turn on same live thread when possible
→ rerun focused failing gate
→ rerun invalidated downstream gates
→ persist state
```

Default repair policy:

- maximum 6 repair rounds per stage;
- if the same failure fingerprint appears twice without meaningful diff/evidence change, force a fresh GPT diagnosis with broader evidence;
- if the same fingerprint appears three times with no progress, mark the stage `BLOCKED` rather than loop forever;
- a new causal failure resets the repeated-fingerprint counter but not the total stage repair count.

## Blocking conditions

The Bridge pauses only for conditions that cannot be safely and correctly resolved autonomously, including:

- missing OpenAI API credential;
- missing/expired local admin session when real authorized UI verification is required and cannot be recreated safely;
- missing GitHub permission needed for the requested release action;
- missing Notion integration credential when Notion writeback is configured as mandatory;
- operation would modify production data/site;
- destructive/irreversible data action not explicitly authorized;
- real schema conflicts with verified evidence and continuing risks data loss;
- release requires an external channel not included in `docs/RELEASE.md`;
- repeated repair loop makes no measurable progress.

A BLOCKED run must save sufficient state to resume after the blocker is removed.

## Approval and safety policy

For the authorized local development workspace, ordinary reversible development operations are auto-approved.

Allowed without per-step user confirmation:

- read/edit files inside the isolated task workspace;
- run project tests and static checks;
- use local test Z-Blog and test database;
- create safety backups/snapshots;
- commit/push the development branch;
- inspect and repair CI;
- create release artifacts after Release Gate PASS.

Never auto-approve:

- production deployment;
- production database mutation;
- deleting unrelated user data/files;
- rewriting Git history or force pushing;
- modifying verified tags;
- bypassing required tests/gates;
- exposing credentials in logs/prompts/state.

## Credentials

Secrets are environment configuration, never repository configuration.

Expected variables may include:

```text
OPENAI_API_KEY
XZ_BRIDGE_GPT_MODEL
NOTION_TOKEN
NOTION_V4_PAGE_ID
GITHUB_TOKEN   # only if local gh auth is unavailable
```

The Bridge must check presence without printing values.

The Codex App Server should reuse the local Codex authentication already configured for the CLI where possible; the Bridge must not copy Codex credentials into its own state.

## OpenAI Responses API contract

Use the Responses API with Structured Outputs (`text.format.type = json_schema`) for controller decisions.

The Bridge stores only response IDs and safe metadata; it does not persist secret-bearing request headers.

The GPT model is configurable. Do not hard-code a ChatGPT product model name that may not exist in the API. Use a documented API model as the default and permit override via `XZ_BRIDGE_GPT_MODEL`.

## Notion integration

The local Bridge cannot assume access to the ChatGPT product's connected Notion session.

For fully autonomous Notion Context/Writeback, use a dedicated Notion API integration configured through local environment variables and target page/database identifiers.

If Notion writeback is mandatory under the project's six-gate flow and credentials are unavailable, the release may not be reported as `FINAL: COMPLETE`; the Bridge must stop with a resumable Notion blocker after preserving the real GitHub release evidence.

## Observability

Each run receives an immutable `run_id`.

Per-run logs must separate:

```text
bridge events
GPT controller decisions
Codex protocol events
Codex stderr diagnostics
deterministic gate results
release evidence
```

Logs must redact likely keys/tokens/cookies and cap raw protocol/log payload size.

The user-facing terminal summary should show only high-level progress, current stage, attempts, and final evidence.

## Completion criteria

Bridge implementation is ready for production takeover only when all of these are verified:

1. configuration/schema validation passes;
2. Resume Gate correctly identifies current v4 T4 state without replaying T2/T3;
3. a fake/mocked App Server proves initialize/thread/turn/event handling;
4. the real local `codex app-server` handshake succeeds;
5. one safe read-only Codex turn returns through the Bridge without manual copying;
6. GPT Structured Output returns a valid controller decision;
7. simulated repair-loop tests pass;
8. restart/resume tests preserve and reconcile state;
9. secrets are not emitted into tracked files/logs;
10. T4 is not modified during Bridge transport/preflight verification;
11. after takeover, T4 executes through the existing task and gates;
12. T5 exists before release automation is enabled;
13. formal release cannot execute without T5 VERIFIED + Release Gate PASS.

## Rollback

The Bridge is an additive outer control layer.

Rollback means:

- stop the Bridge process;
- keep all plugin code, Git commits, tests, task files, runtime evidence and current branch state intact;
- continue the existing manual ChatGPT/Codex workflow from `knowledge/PROJECT-STATE.md` and the current `.codex-tasks/` file.

Disabling or removing the Bridge must never require reverting T2, T3, T4, database migrations, or verified plugin functionality.
