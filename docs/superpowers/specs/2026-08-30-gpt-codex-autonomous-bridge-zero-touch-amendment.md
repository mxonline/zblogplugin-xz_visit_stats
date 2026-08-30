# GPT-Codex Autonomous Bridge — Zero-Touch Execution Amendment

Date: 2026-08-30
Status: approved requirement amendment
Applies to: Bridge v1 + Z-Blog Autonomous Development v2.1

## Hard requirement

For every normal development run after Bridge activation, the user submits exactly one business goal. From that point until the run reaches a legitimate terminal state, the system must not depend on further user interaction with Codex or manual GPT↔Codex relay.

The normal successful terminal state is a real formal release. A development run is not complete merely because source changes, tests, local runtime or CI have passed.

## Forbidden dependencies

The production development path must not require the user to:

- open Codex UI;
- click Continue, Approve, Run or similar Codex UI controls;
- paste GPT prompts into Codex;
- paste Codex output into GPT;
- manually invoke routine development commands;
- answer normal reversible engineering confirmations;
- manually advance stages;
- act as transport between GPT, Codex, local verification, GitHub CI or release automation.

`Codex App Server` is the primary executor transport. `codex exec` may be used only as a Bridge-controlled programmatic fallback. A fallback that requires manual Codex UI operation is not an acceptable fallback.

## Zero-touch run contract

The runtime policy is:

```text
USER_INPUT_COUNT = 1
AUTONOMOUS_EXECUTION = REQUIRED
MANUAL_GPT_CODEX_COPY_PASTE = FORBIDDEN
CODEX_UI_DEPENDENCY = FORBIDDEN
CHATGPT_UI_RELAY_DEPENDENCY = FORBIDDEN
NORMAL_USER_CONFIRMATION = FORBIDDEN
```

The user business goal is expanded automatically into requirement envelope, PRD, architecture, acceptance criteria, implementation tasks and release target according to project rules and evidence.

## Required autonomous sequence

```text
single user business goal
→ Resume Gate
→ Requirement Gate
→ ecosystem search / Reuse Gate when applicable
→ PRD / architecture / acceptance criteria
→ Lane Routing
→ GPT↔Bridge↔Codex execution loop
→ tests
→ required local Z-Blog runtime
→ required SQL / EXPLAIN
→ Evidence Ledger
→ automatic diagnosis / repair / retest
→ git commit / push
→ exact-SHA GitHub CI
→ risk-driven security / performance / compatibility checks
→ version / changelog / release notes
→ final runtime verification
→ Release Gate
→ Rollback Gate
→ formal ZIP
→ tag
→ GitHub Release
→ Notion read/write verification
→ PROJECT-STATE / Knowledge writeback
→ six-gate report
→ RELEASED
```

All normal failures within this sequence remain inside the automatic repair/retry loop.

## Business ambiguity policy

Technical choices that can be resolved from repository evidence, project standards, compatibility, data safety, security, maintainability or established acceptance criteria are GPT decisions and must not be returned to the user.

If multiple technical implementations satisfy the same business goal, GPT selects the lowest-risk maintainable option and continues.

If a true unresolved business choice would materially change user-visible meaning, GPT should prefer a conservative reversible default when one exists. Only when no safe reversible interpretation exists may the run enter BLOCKED.

## BLOCKED semantics

BLOCKED is a safe terminal pause, not a request to continue in Codex UI. Allowed blockers are limited to missing/expired non-refreshable credentials, unauthorized production access, destructive irreversible risk, credible data-loss/schema conflict, unsafely entangled user work, unavoidable external human authorization, or repeated diagnosis showing continued mutation is unsafe.

When BLOCKED, Bridge persists request ID, branch, SHA, thread/controller identifiers safe to store, completed gates, evidence IDs, blocker reason and exact resume action. When the external blocker is resolved, Bridge resumes from the last trustworthy state without repeating verified work.

## One-time bootstrap exception

Initial Bridge installation may require one-time secure configuration of OpenAI, GitHub, Notion and local environment credentials. This setup is outside an individual development run and must be completed before Zero-Touch activation is declared.

After activation, the system must not rely on repeatedly asking for the same credential or on interactive Codex UI authentication as part of routine development.

## Activation acceptance

Zero-Touch mode may not become the repository default until a real acceptance run proves all of the following without user relay after the initial business goal:

1. one user requirement starts the run;
2. GPT creates/updates requirement and technical decisions automatically;
3. GPT↔Codex messages travel only through Bridge programmatic transports;
4. no Codex UI interaction occurs;
5. routine failure is automatically diagnosed, repaired and re-tested;
6. local Z-Blog runtime executes when required;
7. SQL/EXPLAIN executes when required;
8. exact-SHA CI is observed and repaired automatically if needed;
9. Release Gate and Rollback Gate are enforced;
10. formal artifact, tag and GitHub Release are verified;
11. Notion and project state are written back;
12. final state is `RELEASE: RELEASED`.

If the acceptance run asks the user to operate Codex UI, manually forward GPT/Codex messages or manually advance a normal stage, acceptance fails and autonomous mode remains unactivated.
