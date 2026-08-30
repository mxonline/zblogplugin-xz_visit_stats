# GPT-Codex Autonomous Development Bridge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build and activate a restartable local GPT↔Codex autonomous development bridge so future `xz_visit_stats` development starts from a user requirement and continues automatically through Codex execution, tests, local Z-Blog verification, exact-SHA GitHub CI, repair loops, release gates, GitHub Release and Notion writeback without manual copy/paste.

**Architecture:** A Windows PowerShell control plane launches Codex App Server as a long-lived JSONL/JSON-RPC-lite child process, calls the OpenAI Responses API for controller decisions, persists an atomic bridge state machine, and invokes project gates through focused adapters. The bridge is additive: current v4/T4 task files, plugin runtime code, local verification and release rules remain authoritative. A direct Notion REST adapter is included because a local Responses API controller cannot call ChatGPT UI connectors autonomously.

**Tech Stack:** PowerShell 5.1+/7 compatible scripts, Codex App Server (`codex app-server`), OpenAI Responses API, JSON Schema, Git/GitHub CLI or GitHub REST, Notion REST API, existing PHP/PHPUnit/Z-Blog/MySQL verification tooling.

**Spec:** `docs/superpowers/specs/2026-08-30-gpt-codex-autonomous-bridge-design.md` plus `docs/superpowers/specs/2026-08-30-gpt-codex-autonomous-bridge-notion-amendment.md`

## Global Constraints

- Preserve verified v4 T2/T3 state; do not rewind or repeat them.
- Current production-development resume target is T4 via `.codex-tasks/08-v4-t4-analytics-admin.md`.
- Never make legacy `.codex-state.json` authoritative for v4.
- Never reset/clean/discard unrelated uncommitted work and never force-push.
- Never modify `zb_system`, unrelated plugins, production data or production site files.
- `OPENAI_API_KEY`, `NOTION_TOKEN`, GitHub credentials, cookies and private visitor data must never be committed or persisted in run artifacts.
- Local runtime verification remains mandatory when `AGENTS.md` requires it; CI cannot replace it.
- Exact-SHA GitHub CI evidence is mandatory for candidates that require CI.
- `AUTONOMOUS_EXECUTION=REQUIRED` is activated only after live Bridge preflight and takeover acceptance pass.
- The first live takeover must not merge/tag/release T4 prematurely; it follows the existing downstream release plan.
- Routine PHP/test/SQL/admin/CI failures are repairable and must not become user-confirmation prompts.
- The bridge stops only on defined `BLOCKED` safety/credential conditions.

---

## File Structure

Create or modify these focused units:

- `bridge/config.example.json` — committed non-secret defaults and model/gate policy.
- `bridge/request.json` — sanitized current request envelope.
- `bridge/state.json` — sanitized crash-recovery state; atomically replaced.
- `bridge/result.json` — sanitized latest result envelope.
- `bridge/schemas/request.schema.json` — GPT→Bridge/Codex request contract.
- `bridge/schemas/state.schema.json` — state-machine contract.
- `bridge/schemas/result.schema.json` — execution/result evidence contract.
- `bridge/prompts/gpt-controller.md` — controller policy and decision contract.
- `bridge/prompts/codex-executor.md` — Codex execution and evidence contract.
- `bridge/lib/Bridge.Common.psm1` — JSON, redaction, process and atomic-file helpers.
- `bridge/lib/Bridge.State.psm1` — legal transitions and resume logic.
- `bridge/lib/Bridge.AppServer.psm1` — App Server process, handshake, thread/turn/event handling.
- `bridge/lib/Bridge.Gpt.psm1` — Responses API structured decision adapter.
- `bridge/lib/Bridge.Notion.psm1` — direct Notion read/write/read-back adapter.
- `bridge/lib/Bridge.Gates.psm1` — local verification, SQL, GitHub CI and release evidence adapters.
- `bridge/lib/Bridge.Orchestrator.psm1` — main autonomous loop and repair policy.
- `scripts/gpt-codex-bridge.ps1` — CLI/service entrypoint.
- `scripts/bridge-preflight.ps1` — non-mutating environment and credential preflight.
- `scripts/install-bridge-task.ps1` — optional Windows Scheduled Task bootstrap for restart/resume.
- `tests/bridge/Assert.ps1` — dependency-free PowerShell assertions.
- `tests/bridge/Test-Schemas.ps1` — contract tests.
- `tests/bridge/Test-State.ps1` — transition/resume tests.
- `tests/bridge/Test-Redaction.ps1` — secret scrubbing tests.
- `tests/bridge/Test-AppServer.ps1` — fake JSONL App Server protocol tests.
- `tests/bridge/Test-Gpt.ps1` — mocked Responses API tests.
- `tests/bridge/Test-Notion.ps1` — mocked Notion adapter tests.
- `tests/bridge/Test-Orchestrator.ps1` — fail→repair→pass and crash-resume tests.
- `.github/workflows/bridge-check.yml` — bridge static/contract tests only; no local-runtime assumptions.
- `.gitignore` — ignore `bridge/config.local.json`, runtime logs, transient run data and token-bearing files.
- `AGENTS.md` — add Bridge transition/mandatory autonomous-execution policy.
- `README-AUTOMATION.md` — deprecate v1.3 manual approve/next controller as historical fallback.
- `knowledge/PROJECT-STATE.md` — record Bridge bootstrap/READY/activation evidence only after verified milestones.

