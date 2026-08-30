# GPT-Codex Autonomous Bridge Zero-Touch Acceptance Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Prove that one user business goal can drive the complete Z-Blog v2.0/v2.1 development and release flow without any further Codex UI operation or manual GPT↔Codex relay.

**Spec:** `docs/superpowers/specs/2026-08-30-gpt-codex-autonomous-bridge-zero-touch-amendment.md`

## Global Constraints

- The user may submit one business goal at run start.
- No user Codex UI action is allowed after run start.
- No manual GPT↔Codex message relay is allowed.
- No routine stage confirmation is allowed.
- App Server is primary; `codex exec` fallback must also be programmatic.
- A real successful run must end with verified formal release evidence, not merely code/test completion.
- BLOCKED may occur only for the explicit safety/access conditions in the spec and must never instruct the user to continue in Codex UI.

---

### Task 21: Enforce zero-touch executor policy in code

**Files:**
- Modify: `bridge/config.example.json`
- Modify: `bridge/lib/Bridge.AppServer.psm1`
- Modify: `bridge/lib/Bridge.Fallback.psm1`
- Modify: `bridge/lib/Bridge.Orchestrator.psm1`
- Modify: `bridge/prompts/gpt-controller.md`
- Modify: `bridge/prompts/codex-executor.md`
- Create: `tests/bridge/Test-ZeroTouchPolicy.ps1`

- [ ] Write failing tests that reject any controller/fallback result whose next action contains manual Codex UI, copy/paste, Continue/Approve, or user-run-command instructions.
- [ ] Add config policy flags:

```json
{
  "zero_touch": {
    "required": true,
    "user_input_count": 1,
    "allow_codex_ui": false,
    "allow_manual_gpt_codex_relay": false,
    "allow_routine_user_confirmation": false
  }
}
```

- [ ] Make App Server approval handling auto-approve only routine reversible actions already allowed by project policy; unsafe requests transition to BLOCKED.
- [ ] Make `codex exec` fallback callable only by Bridge and return the normal structured result contract.
- [ ] Add orchestrator validation that rejects any normal-stage decision requiring user relay/UI interaction.
- [ ] Run `Test-ZeroTouchPolicy.ps1` and commit.

---

### Task 22: Add unattended continuation and process supervision

**Files:**
- Modify: `bridge/lib/Bridge.Orchestrator.psm1`
- Modify: `bridge/lib/Bridge.State.psm1`
- Modify: `scripts/gpt-codex-bridge.ps1`
- Modify: `scripts/install-bridge-task.ps1`
- Create: `tests/bridge/Test-UnattendedResume.ps1`

- [ ] Test automatic continuation across Codex turn completion, CI wait, infrastructure retry, process restart and Windows task resume.
- [ ] Ensure every non-terminal state has a machine-executable `next_action`; no state may encode `WAIT_FOR_USER_NEXT` or equivalent.
- [ ] Persist state before external mutation and after terminal evidence collection.
- [ ] On restart, reconcile Git/runtime/CI and continue automatically from the earliest unfinished legitimate stage.
- [ ] Run crash/restart fixtures and commit.

---

### Task 23: Add release-to-completion invariant

**Files:**
- Modify: `bridge/lib/Bridge.Orchestrator.psm1`
- Modify: `bridge/lib/Bridge.Gates.psm1`
- Modify: `bridge/schemas/state.schema.json`
- Modify: `bridge/schemas/result.schema.json`
- Create: `tests/bridge/Test-ReleaseCompletionInvariant.ps1`

- [ ] Test that `COMPLETE` is impossible while release target is formal publish and any of these are missing: Release Gate PASS, Rollback Gate PASS/PASS_FORWARD_ONLY, release SHA CI PASS, final required runtime PASS, formal ZIP, correct tag, GitHub Release artifact, Notion writeback/read-back, project-state writeback.
- [ ] Ensure intermediate milestones return `IN_PROGRESS` or next stage rather than user-facing completion.
- [ ] Ensure current T4 may finish with Release Gate NOT READY but orchestrator automatically resolves the next defined downstream stage instead of stopping for the user.
- [ ] Run tests and commit.

---

### Task 24: Real Zero-Touch end-to-end acceptance run

**Files:**
- Runtime-generated sanitized evidence only.
- Update after PASS: `AGENTS.md`, `knowledge/PROJECT-STATE.md`, `README-AUTOMATION.md`, `docs/ZBLOG-AUTONOMOUS-DEVELOPMENT-v2.1.md`.

- [ ] Complete one-time preflight before the acceptance run: OpenAI auth, Codex App Server, GitHub auth, Notion auth/target, local Z-Blog runtime, MySQL and repository/worktree safety.
- [ ] Start the acceptance run with exactly one user business goal.
- [ ] Record `user_input_count = 1` at run start and fail acceptance if it increments for a normal-stage prompt.
- [ ] Prove all GPT↔Codex exchanges occur through Bridge transports.
- [ ] Prove no Codex UI dependency via event/audit records.
- [ ] Prove required real local Z-Blog and SQL/EXPLAIN gates execute.
- [ ] Prove exact-SHA GitHub CI and automatic failure handling.
- [ ] Continue through all legitimate downstream stages until Release Gate and Rollback Gate pass.
- [ ] Verify formal ZIP checksum/content, tag target and GitHub Release attachment.
- [ ] Verify Notion writeback/read-back and PROJECT-STATE update.
- [ ] Emit final six-gate report with `RELEASE: RELEASED`.
- [ ] If any manual Codex UI action, manual GPT↔Codex relay or normal-stage user confirmation occurs, mark Zero-Touch acceptance FAIL and do not activate autonomous default.

---

### Task 25: Activate zero-touch policy as repository norm

**Files:**
- Modify: `AGENTS.md`
- Modify: `README-AUTOMATION.md`
- Modify: `knowledge/PROJECT-STATE.md`
- Modify: `docs/ZBLOG-AUTONOMOUS-DEVELOPMENT-v2.1.md`

- [ ] Require PASS evidence from Tasks 1–24, including the real end-to-end Zero-Touch acceptance run.
- [ ] Add normative rules:

```text
AUTONOMOUS_EXECUTION = REQUIRED
USER_INPUT_COUNT = 1
MANUAL_GPT_CODEX_COPY_PASTE = FORBIDDEN
CODEX_UI_DEPENDENCY = FORBIDDEN
CHATGPT_UI_RELAY_DEPENDENCY = FORBIDDEN
NORMAL_USER_CONFIRMATION = FORBIDDEN
BASE_ENGINEERING_FLOW = ZBLOG_V2_COMPLETE
```

- [ ] Document that direct/manual Codex UI is not an operational fallback. Programmatic v2.0 fallback is the only degraded execution mode.
- [ ] Record activation commit/SHA, CI, runtime, release and Zero-Touch evidence IDs in PROJECT-STATE.
- [ ] Require final exact-SHA CI PASS for the activation commit.
