# xz_visit_stats Codex workspace rules

## Purpose

This repository is intended to be developed from a Codex workspace that can directly access the real Git working tree and a terminal. Codex is the execution environment for normal development; a separate local Runner is not part of the primary development path.

The desired workflow is:

```text
User requirement
→ ChatGPT requirements / PRD / acceptance criteria
→ Codex opens the real project workspace
→ reads this file and current repository state
→ edits the real source tree
→ runs local automated checks
→ runs local Z-Blog runtime verification when required
→ fixes failures and re-tests
→ Git commit / push / CI
→ release documentation and release gates
```

Routine development must not require the user to copy commands, edit task files, open a second Codex window, or confirm each normal code/test operation.

## Project baseline

- Project: Z-BlogPHP plugin `xz_visit_stats`.
- Repository: `mxonline/zblogplugin-xz_visit_stats`.
- Current formal baseline is the version recorded by `plugin.xml`, `docs/VERSION.md`, Git tags/releases and the current branch. Never trust a stale version written in this file over the real repository state.
- Before starting a task, inspect `git status`, current branch, `plugin.xml`, relevant PRD/version documents and the affected code.
- A 2.0 task is a major-version task and requires local Z-Blog runtime verification before it can be called complete.

## Expected local development environment

The current Windows test environment is expected to expose the plugin inside a real Z-Blog installation. Typical paths are:

```text
Z-Blog root:   D:\wwwroot\xinzhao_net
Plugin root:   D:\wwwroot\xinzhao_net\zb_users\plugin\xz_visit_stats
Local site:    http://127.0.0.1
PHP CLI:       D:\BtSoft\php\83\php.exe
```

Do not blindly hard-code these values into runtime plugin code. Local scripts may use them as defaults but must allow overrides. If the real workspace differs, detect the actual paths before testing.

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
2. Determine the affected Hook, database, configuration and compatibility surface.
3. Make the smallest coherent implementation.
4. Run fast local checks.
5. If the change depends on Z-Blog runtime behavior, run the real local-runtime checks described in `docs/TESTING.md`.
6. On failure, read the actual error/output, fix the cause and re-run the relevant checks.
7. Inspect the final diff for unrelated edits, generated junk and secrets.
8. Update documentation/version metadata only when the task or release state requires it.
9. Commit/push the development branch when the requested workflow includes Git delivery.
10. Report only evidence-backed status.

## When local Z-Blog runtime verification is mandatory

Runtime verification is a release blocker for changes involving any of the following:

- major-version work such as v2.0;
- plugin install, enable, disable, uninstall or upgrade behavior;
- database schema, indexes, migrations or stored configuration;
- Z-Blog Hooks and request lifecycle behavior;
- request collection, IP, Referer, UA, bot/spider or HTTP status capture;
- admin pages, AJAX/runtime endpoints or permissions;
- Nginx / PHP FastCGI dependent behavior;
- performance-sensitive collector/statistics queries;
- any behavior that CI unit tests cannot faithfully represent.

A documentation-only change or a small isolated pure-function fix may skip full runtime verification when its acceptance criteria are fully covered by faster checks.

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

## Documentation style

README, CHANGELOG, VERSION and Release Notes must describe the real implemented and verified state in a natural maintainer voice. Do not use generic AI-style claims such as “全面优化”“显著提升稳定性” when the code evidence does not justify them. Unverified items must remain marked as unverified.

## Completion report

Every completed task report must contain:

- current finding / implemented result;
- files changed;
- checks actually executed and their results;
- local Z-Blog/runtime checks actually executed when required;
- Git branch/commit/CI state when applicable;
- remaining limitations or external blockers.

Never replace missing execution evidence with planned commands or expected results.