---

### Task 1: Freeze Bridge contracts and secret boundaries

**Files:**
- Create: `bridge/config.example.json`
- Create: `bridge/schemas/request.schema.json`
- Create: `bridge/schemas/state.schema.json`
- Create: `bridge/schemas/result.schema.json`
- Create: `bridge/request.json`
- Create: `bridge/state.json`
- Create: `bridge/result.json`
- Create: `tests/bridge/Assert.ps1`
- Create: `tests/bridge/Test-Schemas.ps1`
- Modify: `.gitignore`

**Interfaces:**
- Produces: schema version `1.0`, lifecycle states, result states and non-secret config consumed by every later task.
- Consumes: accepted design and Notion amendment only.

- [ ] **Step 1: Write schema tests that fail because the contracts do not exist**

```powershell
. "$PSScriptRoot/Assert.ps1"
$root = Resolve-Path "$PSScriptRoot/../.."
Assert-PathExists "$root/bridge/schemas/request.schema.json"
Assert-PathExists "$root/bridge/schemas/state.schema.json"
Assert-PathExists "$root/bridge/schemas/result.schema.json"

$request = Get-Content "$root/bridge/request.json" -Raw | ConvertFrom-Json
Assert-Equal '1.0' $request.schema_version 'request schema version'
Assert-Equal 'PRESERVE_VERIFIED' $request.resume_policy 'resume policy'
Assert-Contains $request.forbidden_actions 'REWRITE_T2_T3' 'T2/T3 lock'
Assert-Contains $request.forbidden_actions 'PREMATURE_RELEASE' 'release lock'
```

- [ ] **Step 2: Run the test and verify failure**

Run: `powershell -NoProfile -ExecutionPolicy Bypass -File tests/bridge/Test-Schemas.ps1`

Expected: non-zero exit because `bridge/schemas/*.json` and request/state/result files do not exist.

- [ ] **Step 3: Create minimal committed config and JSON contracts**

`bridge/config.example.json` must include only non-secret values:

```json
{
  "schema_version": "1.0",
  "project": "xz_visit_stats",
  "repository": "mxonline/zblogplugin-xz_visit_stats",
  "development_branch": "feature/visit-stats-4.0",
  "controller_model": "env:OPENAI_CONTROLLER_MODEL",
  "codex_model": "env:CODEX_MODEL",
  "max_repair_rounds": 3,
  "infra_retry_limit": 5,
  "poll_seconds": 30,
  "local_zblog_root": "D:\\wwwroot\\www.xzhao.net",
  "local_plugin_root": "D:\\wwwroot\\www.xzhao.net\\zb_users\\plugin\\xz_visit_stats",
  "php_cli": "D:\\BtSoft\\php\\83\\php.exe",
  "local_site": "http://127.0.0.1"
}
```

Initial `bridge/state.json`:

```json
{
  "schema_version": "1.0",
  "status": "IDLE",
  "stage": "BRIDGE_BOOTSTRAP",
  "request_id": null,
  "thread_id": null,
  "head_sha": null,
  "repair_round": 0,
  "last_verified_gate": null,
  "next_action": "PREFLIGHT",
  "updated_at": null
}
```

- [ ] **Step 4: Add ignore rules**

Add exact patterns:

```gitignore
bridge/config.local.json
bridge/runs/*
!bridge/runs/.gitkeep
bridge/*.log
bridge/*.tmp
.env
.env.*
```

- [ ] **Step 5: Run contract tests and JSON parse checks**

