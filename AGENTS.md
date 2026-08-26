# xz_visit_stats Codex workspace rules

## Purpose

This repository is intended to be developed from a Codex workspace that can directly access the real Git working tree and a terminal. Codex is the execution environment for normal development; a separate local Runner is not part of the primary development path.

The desired workflow is:

```text
User requirement
→ ChatGPT requirements / PRD / acceptance criteria
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
7. After verified work, update project state/knowledge and the corresponding Notion record.

Authority order is real Git/runtime/CI evidence first, then `PROJECT-STATE.md`, current PRD/design/task handoff, this file, project knowledge, Notion, and historical/external references.

The legacy `.codex-state.json` belongs to an older controller/task chain and is not the authoritative v4 project-state source unless a future migration explicitly makes it so and tests that controller behavior.

Do not load all project/history/reference documents by default. Keep context task-specific.

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

1. Read the real current code and repository status.
2. Load the task-specific project knowledge via `knowledge/INDEX.md`.
3. Determine the affected Hook, database, configuration and compatibility surface.
4. Make the smallest coherent implementation.
5. Run fast local checks.
6. If the change depends on Z-Blog runtime behavior, run the real local-runtime checks described in `docs/TESTING.md`.
7. On failure, read the actual error/output, check `knowledge/KNOWN-FAILURES.md`, fix the cause and re-run the relevant checks.
8. Inspect the final diff for unrelated edits, generated junk and secrets.
9. Update documentation/version metadata only when the task or release state requires it.
10. Commit/push the development branch when the requested workflow includes Git delivery.
11. Evaluate the Release Gate even when the current phase is not ready to publish.
12. Update `knowledge/PROJECT-STATE.md`; add reusable knowledge/failure entries only when supported by observed evidence.
13. Ensure the controller has written the real result back to Notion.
14. Emit the mandatory six-gate completion report.

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

A documentation-only change or a small isolated pure-function fix may skip full runtime verification when its acceptance criteria are fully covered by faster checks. In that case the Local Runtime gate must be reported as `NOT REQUIRED` with an explicit reason.

## Fast checks

Prefer task-relevant checks rather than running every heavy tool after every edit:

- `git diff --check`;
- PHP syntax check for changed PHP files or the full plugin when appropriate;
- existing PHPUnit tests;
- JavaScript syntax checks when JS changes;
- `scripts/local-verify.ps1` for the standard local verification chain;
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

- current finding / implemented result;
- files changed;
- checks actually executed and their results;
- local Z-Blog/runtime checks actually executed when required;
- Git branch/commit/CI state when applicable;
- Release Gate result;
- Notion context/writeback evidence for a complete-flow task;
- knowledge/state writeback evidence for non-trivial tasks;
- remaining limitations or external blockers;
- the mandatory six-gate block when complete flow was requested.

Never replace missing execution evidence with planned commands or expected results.
