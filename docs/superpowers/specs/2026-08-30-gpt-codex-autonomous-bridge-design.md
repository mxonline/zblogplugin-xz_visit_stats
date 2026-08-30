# GPT-Codex Autonomous Development Bridge — Design Specification

Date: 2026-08-30
Project: `xz_visit_stats`
Target: bridge control plane v1
Integration baseline: `feature/visit-stats-4.0`
Implementation branch: `feature/gpt-codex-bridge-v1`

## 1. Purpose

Build an additive autonomous development bridge that removes manual copying between GPT and Codex. A user submits a development goal once; the bridge continuously transfers structured tasks and results between a GPT controller and Codex, drives repair loops, executes the existing local verification and GitHub CI gates, and continues until a legitimate release succeeds or a hard BLOCKED condition requires human intervention.

The bridge is a control plane. It does not replace the existing Z-Blog plugin development process, T4 task specification, tests, local runtime verification, GitHub CI, release rules, or Notion project records.

## 2. Current project state and non-regression boundary

The bridge must resume the existing v4.0 program rather than restart it.

- T2 is verified and locked.
- T3 is verified and locked.
- T4 analytics/admin work is the current in-progress phase.
- Primary task entrypoint: `.codex-tasks/08-v4-t4-analytics-admin.md`.
- Current development branch baseline: `feature/visit-stats-4.0`.
- T4 must preserve all v3 historical data and verified T3 session/event data.
- T4 must complete its existing runtime and CI gates before any downstream phase is accepted.
- Existing T4 constraints remain authoritative, including dwell semantics, privacy behavior, keyset pagination requirements, controlled export behavior, Windows local verification and MySQL EXPLAIN checks.

The bridge must not reinterpret or rewrite T2/T3 evidence. It must not make legacy `.codex-state.json` authoritative for v4.

## 3. Chosen architecture

Use a hybrid architecture:

1. GPT controller: reasoning, root-cause analysis, gate decisions and repair strategy.
2. Codex App Server: long-lived Codex execution interface and thread continuity.
3. Local Bridge Controller: state machine, message transport, structured persistence, retries, timeouts and process supervision.
4. Git/local runtime/GitHub CI: evidence sources.
5. JSON control-plane state: crash recovery and audit trail.
6. `codex exec`: fallback execution path only if App Server is temporarily unavailable.
7. ChatGPT-connected Notion layer: project context and writeback; no Notion API secret is required inside the local bridge for v1.

The bridge should use official OpenAI programmatic interfaces rather than screen automation or parsing ChatGPT web UI output.

## 4. Authority order

When records disagree, trust evidence in this order:

1. Real Git repository state and exact commit SHA.
2. Real local Z-Blog runtime and database evidence.
3. GitHub Actions result for the exact SHA under review.
4. `bridge/state.json` and per-run bridge records.
5. `knowledge/PROJECT-STATE.md`.
6. Notion project records.
7. Historical chat or legacy controller files.

No lower-level record may override higher-trust evidence.

## 5. High-level data flow

```text
User requirement
    ↓
GPT Controller
    ↓
Context / Resume Gate
    ↓
Structured Bridge Request
    ↓
Local Bridge Controller
    ↓
Codex App Server / existing Codex thread
    ↓
Real Windows repository
    ↓
Code + tests + local Z-Blog + MySQL
    ↓
Commit / push
    ↓
Exact-SHA GitHub CI
    ↓
Structured Bridge Result
    ↓
GPT Controller Review
    ├─ PASS → next gate/stage
    ├─ REPAIRABLE → focused repair instruction → same Codex thread
    ├─ RETRYABLE_INFRA → bounded retry/reconnect
    └─ BLOCKED → persist state and stop safely
    ↓
Release Gate
    ↓
Tag + formal ZIP + GitHub Release
    ↓
Notion writeback
    ↓
COMPLETE
```

There is no routine user copy/paste step between GPT and Codex.

## 6. Resume Gate

Every autonomous run begins with a Resume Gate before dispatching work.

The Resume Gate must:

- inspect repository path, branch, HEAD and git status;
- detect unsafe dirty or unrelated changes before modifying anything;
- read `AGENTS.md`, `knowledge/PROJECT-STATE.md`, relevant PRD/plan/task files and bridge state;
- reconcile recorded state with real Git/runtime/CI evidence;
- preserve verified phases and avoid repeating completed migrations or destructive checks;
- determine the earliest legitimate unfinished stage;
- verify that the intended action stays inside the authorized local test workspace;
- create an isolated worktree when bridge/controller development would otherwise interfere with an in-progress plugin worktree.