Run:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tests/bridge/Test-Schemas.ps1
Get-ChildItem bridge -Recurse -Filter *.json | ForEach-Object { Get-Content $_.FullName -Raw | ConvertFrom-Json | Out-Null }
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add bridge .gitignore tests/bridge
git commit -m "feat(bridge): add control-plane contracts"
```

---

### Task 2: Add atomic state persistence and redaction

**Files:**
- Create: `bridge/lib/Bridge.Common.psm1`
- Create: `bridge/lib/Bridge.State.psm1`
- Create: `tests/bridge/Test-State.ps1`
- Create: `tests/bridge/Test-Redaction.ps1`

**Interfaces:**
- Produces: `Read-BridgeJson`, `Write-BridgeJsonAtomic`, `Protect-BridgeEvidence`, `Get-BridgeState`, `Set-BridgeState`, `Test-BridgeTransition`.
- Consumes: lifecycle/result constants from Task 1.

- [ ] **Step 1: Write failing transition and atomic-write tests**

```powershell
Import-Module "$PSScriptRoot/../../bridge/lib/Bridge.State.psm1" -Force
Assert-True (Test-BridgeTransition -From 'IDLE' -To 'CONTEXT_SYNC') 'IDLE -> CONTEXT_SYNC'
Assert-False (Test-BridgeTransition -From 'IDLE' -To 'RELEASE') 'IDLE cannot jump to RELEASE'
Assert-True (Test-BridgeTransition -From 'GPT_REVIEW' -To 'REPAIR') 'review -> repair'
Assert-True (Test-BridgeTransition -From 'GPT_REVIEW' -To 'UNIT_TEST') 'review -> next gate'
```

Redaction test:

```powershell
$raw = 'Authorization: Bearer sk-proj-SECRET NOTION_TOKEN=secret-value cookie=session123'
$safe = Protect-BridgeEvidence $raw
Assert-NotContains $safe 'sk-proj-SECRET' 'OpenAI key redacted'
Assert-NotContains $safe 'secret-value' 'Notion token redacted'
Assert-NotContains $safe 'session123' 'cookie redacted'
```

- [ ] **Step 2: Run tests and verify failure because modules are absent**

Run:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tests/bridge/Test-State.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File tests/bridge/Test-Redaction.ps1
```

Expected: FAIL.

- [ ] **Step 3: Implement atomic writes and legal transitions**

`Write-BridgeJsonAtomic` must write UTF-8 to `<path>.tmp`, flush/close, then replace/move into the target path without exposing a half-written state file. `Set-BridgeState` must reject illegal transitions and stamp `updated_at` in UTC ISO-8601.

- [ ] **Step 4: Implement evidence redaction**

At minimum redact:

```text
sk-[A-Za-z0-9_-]+
secret_[A-Za-z0-9_-]+
Bearer\s+[A-Za-z0-9._~-]+
NOTION_TOKEN\s*=\s*[^\s]+\nCookie:\s*[^\r\n]+
Set-Cookie:\s*[^\r\n]+
```

Do not persist raw HTTP authorization headers.

- [ ] **Step 5: Run both tests and verify PASS**

- [ ] **Step 6: Commit**

```bash
git add bridge/lib tests/bridge
git commit -m "feat(bridge): add crash-safe state engine"
```

---

### Task 3: Implement non-mutating preflight and Resume Gate

**Files:**
- Create: `scripts/bridge-preflight.ps1`
- Create: `tests/bridge/Test-Preflight.ps1`
- Modify: `bridge/lib/Bridge.State.psm1`

**Interfaces:**
- Produces: `Invoke-BridgePreflight` result object with `status`, `repo_root`, `branch`, `head_sha`, `dirty_paths`, `codex_available`, `openai_auth`, `notion_auth`, `github_auth`, `zblog_runtime`, `resume_stage`.
- Consumes: config, current Git state, `AGENTS.md`, `knowledge/PROJECT-STATE.md` and `.codex-tasks/08-v4-t4-analytics-admin.md`.

- [ ] **Step 1: Write failing preflight fixture tests**

Test that unrelated dirty files cause `BLOCKED_WORKTREE` and are never cleaned. Test that a clean fixture with T2/T3 verified and T4 in progress returns `resume_stage = T4_ANALYTICS_ADMIN`.

- [ ] **Step 2: Run fixture tests and verify failure**

- [ ] **Step 3: Implement Git/workspace inspection without mutation**

Use:

```powershell
git rev-parse --show-toplevel
git branch --show-current
git rev-parse HEAD
git status --porcelain=v1
```

Never call `reset`, `clean`, destructive checkout, or stash as an automatic safety workaround.

- [ ] **Step 4: Implement capability checks**

Check presence/auth for `codex`, `git`, GitHub path (`gh auth status` when `gh` exists), `OPENAI_API_KEY`, `NOTION_TOKEN`, configured Notion target and local Z-Blog paths. Missing secrets return a structured credential blocker; they are not logged.

- [ ] **Step 5: Implement T4 Resume Gate**

Explicitly reject legacy `.codex-state.json` as v4 authority. Read `knowledge/PROJECT-STATE.md` and require real Git evidence to reconcile. If verified T2/T3 markers exist and T4 is incomplete, return T4 without rerunning T2/T3.

- [ ] **Step 6: Run tests and a read-only live preflight**

