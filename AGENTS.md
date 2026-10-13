# xz_visit_stats Codex workspace rules

## Purpose

This repository is intended to be developed from a Codex workspace that can directly access the real Git working tree and a terminal. Codex is the execution environment for normal development; a separate local Runner is not part of the primary development path.

The desired workflow is:

```text
User requirement
→ ChatGPT requirements / PRD / acceptance criteria
→ project state / task-specific knowledge
→ GitHub + official ecosystem search when a reusable subsystem decision is involved
→ Reuse Gate: USE / REUSE / FORK / BUILD
→ create or resume DEV-YYYYMMDD-NNN canonical Runtime Bundle
→ Codex opens the real project workspace
→ reads this file and current repository state
→ loads only the required project knowledge
→ edits the real source tree
→ runs local automated checks
→ runs local Z-Blog runtime verification when required
→ fixes failures and re-tests
→ Git commit / push / CI
→ release documentation and release gates
→ Notion writeback
→ project knowledge/state writeback
→ mandatory six-gate completion report
```

Routine development must not require the user to copy commands, edit task files, open a second Codex window, or confirm each normal code/test operation.

## Canonical unattended runtime authority

New development runs use the version-independent contract in `docs/DEVELOPMENT-RUNTIME.md`.

Machine execution state is persisted under:

```text
.development/runtime/<execution_id>/state.json
.development/runtime/<execution_id>/events.jsonl
.development/runtime/<execution_id>/evidence/index.json
```

- `execution_id` uses the existing `DEV-YYYYMMDD-NNN` run number and remains stable across chat/Codex/Windows interruption.
- `scripts/dev_runtime.py` owns schema validation, revisioned state, append-only events, evidence binding, Git/CI reconciliation and deterministic next-action evaluation.
- `scripts/dev-flow.ps1` is a thin Windows adapter to the same runtime; it must not maintain a second state model.
- Real Git, exact-head CI, local Z-Blog/runtime, Release and Notion tool results remain factual evidence. If they contradict a stored state string, reconcile/fail closed rather than trusting the state string.
- `.codex-state.json`, `.codex/tasks.json`, `.codex-tasks/` and `dev-v1.3.ps1` are **legacy compatibility** for older task chains. They are not canonical state for new runs and cannot override a valid DEV Runtime Bundle.
- Chat history and model memory are hints only; they have no persistent execution-state authority.

On `continue`, `resume`, or a restarted workspace, load the same Runtime Bundle first, validate it, reconcile the real branch/head SHA, restore Notion/PRD context, then execute exactly the returned next action. Do not create a second run for the same interrupted task.

## Cross-session handoff continuity (2026-10-09)

- Reuse the current `.development/runtime/<execution_id>/state.json`, `events.jsonl`, and `evidence/index.json` as the *only* accepted DEV execution state. This rule does not add another runner, second HANDOFF state, development stage, Release Gate, or human approval.
- At an actually verified checkpoint, external BLOCKED, or voluntary long-session close, project the current execution_id / revision / branch HEAD / verified tests / first causal failure / remedies already attempted / next_action into the existing Notion project HANDOFF. Write and refetch before claiming synchronization. If Notion cannot be reached, preserve Git Runtime truth and mark the projection stale.
- In a fresh Codex conversation, run the existing `scripts/dev_runtime.py`/Windows adapter validation, reconcile Git/CI and local Z-Blog evidence, and only then consume the Notion HANDOFF. Reuse stable verified stages, repair failed current work, and keep existing six-gate completion contract.
- Do not equate a handoff summary or old Notion plan with actual Release; do not interrupt ordinary active work merely to request user “continue”. No secrets, session cookies, database contents or private credentials in handoff.

## Project baseline

- Project: Z-BlogPHP plugin `xz_visit_stats`.
- Repository: `mxonline/zblogplugin-xz_visit_stats`.
- Current formal baseline is the version recorded by `plugin.xml`, `docs/VERSION.md`, Git tags/releases and the current branch. Never trust a stale version written in this file over the real repository state.
- Before starting a task, inspect `git status`, current branch, `plugin.xml`, relevant PRD/version documents and the affected code.
- Major-version work requires local Z-Blog runtime verification before it can be called complete.

## Knowledge loading protocol

The project knowledge router is `knowledge/INDEX.md`.

For every non-trivial task:

1. Read this `AGENTS.md`.
2. Read `knowledge/PROJECT-STATE.md`.
3. Inspect the real current Git state and reconcile any difference before execution.
4. Follow `knowledge/INDEX.md` to load only the documents relevant to the task.
5. Search `knowledge/KNOWN-FAILURES.md` before inventing a new fix for a repeated failure.
6. Use `knowledge/ZBLOG-DEVELOPMENT-KNOWLEDGE.md` for reusable project engineering rules.
7. When a new reusable subsystem/library/algorithm decision is involved, read and execute `knowledge/REUSE-GATE.md` before implementation.
8. After verified work, update project state/knowledge and the corresponding Notion record.