For the first production use on the current project, the expected resume target is T4, not T2/T3.

## 7. State machine

The bridge state machine is explicit and restartable.

Allowed lifecycle states:

- `IDLE`
- `CONTEXT_SYNC`
- `RESUME_GATE`
- `TASK_DISPATCH`
- `CODEX_RUNNING`
- `RESULT_COLLECT`
- `GPT_REVIEW`
- `REPAIR`
- `UNIT_TEST`
- `LOCAL_RUNTIME`
- `SQL_EXPLAIN`
- `GITHUB_CI`
- `RELEASE_GATE`
- `RELEASE`
- `NOTION_WRITEBACK`
- `COMPLETE`
- `BLOCKED`
- `FAILED`

Stage results use:

- `PASS`
- `REPAIRABLE`
- `RETRYABLE_INFRA`
- `BLOCKED`
- `FAILED`

`BLOCKED` is reserved for conditions that legitimately require user intervention. Routine test failures are not BLOCKED.

## 8. Execution lanes

The bridge exposes reusable lanes similar in concept to the firmware control plane but tailored to Z-Blog development:

- `DOC_ONLY`
- `FAST_GATE`
- `UNIT_TEST`
- `LOCAL_RUNTIME`
- `SQL_EXPLAIN`
- `GITHUB_CI`
- `RELEASE_GATE`

For current T4, the mandatory verification chain is:

`UNIT_TEST → LOCAL_RUNTIME → SQL_EXPLAIN → GITHUB_CI → T4 completion gate`

Release is not permitted merely because T4 passes. The bridge must continue only through the project's defined downstream release preparation and final runtime/release gates.

## 9. Request contract

Each GPT-to-Codex dispatch is machine-readable and schema validated. Minimum fields:

```json
{
  "schema_version": "1.0",
  "request_id": "BRIDGE-...",
  "action": "RESUME|EXECUTE|REPAIR|VERIFY|RELEASE",
  "project": "xz_visit_stats",
  "target_version": "4.0.0",
  "branch": "feature/visit-stats-4.0",
  "expected_head_sha": null,
  "current_stage": "T4_ANALYTICS_ADMIN",
  "task_file": ".codex-tasks/08-v4-t4-analytics-admin.md",
  "resume_policy": "PRESERVE_VERIFIED",
  "required_gates": [],
  "forbidden_actions": [],
  "repair_context": null,
  "max_repair_rounds": 3
}
```

The first T4 request must explicitly preserve T2/T3 and forbid premature merge/tag/release.

## 10. Result contract

Codex/Bridge returns structured evidence rather than an unstructured completion paragraph. Minimum fields:

```json
{
  "schema_version": "1.0",
  "request_id": "BRIDGE-...",
  "stage": "...",
  "result": "PASS|REPAIRABLE|RETRYABLE_INFRA|BLOCKED|FAILED",
  "branch": "...",
  "head_sha": "...",
  "files_changed": [],
  "tests": [],
  "runtime_checks": [],
  "sql_explain": [],
  "ci": {},
  "failures": [],
  "root_cause": null,
  "release_gate": "NOT_READY|PASS|BLOCKED",
  "next_action": "..."
}
```

Claims of PASS require evidence. A CI PASS cannot substitute for local runtime verification when local runtime is required.

## 11. Autonomous repair loop

Default maximum automatic repair rounds per distinct failure cluster: 3.

For a routine failure:

1. collect focused logs and the failing command/gate;
2. GPT classifies the failure and proposes the smallest justified repair;
3. dispatch repair into the same Codex thread when safe;
4. Codex performs root-cause analysis before editing;
5. run the narrowest relevant test first;
6. rerun every affected downstream gate;
7. update structured result and state;
8. continue automatically on PASS.

Repair rounds are not arbitrary retries. Each round must record a new diagnosis or changed corrective action. Repeating the same failing command without new evidence does not consume another code-repair round; it is treated as infrastructure retry behavior.

After three unsuccessful code-repair rounds, GPT performs an escalation analysis. It may open a new Codex thread with the full preserved evidence if context corruption is suspected. It becomes BLOCKED only if further autonomous action is unsafe or lacks required access/evidence.