Run:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tests/bridge/Test-Preflight.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/bridge-preflight.ps1 -ReadOnly
```

Expected live result: no source mutation; any missing credential is reported as a blocker without exposing its value.

- [ ] **Step 7: Commit**

```bash
git add scripts/bridge-preflight.ps1 bridge/lib/Bridge.State.psm1 tests/bridge/Test-Preflight.ps1
git commit -m "feat(bridge): add resume and safety preflight"
```

---

### Task 4: Implement Codex App Server client with persistent thread support

**Files:**
- Create: `bridge/lib/Bridge.AppServer.psm1`
- Create: `tests/bridge/fixtures/fake-app-server.ps1`
- Create: `tests/bridge/Test-AppServer.ps1`

**Interfaces:**
- Produces: `Start-CodexAppServer`, `Initialize-CodexSession`, `Start-CodexTurn`, `Read-CodexEvents`, `Stop-CodexAppServer`, `Invoke-CodexExecFallback`.
- Consumes: process helpers and state engine.

- [ ] **Step 1: Write a fake JSONL server and failing client tests**

The fixture emits one JSON object per line and simulates initialization, thread creation/resume, a turn, streamed notifications and a terminal result. The test asserts that the client keeps the same `thread_id` across two turns.

- [ ] **Step 2: Run the test and verify failure**

- [ ] **Step 3: Implement App Server process transport**

Launch `codex app-server` using `System.Diagnostics.Process` with redirected stdin/stdout/stderr. Treat stdout as JSONL only. Keep stderr separately and redact before persistence.

The client must follow the current official App Server generated schema rather than hand-inventing protocol fields. During implementation run:

```bash
codex app-server generate-json-schema
```

Store the generated schema only as a temporary implementation reference unless license/size review justifies committing it.

- [ ] **Step 4: Implement server-initiated approval handling**

Bridge policy may automatically approve only routine reversible actions already authorized by `AGENTS.md`. Any request outside the workspace, destructive action or production access becomes `BLOCKED` rather than auto-approved.

- [ ] **Step 5: Implement fallback**

`Invoke-CodexExecFallback` is allowed only when App Server startup/protocol is unavailable. It must preserve the same request/result envelope and must be marked `executor_transport = codex_exec_fallback`.

- [ ] **Step 6: Run fake-server tests and a read-only live App Server handshake**

Expected: persistent thread ID, terminal turn event recognized, clean process shutdown/reconnect.

- [ ] **Step 7: Commit**

```bash
git add bridge/lib/Bridge.AppServer.psm1 tests/bridge
git commit -m "feat(bridge): add Codex App Server transport"
```

---

### Task 5: Implement GPT controller adapter using Responses API structured output

**Files:**
- Create: `bridge/lib/Bridge.Gpt.psm1`
- Create: `bridge/prompts/gpt-controller.md`
- Create: `tests/bridge/Test-Gpt.ps1`

**Interfaces:**
- Produces: `Invoke-GptBridgeDecision -Context <object> -ExecutionResult <object>` returning exactly `NEXT_STAGE|REPAIR|RETRY_INFRA|BLOCKED|RELEASE_READY` plus reason, repair instruction, required gates and safety classification.
- Consumes: `OPENAI_API_KEY`, configured controller model and result schema.

- [ ] **Step 1: Write mocked HTTP tests**

Test a failing PHPUnit result maps to `REPAIR`, a transient GitHub 502 maps to `RETRY_INFRA`, and a destructive production request maps to `BLOCKED`.

- [ ] **Step 2: Verify tests fail before adapter exists**

- [ ] **Step 3: Implement Responses API call**

Use `POST https://api.openai.com/v1/responses` with bearer auth from `OPENAI_API_KEY`. Request structured JSON output matching a controller decision schema. Do not put the API key into prompts, files or diagnostics.

The prompt must state:

```text
Real Git/runtime/CI evidence outranks recorded state.
Never rewrite verified T2/T3.
Routine development failures => REPAIR, not BLOCKED.
Never bypass required local runtime or exact-SHA CI.
Never authorize merge/tag/release before the project Release Gate.
Return JSON only matching the supplied schema.
```

- [ ] **Step 4: Add controller conversation continuity**

Persist only safe response/thread identifiers required for controller continuity; do not persist hidden reasoning or raw secrets. When using Responses API continuation, preserve the supported response linkage mechanism from current official docs.

- [ ] **Step 5: Run mocked tests and one harmless live classification probe**

The live probe must use a synthetic failure object and must not dispatch Codex or mutate the repository.

- [ ] **Step 6: Commit**

```bash
git add bridge/lib/Bridge.Gpt.psm1 bridge/prompts/gpt-controller.md tests/bridge/Test-Gpt.ps1
git commit -m "feat(bridge): add GPT controller decisions"
```

