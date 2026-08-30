# GPT-Codex Autonomous Development Bridge — Notion Integration Amendment

Date: 2026-08-30
Project: `xz_visit_stats`
Applies to: `docs/superpowers/specs/2026-08-30-gpt-codex-autonomous-bridge-design.md`

## Reason for amendment

The accepted design requires an autonomous local controller built around the OpenAI Responses API and Codex App Server. A local programmatic GPT controller cannot directly invoke the Notion connector that exists inside the ChatGPT product UI. Therefore the original Section 17 cannot, by itself, satisfy the accepted no-copy/paste, unattended completion goal.

This amendment supersedes only the Notion integration details in Section 17. All other architecture, safety, T4 resume and release-gate rules remain unchanged.

## Required v1 behavior

Bridge v1 MUST include a direct Notion REST adapter for deterministic context/writeback during autonomous runs.

Credentials and identifiers are local configuration only:

- `NOTION_TOKEN` is read from the Windows process environment or an OS secret store.
- The target Notion page/database identifiers are read from environment variables or `bridge/config.local.json`.
- `bridge/config.local.json` is git-ignored and MUST never be committed.
- Tokens, cookies, page contents containing secrets, and private connector data MUST not be written to `bridge/runs/`.

The committed `bridge/config.example.json` contains placeholders only and no real credential or private page identifier.

## Activation rule

During Bridge bootstrap, the Notion adapter may be exercised against mocks/fixtures without a live token. However `AUTONOMOUS_EXECUTION=REQUIRED` MUST NOT be activated for normal project development until the live preflight has verified:

1. OpenAI controller authentication;
2. Codex App Server availability;
3. Git/GitHub authentication required by the project;
4. local Z-Blog test-environment access when required;
5. Notion read/write access to the configured project target.

After activation, future normal development runs do not require manual Notion copy/paste.

## Notion gate semantics

- `NOTION_CONTEXT PASS` requires an actual successful read or a bridge-cached context object whose source revision was previously verified and is still accepted by the Resume Gate.
- `NOTION_WRITEBACK PASS` requires an actual successful write followed by read-back verification of the written release/stage marker.
- A transient Notion failure after development starts is `RETRYABLE_INFRA` and receives bounded retry/backoff.
- If authentication is missing/expired and cannot be refreshed automatically, the Notion gate becomes `BLOCKED`; bridge state is persisted and no work is discarded.
- A release may not be reported as a fully complete six-gate flow until Notion Writeback is PASS, consistent with `AGENTS.md`.

## Responsibility change

Replace the original statement that the ChatGPT-connected Notion layer owns autonomous writeback with this split:

- Local Bridge Controller: performs Notion REST reads/writes during autonomous execution.
- GPT Controller: decides what verified project facts should be written and supplies a schema-valid Notion handoff payload.
- ChatGPT connector: remains useful for interactive inspection or emergency/manual recovery, but is not part of the unattended runtime dependency chain.

## Security boundary

No Notion token is stored in repository files, GitHub Actions variables committed to source, prompt text, Codex task files, run logs or release artifacts. The adapter must redact authorization headers and token-shaped values before persisting diagnostic evidence.