## 12. Legitimate BLOCKED conditions

The bridge may stop for the user only when at least one of these applies:

- missing or expired credential/session that cannot be refreshed automatically;
- required action would touch production data/site outside the authorized local environment;
- destructive or irreversible action cannot be proven safe;
- schema/runtime conflict creates credible data-loss risk;
- branch/worktree safety cannot be resolved without discarding unrelated user work;
- required external authorization is unavailable;
- repeated repair analysis concludes that further automatic mutation is materially unsafe.

PHP errors, PHPUnit failures, JavaScript syntax errors, SQL errors, bad query plans, failed admin pages and failed GitHub CI are normally repairable, not BLOCKED.

## 13. Worktree and data safety

The bridge must fail closed on unsafe workspace conditions.

- Never reset, clean, discard or overwrite unrelated uncommitted work.
- Never force push.
- Never modify `zb_system`.
- Never modify unrelated plugins or a production site.
- Before local plugin deployment, create a timestamped backup of the currently running plugin directory.
- Sync only the target plugin into the authorized local Z-Blog instance.
- Database migrations must be repeatable/idempotent where required and preserve verified historical data.
- Secrets, cookies, tokens, API keys, raw private visitor data and credentials must not be committed or copied into bridge run artifacts.
- `OPENAI_API_KEY` is provided through the machine environment or an OS secret mechanism, never repository files.

## 14. Local runtime contract

The existing project runtime contract remains unchanged unless the project state is intentionally updated through its normal process.

Current expected test environment includes:

- Windows local Z-Blog root: `D:\wwwroot\www.xzhao.net`
- plugin root: `D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats`
- PHP CLI: `D:\BtSoft\php\83\php.exe`
- local site: `http://127.0.0.1`
- PHP 8.3.8
- MySQL 5.7.38-log

Bridge preflight must verify these rather than assuming they still match recorded documentation.

## 15. GitHub CI and exact-SHA gate

After Codex commits and pushes a candidate:

1. record the candidate SHA;
2. find the GitHub Actions run for that exact SHA;
3. wait/poll until a terminal conclusion;
4. on failure, collect workflow/job evidence and return it to GPT;
5. repair, commit and push a new SHA;
6. require a new exact-SHA CI PASS;
7. never reuse CI evidence from an earlier commit as proof for a changed candidate.

No merge/tag/release occurs before the project's release gate allows it.

## 16. Release semantics

`RELEASED` means all required project release gates have passed and the bridge has verified all of the following:

- approved release commit/merge state;
- version metadata and changelog are correct;
- final local/runtime checks required by the project pass;
- GitHub CI passes for the release candidate SHA;
- release artifact/ZIP is built by the approved path and verified;
- release tag exists at the intended commit;
- GitHub Release exists and contains the intended artifact;
- project state is updated;
- Notion writeback is complete or explicitly reports a connector BLOCKED state after the release itself.

A successful T4 is not a release. The current T4 release gate remains `NOT READY` until downstream release work legitimately completes.

## 17. Notion integration

For v1, the local bridge does not store a Notion token. The GPT/ChatGPT controller uses the connected Notion capability to:

- obtain project context at start when needed;
- write key verified phase transitions;
- record final release evidence;
- record BLOCKED conditions and resume point.

The bridge emits a structured handoff payload that makes Notion writeback deterministic. If Notion is temporarily unavailable, development may continue when Notion is not itself the blocking source of truth, but final reporting must clearly mark the writeback status and retry it before declaring the full six-gate flow complete.

## 18. Proposed repository layout

The controller is additive and isolated from plugin runtime code:

```text
bridge/
  config.example.json
  request.json
  state.json
  result.json
  schemas/
    request.schema.json
    state.schema.json
    result.schema.json
  prompts/
    gpt-controller.md
    codex-executor.md
  runs/
    .gitkeep
scripts/
  gpt-codex-bridge.ps1
  bridge-preflight.ps1
  bridge-runtime-gate.ps1
  bridge-ci-gate.ps1
  bridge-report.ps1
tests/
  bridge/
```

Generated run logs containing machine-specific or sensitive material must be excluded from version control. Only sanitized fixtures may be committed.

## 19. Controller responsibilities

### GPT Controller