---

### Task 6: Implement direct Notion context and writeback adapter

**Files:**
- Create: `bridge/lib/Bridge.Notion.psm1`
- Create: `tests/bridge/Test-Notion.ps1`
- Modify: `bridge/config.example.json`

**Interfaces:**
- Produces: `Get-NotionProjectContext`, `Write-NotionStageUpdate`, `Confirm-NotionStageUpdate`.
- Consumes: `NOTION_TOKEN` and local-only target identifiers.

- [ ] **Step 1: Write mocked Notion tests**

Assert authorization comes only from environment, logs are redacted, HTTP 429 becomes `RETRYABLE_INFRA`, HTTP 401 becomes credential `BLOCKED`, and a write is not PASS until read-back contains the expected stage/release marker.

- [ ] **Step 2: Verify failure before adapter exists**

- [ ] **Step 3: Implement REST adapter**

Use the current Notion API version header documented at implementation time. Keep the API base URL and target IDs configurable. No real page ID goes into committed config.

`bridge/config.example.json` adds:

```json
{
  "notion": {
    "enabled": true,
    "token_source": "env:NOTION_TOKEN",
    "target_source": "env:XZ_VISIT_STATS_NOTION_TARGET"
  }
}
```

- [ ] **Step 4: Implement deterministic write/read-back**

Write a compact stage payload containing request ID, stage, status, branch, SHA, gates, release state and timestamp. Verify by fetching the target and matching the request ID + stage + SHA marker.

- [ ] **Step 5: Run mocked tests**

A live Notion check is deferred until credential activation preflight; do not invent a PASS without a real token.

- [ ] **Step 6: Commit**

```bash
git add bridge/lib/Bridge.Notion.psm1 bridge/config.example.json tests/bridge/Test-Notion.ps1
git commit -m "feat(bridge): add autonomous Notion adapter"
```

---

### Task 7: Implement project gate adapters

**Files:**
- Create: `bridge/lib/Bridge.Gates.psm1`
- Create: `scripts/bridge-runtime-gate.ps1`
- Create: `scripts/bridge-ci-gate.ps1`
- Create: `scripts/bridge-report.ps1`
- Create: `tests/bridge/Test-Gates.ps1`

**Interfaces:**
- Produces: `Invoke-UnitGate`, `Invoke-LocalRuntimeGate`, `Invoke-SqlExplainGate`, `Wait-ExactShaCi`, `Get-ReleaseGate`, `Get-SixGateReport`.
- Consumes: existing `scripts/local-verify.ps1`, PHP/PHPUnit commands, GitHub CLI/REST and project release docs.

- [ ] **Step 1: Write failing gate tests using command-result fixtures**

Verify that CI for SHA A cannot satisfy SHA B, local runtime `NOT REQUIRED` requires a reason, and T4 returns `Release Gate = NOT READY` rather than skipping the gate.

- [ ] **Step 2: Implement unit/fast gate wrapper**

Reuse existing project checks; do not duplicate plugin test logic. Capture command, exit code and sanitized evidence.

- [ ] **Step 3: Implement local runtime wrapper**

Before deployment, create a timestamped backup of only `zb_users/plugin/xz_visit_stats`. Sync only the target plugin. Delegate standard verification to existing project scripts and task-specific commands. Never claim PASS from planned commands.

- [ ] **Step 4: Implement SQL EXPLAIN evidence adapter**

For T4, accept only explicit EXPLAIN output tied to the queries under review; preserve MySQL 5.7 compatibility.

- [ ] **Step 5: Implement exact-SHA CI polling**

Record candidate SHA, poll workflow runs until terminal state, and return logs/URLs or sanitized job summaries on failure. A new repair commit invalidates prior SHA evidence.

- [ ] **Step 6: Implement six-gate report formatter**

Output exactly the `AGENTS.md` six-gate format and enforce `FINAL: INCOMPLETE` if any gate is BLOCKED.

- [ ] **Step 7: Run tests and commit**

```bash
git add bridge/lib/Bridge.Gates.psm1 scripts/bridge-*.ps1 tests/bridge/Test-Gates.ps1
git commit -m "feat(bridge): add runtime CI and release gates"
```

---

### Task 8: Implement autonomous orchestrator and repair state machine

**Files:**
- Create: `bridge/lib/Bridge.Orchestrator.psm1`
- Create: `bridge/prompts/codex-executor.md`
- Create: `tests/bridge/Test-Orchestrator.ps1`

**Interfaces:**
- Produces: `Invoke-AutonomousBridgeRun` and `Resume-AutonomousBridgeRun`.
- Consumes: Tasks 2–7 modules.