Authority order is real Git/runtime/CI evidence first, then `PROJECT-STATE.md`, current PRD/design/task handoff, this file, project knowledge, Notion, and historical/external references.

The legacy `.codex-state.json` belongs to an older controller/task chain and is not the authoritative v4 project-state source unless a future migration explicitly makes it so and tests that controller behavior.

Do not load all project/history/reference documents by default. Keep context task-specific.

## Reuse Gate hard rule

`knowledge/REUSE-GATE.md` is a prospective pre-implementation decision gate. It does not replace the v4 PRD, current `.codex-tasks/`, local runtime checks, CI or release gates.

- Do not rewind, delete or redo completed v4/T2/T3 work merely because this rule was added after development had started.
- Existing verified data/schema/runtime evidence remains authoritative.
- Apply the gate to unresolved T3/T4 implementation choices and future non-trivial subsystem decisions.
- Search Z-Blog official resources first, then maintained GitHub projects/libraries, before custom-building a generic capability.
- Evaluate license, maintenance, CI/tests, security/dependencies, PHP/Z-Blog/MySQL compatibility, hot-path performance, package size, privacy and maintenance cost.
- Star count is supporting evidence only.
- No explicit `USE / REUSE / FORK / BUILD` decision means no new reusable subsystem implementation should begin.
- A gate may conclude `BUILD + SELECTIVE REUSE`; large analytics systems may be architecture references without being imported or forked.

## Expected local development environment

The v4 Windows audit most recently verified this local environment:

```text
Z-Blog root:   D:\wwwroot\www.xzhao.net
Plugin root:   D:\wwwroot\www.xzhao.net\zb_users\plugin\xz_visit_stats
Local site:    http://127.0.0.1
PHP CLI:       D:\BtSoft\php\83\php.exe
PHP:           8.3.8
Z-BlogPHP:     173540
MySQL:         5.7.38-log
```

Do not blindly hard-code these values into runtime plugin code. Local scripts may use verified values as defaults but must allow overrides. If the real workspace differs, detect the actual paths/versions before testing and update project state after verification.

## Direct execution policy

Codex may directly perform routine development operations inside the authorized development workspace:

- read and edit project files;
- run PHP, PHPUnit, PowerShell and other project test commands;
- access the local Z-Blog test site;
- inspect local development logs and test-database behavior;
- create/switch a development branch when the task requires it;
- commit and push completed development work to the development branch;
- inspect GitHub CI and fix failures when GitHub access is available.

Do not stop to ask for approval for ordinary reversible development operations already inside the task scope.

Pause only when an operation requires credentials that are not available, touches production data/site, is destructive or irreversible, or exceeds the authorized local development workspace.

## Hard safety boundaries

- Never modify `zb_system` core files as part of plugin development.
- Never edit unrelated plugins or production files.
- Never use the local development flow to delete or overwrite production data.
- Do not commit passwords, tokens, database credentials, cookies or private keys.
- Database migration work must preserve existing plugin data unless the explicit task says otherwise and provides a rollback/migration plan.
- Do not claim a runtime test passed unless the command/request/database check was actually executed and the result was observed.

## Development priorities

1. Functional correctness.
2. Backward compatibility and data safety.
3. Fast, automated iteration.
4. Performance on the request/collector hot path.
5. Security and permission correctness.
6. UI consistency.
7. Minimal, human-readable code without unnecessary abstractions.

## Required task loop

For every non-trivial task:

1. Load or create the single canonical `DEV-YYYYMMDD-NNN` Runtime Bundle and validate `state / events / evidence`; when continuing a task, reuse its existing execution ID and task rather than creating a parallel run.
2. Read the real current Git state and reconcile branch/head SHA against Runtime; do not overwrite uncommitted human work.
3. Restore current Notion Context/PRD and record verifiable evidence for Gate 1.
4. Read the real current code and load task-specific knowledge via `knowledge/INDEX.md`.
5. Determine the affected Hook, database, configuration and compatibility surface.
6. If the task introduces/selects a reusable subsystem, run `knowledge/REUSE-GATE.md` and record `USE / REUSE / FORK / BUILD` before production implementation.
7. Make the smallest coherent implementation and run fast local checks.
8. If the task depends on Z-Blog runtime behavior, run the real local-runtime checks described in `docs/TESTING.md`.
9. On failure, read the actual output, check `knowledge/KNOWN-FAILURES.md`, fix the cause and re-run the relevant checks without asking whether to continue.
10. Inspect the final diff for unrelated edits, generated junk and secrets; update documentation/version metadata only when the task or release state requires it.
11. Commit/push the development branch when the requested workflow includes Git delivery; any earlier CI PASS becomes stale after a new head SHA.
12. Validate GitHub CI against the exact current head SHA.
13. Evaluate the Release Gate even when the current phase is not ready to publish.
14. Update `knowledge/PROJECT-STATE.md` and the existing Notion task/plan with observed evidence; refetch Notion when the complete flow requires writeback verification.
15. Persist each accepted checkpoint as evidence + state revision + append-only event, then emit the mandatory six-gate completion report only when the runtime evaluator permits it.