- understand user goal and project context;
- issue schema-valid task/repair decisions;
- analyze failures and root causes;
- enforce release and safety policy;
- decide NEXT_STAGE / REPAIR / BLOCKED / RELEASE_READY;
- perform connected Notion context/writeback operations.

### Local Bridge Controller

- launch/connect to Codex App Server;
- manage thread ID and reconnect behavior;
- validate request/result JSON;
- supervise state transitions, process lifetime and bounded retries;
- execute evidence collection that belongs to the bridge;
- persist crash-safe state atomically;
- prevent unsafe dispatch when preflight fails.

### Codex Executor

- read repository/task context;
- modify real source;
- run tests and local verification;
- perform focused debugging and repair;
- commit/push only when gates permit;
- return concrete machine evidence.

## 20. Model policy

Model selection is configuration-driven, not hard-coded into source.

Recommended policy:

- GPT controller, architecture/root-cause escalation, release gate: GPT-5.6 class model with high reasoning.
- Codex multi-file T4 implementation, SQL/admin architecture or difficult bugs: high/xhigh reasoning.
- Simple syntax/status/format checks: faster lower-cost mode.
- A failed repair round may escalate reasoning strength automatically without changing project semantics.

The bridge must record the actual controller/executor model identifiers used for each run but must not require one specific future model name to remain available forever.

## 21. First deployment strategy: progressive takeover

The bridge is introduced without interrupting current T4 development.

Phase A — control-plane verification:

- create bridge files and schemas only;
- validate state persistence, App Server connection, structured request/result round-trip and safe reconnect;
- perform a read-only T4 context/preflight check;
- do not mutate plugin runtime code.

Phase B — bounded T4 takeover:

- resume T4 from actual current evidence;
- dispatch the existing T4 task rather than redesign it;
- execute existing tests and local gates;
- verify GPT-driven automatic repair with at least one controlled test fixture or simulated failure path;
- preserve T2/T3.

Phase C — full autonomous development/release control:

- bridge drives remaining stages and release preparation;
- release is still subject to the normal project Release Gate;
- final success requires real tag, release and artifact verification.

If the bridge itself fails during takeover, the existing manual/direct Codex development process remains usable as a fallback. Bridge failure must not corrupt plugin source or project evidence.

## 22. Acceptance criteria

The bridge is accepted only when all of these are demonstrated:

1. A user can submit one development instruction without manually copying Codex output to GPT or GPT instructions back to Codex.
2. GPT and Codex exchange schema-valid messages automatically.
3. Codex uses a persistent/recoverable thread or the documented fallback.
4. Bridge resumes the real current project state without repeating T2/T3.
5. Routine code/test/runtime/CI failures automatically enter repair analysis and rerun affected gates.
6. Local Z-Blog runtime verification cannot be silently skipped when required.
7. Exact-SHA GitHub CI is enforced.
8. Unsafe dirty worktree or destructive-risk conditions fail closed without discarding user work.
9. Restarting the bridge resumes from persisted state and reconfirms higher-trust evidence.
10. Release cannot be declared from prose alone; tag, GitHub Release and verified artifact must exist.
11. The current T4 can complete under bridge control while preserving all existing project constraints.
12. The bridge can continue through downstream development and release gates until `RELEASED`, or produce a precise `BLOCKED` handoff if human intervention is genuinely necessary.

## 23. Out of scope for bridge v1

- replacing the existing Z-Blog plugin architecture;
- replacing existing tests or T4 PRD/task definitions;
- storing ChatGPT session cookies or automating the ChatGPT web UI;
- embedding Notion credentials in the local bridge;
- direct mutation of production website/database;
- broad multi-project orchestration beyond designing interfaces that can be generalized later;
- inventing a new CI/release platform when GitHub Actions and the existing project gates already cover the requirement.

## 24. Rollback

Bridge adoption is additive and reversible.

If bridge behavior is unstable:

1. stop the bridge controller;
2. preserve `bridge/state.json` and sanitized run evidence;
3. do not roll back verified plugin commits merely because the controller failed;
4. resume using the existing direct Codex workflow from the last verified Git/runtime/CI state;
5. fix the bridge on its isolated branch/worktree;
6. reconnect only after bridge tests pass.

The rollback target is the existing `feature/visit-stats-4.0` development process and its verified project state, not a historical T2/T3 code rollback.