- [ ] **Step 1: Write a fail→repair→pass integration test**

Synthetic sequence:

```text
RESUME_GATE PASS
→ Codex result: PHPUnit FAIL
→ GPT: REPAIR
→ Codex focused repair PASS
→ UNIT_TEST PASS
→ LOCAL_RUNTIME PASS
→ SQL_EXPLAIN PASS
→ GITHUB_CI PASS
→ T4 Release Gate NOT READY
→ persist next downstream stage, no release
```

Assert no user-confirmation state is entered for the PHPUnit failure.

- [ ] **Step 2: Write crash/resume test**

Terminate the fixture after `CODEX_RUNNING`, reload `bridge/state.json`, and assert resume continues from the last safe state rather than restarting T2/T3.

- [ ] **Step 3: Implement state-driven loop**

Core pseudocode must map directly to code:

```powershell
while ($state.status -notin @('COMPLETE','BLOCKED','FAILED')) {
    $evidence = Invoke-CurrentBridgeStage -State $state
    $decision = Invoke-GptBridgeDecision -Context $context -ExecutionResult $evidence
    $state = Move-BridgeState -State $state -Decision $decision
    Save-BridgeState $state
}
```

Every transition persists before the next external mutation.

- [ ] **Step 4: Implement repair policy**

Maximum 3 code-repair rounds per failure cluster. Each new round must change diagnosis or repair instruction. Infrastructure retries are counted separately. After three code-repair rounds, perform GPT escalation; BLOCK only if continued mutation is unsafe or access/evidence is missing.

- [ ] **Step 5: Implement T4 dispatch contract**

First real request must contain:

```json
{
  "action": "RESUME",
  "current_stage": "T4_ANALYTICS_ADMIN",
  "task_file": ".codex-tasks/08-v4-t4-analytics-admin.md",
  "resume_policy": "PRESERVE_VERIFIED",
  "required_gates": ["UNIT_TEST","LOCAL_RUNTIME","SQL_EXPLAIN","GITHUB_CI"],
  "forbidden_actions": ["REWRITE_T2_T3","MERGE_T4_PREMATURELY","TAG_T4_PREMATURELY","RELEASE_T4_PREMATURELY"]
}
```

- [ ] **Step 6: Run synthetic integration tests and commit**

```bash
git add bridge/lib/Bridge.Orchestrator.psm1 bridge/prompts/codex-executor.md tests/bridge/Test-Orchestrator.ps1
git commit -m "feat(bridge): add autonomous repair orchestrator"
```

---

### Task 9: Add CLI entrypoint and restart-at-boot option

**Files:**
- Create: `scripts/gpt-codex-bridge.ps1`
- Create: `scripts/install-bridge-task.ps1`
- Create: `tests/bridge/Test-Cli.ps1`

**Interfaces:**
- Produces commands: `preflight`, `start`, `resume`, `status`, `stop-after-stage`, `report`.
- Consumes: orchestrator and state engine.

- [ ] **Step 1: Write CLI parsing tests**

Verify `status` is read-only, `resume` requires an existing non-terminal state, and `start` always runs preflight first.

- [ ] **Step 2: Implement entrypoint**

Examples:

```powershell
.\scripts\gpt-codex-bridge.ps1 preflight
.\scripts\gpt-codex-bridge.ps1 start -Requirement "Continue xz_visit_stats v4.0 to release"
.\scripts\gpt-codex-bridge.ps1 resume
.\scripts\gpt-codex-bridge.ps1 status
```

No command requires the user to copy Codex output back to GPT.

- [ ] **Step 3: Implement optional Scheduled Task bootstrap**

Create a task only with explicit one-time installation invocation. The task runs `resume` on machine startup/logon and exits harmlessly when state is terminal/IDLE. Do not store credentials in task arguments.

- [ ] **Step 4: Run CLI tests and commit**

```bash
git add scripts/gpt-codex-bridge.ps1 scripts/install-bridge-task.ps1 tests/bridge/Test-Cli.ps1
git commit -m "feat(bridge): add autonomous bridge CLI"
```

---

### Task 10: Add Bridge CI without pretending CI is local runtime

**Files:**
- Create: `.github/workflows/bridge-check.yml`
- Create: `tests/bridge/Run-All.ps1`

**Interfaces:**
- Produces: repository-level bridge contract/test CI.
- Consumes: all dependency-free bridge tests.

- [ ] **Step 1: Create test aggregator**

`Run-All.ps1` executes every `Test-*.ps1`, stops on first non-zero exit, and returns non-zero overall on failure.

- [ ] **Step 2: Create workflow**

Run on pull request/push for bridge/script/doc paths using `windows-latest` PowerShell. Do not attempt the user's local Z-Blog runtime or MySQL from GitHub Actions.