## When local Z-Blog runtime verification is mandatory

Runtime verification is a release blocker for changes involving any of the following:

- major-version work;
- plugin install, enable, disable, uninstall or upgrade behavior;
- database schema, indexes, migrations or stored configuration;
- Z-Blog Hooks and request lifecycle behavior;
- request collection, IP, Referer, UA, bot/spider or HTTP status capture;
- admin pages, AJAX/runtime endpoints or permissions;
- Nginx / PHP FastCGI dependent behavior;
- performance-sensitive collector/statistics queries;
- any behavior that CI unit tests cannot faithfully represent.

A documentation-only change or a small isolated pure-function fix may skip full runtime verification when its acceptance criteria are fully covered by faster checks. In that case the Local Runtime gate must be reported as `NOT REQUIRED` with an explicit reason and evidence.

## Fast checks

Prefer task-relevant checks rather than running every heavy tool after every edit:

- `git diff --check`;
- PHP syntax check for changed PHP files or the full plugin when appropriate;
- existing PHPUnit tests;
- JavaScript syntax checks when JS changes;
- `scripts/local-verify.ps1` for the standard local verification chain;
- `python scripts/dev_runtime.py evaluate ...` or `scripts/dev-flow.ps1 evaluate ...` for runtime-state integrity;
- direct local HTTP/runtime/database checks for runtime-sensitive work.

PHPStan, Semgrep and full regression suites are risk/release driven, not automatic blockers for every small change.

## Runtime testing rules

Use the real local test site and test database. Typical runtime checks include:

- homepage/article requests create expected visit records;
- page-type 404 requests record the correct status;
- static assets/admin/plugin-admin requests do not create normal visit records;
- Googlebot/Baiduspider/bingbot UA requests are classified correctly;
- Referer/source parsing and HTML escaping remain correct;
- plugin disable/enable does not corrupt data or duplicate schema operations;
- upgrade/migration is repeatable and preserves older records;
- PHP/Nginx/SQL logs do not contain new Fatal/Warning/Notice/SQL errors caused by the task.

For database assertions, compare important aggregate results against direct SQL or Z-Blog database queries rather than trusting UI output alone.

## Git and release behavior

- Work on a task-specific development branch unless the current task explicitly uses an existing branch.
- Never overwrite unrelated uncommitted work; inspect `git status` first.
- Commit and push normal development work when the task calls for end-to-end delivery.
- CI failure is part of the normal repair loop: read the real logs, fix locally, re-test and push again.
- A GitHub CI PASS is reusable only when its evidence is bound to the current Runtime `head_sha`; a new Commit invalidates the old CI checkpoint.
- Do not merge/tag/release until release gates pass.
- Formal releases follow `docs/RELEASE.md` and the repository release-document rules.
- An intermediate phase may report `Release Gate: NOT READY`; this means the gate was evaluated and the reason was recorded, not skipped.

## Mandatory full-flow hard gates

Whenever the task says to run the complete development flow, the final report must include exactly these six gates:

```text
FULL DEVELOPMENT FLOW GATE

[1] Notion Context       PASS / BLOCKED
    Evidence: ...
[2] Codex Development    PASS / BLOCKED
    Evidence: ...
[3] Local Runtime        PASS / NOT REQUIRED / BLOCKED
    Evidence: ...
[4] GitHub CI            PASS / NOT REQUIRED / BLOCKED
    Evidence: ...
[5] Release Gate         PASS / NOT READY / BLOCKED
    Evidence: ...
[6] Notion Writeback     PASS / BLOCKED
    Evidence: ...

FINAL: COMPLETE / INCOMPLETE
RELEASE: RELEASED / NOT RELEASED
```

Rules:

- If any gate is `BLOCKED`, `FINAL` must be `INCOMPLETE`.
- A `PASS` without evidence is invalid.
- GitHub CI cannot substitute for mandatory local runtime verification.
- Release Gate may be `NOT READY` for an intermediate phase, but it may never be omitted.
- Notion Context and Notion Writeback are both mandatory gates for the complete flow.
- Only a real Tag + GitHub Release + formal ZIP may produce `RELEASE: RELEASED`.
- Do not use phrases equivalent to “complete development flow finished” before this gate block has been produced.

## Documentation style

README, CHANGELOG, VERSION and Release Notes must describe the real implemented and verified state in a natural maintainer voice. Do not use generic AI-style claims such as “全面优化”“显著提升稳定性” when the code evidence does not justify them. Unverified items must remain marked as unverified.

## Completion report

Every completed task report must contain:

- execution id / run number and state revision;
- current finding / implemented result;
- files changed;
- checks actually executed and their results;
- local Z-Blog/runtime checks actually executed when required;
- Git branch/commit/CI state when applicable;
- Reuse Gate decision when the task involved a reusable subsystem;
- Release Gate result;
- Notion context/writeback evidence for a complete-flow task;
- knowledge/state writeback evidence for non-trivial tasks;
- remaining limitations or external blockers;
- the mandatory six-gate block when complete flow was requested.

Never replace missing execution evidence with planned commands or expected results.