- [ ] **Step 3: Run locally**

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tests/bridge/Run-All.ps1
```

Expected: PASS.

- [ ] **Step 4: Commit and push Bridge branch**

```bash
git add .github/workflows/bridge-check.yml tests/bridge/Run-All.ps1
git commit -m "ci(bridge): verify autonomous control plane"
git push origin feature/gpt-codex-bridge-v1
```

- [ ] **Step 5: Require exact-SHA Bridge CI PASS**

Do not proceed to activation on a failed workflow.

---

### Task 11: Live Phase A control-plane verification

**Files:**
- Modify only if defects are found: Bridge files from Tasks 1–10.
- Update after verified result: `knowledge/PROJECT-STATE.md`

**Interfaces:**
- Produces: verified live Bridge transport/preflight evidence without modifying plugin business code.

- [ ] **Step 1: Run live credential/environment preflight**

Require real PASS for OpenAI API auth, Codex App Server, Git/GitHub auth, Notion read/write target, repository safety and expected local Z-Blog access.

Missing credentials are a one-time `BLOCKED` setup condition; do not fake PASS.

- [ ] **Step 2: Run read-only App Server round trip**

Dispatch a task that only asks Codex to read `AGENTS.md`, current branch/HEAD and T4 task file and return structured evidence. It must not modify source.

- [ ] **Step 3: Run synthetic GPT→Codex→GPT repair fixture through the real bridge**

Use a controlled fixture/sandbox failure, not the plugin runtime, to prove automatic bidirectional repair transport.

- [ ] **Step 4: Kill/restart bridge and prove resume**

Stop the process after persisted non-terminal state, restart, and verify it reconnects/resumes without user copy/paste.

- [ ] **Step 5: Record verified Phase A state**

Update `knowledge/PROJECT-STATE.md` with exact branch/SHA, transport, preflight status and remaining activation blockers. Do not mark Bridge READY if any acceptance check failed.

- [ ] **Step 6: Commit/push any fixes and require exact-SHA CI PASS**

---

### Task 12: Live Phase B takeover of current T4

**Files:**
- Current T4 plugin files as dictated by `.codex-tasks/08-v4-t4-analytics-admin.md`.
- Bridge state/evidence files only in sanitized committed form.
- Update: `knowledge/PROJECT-STATE.md`.

**Interfaces:**
- Produces: proof the Bridge can resume real T4 and autonomously drive its existing task/gates.
- Consumes: current real `feature/visit-stats-4.0` HEAD; never assumes the Bridge branch's old base is still latest.

- [ ] **Step 1: Reconcile latest remote T4 before takeover**

Fetch/reconcile `feature/visit-stats-4.0`, verify no unrelated local work would be overwritten, and create/use an isolated worktree if needed. Preserve T2/T3.

- [ ] **Step 2: Start one autonomous T4 request**

User-facing requirement for acceptance run:

```text
Resume xz_visit_stats v4.0 from the current verified state. Complete T4 according to .codex-tasks/08-v4-t4-analytics-admin.md, automatically repair failures, run required local Z-Blog and MySQL EXPLAIN verification, push candidate commits and require exact-SHA GitHub CI. Preserve T2/T3 and stop before any action forbidden by the current release plan.
```

The user must not manually relay any Codex/GPT message during this run.

- [ ] **Step 3: Require real T4 gates**

`UNIT_TEST → LOCAL_RUNTIME → SQL_EXPLAIN → GITHUB_CI → T4 completion gate`.

A T4 `Release Gate: NOT READY` is expected if downstream release stages remain.

- [ ] **Step 4: Prove automatic repair if a real failure occurs**

If no organic failure occurs, do not inject a failure into production code merely to prove repair; Task 11 synthetic repair evidence is sufficient.

- [ ] **Step 5: Continue to the project's defined next stage automatically**

Do not stop merely because T4 is complete. Read the verified project state and follow the existing downstream task/release plan. Do not invent a T5 task if the repository does not define one; GPT/Codex must derive the next legitimate stage from current project documents and release rules.

---

### Task 13: Release automation and full six-gate completion

**Files:**
- Modify as required by existing release docs: `plugin.xml`, `CHANGELOG.md`, `docs/VERSION.md`, release notes or formal package scripts.
- Update: `knowledge/PROJECT-STATE.md`.

**Interfaces:**
- Produces: real release Tag, formal ZIP, GitHub Release, Notion writeback and six-gate evidence.
- Consumes: existing `docs/RELEASE.md` and verified downstream project state.

- [ ] **Step 1: Let Bridge evaluate Release Gate from real evidence**

No release action occurs while gate is `NOT READY` or `BLOCKED`.

- [ ] **Step 2: On PASS, build formal release artifact through the repository-approved path**

Verify version metadata, package contents and checksum.

- [ ] **Step 3: Require release-candidate exact-SHA CI PASS and final required local runtime PASS**

- [ ] **Step 4: Create/verify tag and GitHub Release**

`RELEASED` is invalid until tag points to the intended release commit and the GitHub Release contains the intended formal ZIP.

- [ ] **Step 5: Perform Notion writeback and read-back verification**

Transient failures auto-retry. Missing/expired auth becomes persisted BLOCKED without undoing the release.

- [ ] **Step 6: Emit exact six-gate report**

Use the `AGENTS.md` required block and include `RELEASE: RELEASED` only with real evidence.

---

### Task 14: Activate Bridge as the mandatory future development norm

**Files:**
- Modify: `AGENTS.md`
- Modify: `README-AUTOMATION.md`
- Modify: `knowledge/PROJECT-STATE.md`
- Create: `docs/BRIDGE-AUTONOMOUS-DEVELOPMENT.md`

**Interfaces:**
- Produces: repository policy that future normal development begins from user requirement/PRD and uses the Bridge automatically.
- Consumes: successful Tasks 10–13 evidence; this policy is not activated merely because code exists.

- [ ] **Step 1: Add explicit activation rule to `AGENTS.md`**

Add the normative policy:

```text
AUTONOMOUS_EXECUTION = REQUIRED
MANUAL_GPT_CODEX_COPY_PASTE = FORBIDDEN

Normal workflow:
User goal → GPT requirement/PRD/Reuse Gate → GPT-Codex Bridge → Codex real execution → automated tests/runtime/CI/repair → Release Gate → Release → Notion writeback.

The user is not required to relay Codex output to GPT or GPT instructions to Codex. Ordinary reversible development failures are handled by the bridge loop. Human intervention is reserved for explicit BLOCKED conditions.
```

Also state that if Bridge is unavailable, fallback direct Codex execution is an emergency/degraded mode and must be recorded; it is not the normal workflow.

- [ ] **Step 2: Deprecate old v1.3 manual controller instructions**

Keep historical files for audit, but prepend `README-AUTOMATION.md` with a clear notice that `dev-v1.3.ps1 approve/next` is legacy and not authoritative for v4/future development.

- [ ] **Step 3: Write operator documentation**

`docs/BRIDGE-AUTONOMOUS-DEVELOPMENT.md` must show normal use as one requirement submission, one-time credential bootstrap, status/recovery behavior and BLOCKED conditions. It must not instruct the user to copy long GPT/Codex prompts between windows.

- [ ] **Step 4: Update project state with activation evidence**

Record exact Bridge version/commit, live App Server handshake, controller model used, Notion adapter verification, local takeover run, CI evidence and activation date.

- [ ] **Step 5: Run documentation/secret/diff checks and commit**

```bash
git diff --check
git grep -n -E 'sk-[A-Za-z0-9_-]{12,}|NOTION_TOKEN=.*[^)]' -- . ':!docs/superpowers/plans/*' || true
git add AGENTS.md README-AUTOMATION.md knowledge/PROJECT-STATE.md docs/BRIDGE-AUTONOMOUS-DEVELOPMENT.md
git commit -m "docs(bridge): make autonomous execution the default workflow"
```

- [ ] **Step 6: Push and require final exact-SHA CI PASS**

The mandatory policy becomes authoritative only after this commit and its required CI/verification evidence pass.

---

## Plan Self-Review

**Spec coverage:**
- Bidirectional GPT↔Codex transport: Tasks 4, 5, 8.
- Crash-safe state/resume: Tasks 2, 8, 9, 11.
- T2/T3 preservation and T4 resume: Tasks 3, 8, 12.
- Automatic repair: Tasks 5, 8, 11–12.
- Local Z-Blog/SQL/CI/release gates: Tasks 7, 12, 13.
- Direct autonomous Notion integration: Task 6 and Task 13.
- Credential/secret safety: Tasks 1–3, 5–6.
- Exact-SHA CI: Tasks 7, 10, 12–14.
- Full release semantics: Task 13.
- Future mandatory development norm: Task 14.

**Placeholder scan:** No TBD/TODO/“implement later” placeholders are permitted. Live credential verification is intentionally a named acceptance gate, not an implementation placeholder.

**Type/name consistency:** Controller decisions are `NEXT_STAGE|REPAIR|RETRY_INFRA|BLOCKED|RELEASE_READY`; execution results remain `PASS|REPAIRABLE|RETRYABLE_INFRA|BLOCKED|FAILED`; lifecycle state names follow the design spec.

**Scope decision:** This remains one coherent subsystem because every task contributes directly to the same autonomous control plane and its activation. T4 business implementation remains governed by the existing T4 plan rather than duplicated here.
