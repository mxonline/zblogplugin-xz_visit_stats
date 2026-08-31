# GPT-Codex Autonomous Development Bridge v2.2 Extension Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans. Execute this only after Bridge v1/v2.1 plans are available; v2.2 is additive.

**Goal:** Guarantee that one user business goal drives an uninterrupted programmatic GPT↔Codex loop through the complete existing Z-Blog development flow and real release, with no dependency on Codex UI or manual result relay.

**Architecture:** Extend the Bridge orchestrator with a continuous redispatch invariant, Approval Proxy, Watchdog, executor-handoff detector, Release-First admission gates and candidate/artifact identity checks. A Codex turn is an execution unit only; its terminal event always returns control to Bridge→GPT, never to the user.

**Specs:**
- `docs/ZBLOG-AUTONOMOUS-DEVELOPMENT-v2.2.md`
- `docs/superpowers/specs/2026-08-31-gpt-codex-autonomous-bridge-v2.2-release-first-amendment.md`

## Global Constraints

- Preserve complete v2.0 and v2.1 behavior.
- Current `xz_visit_stats` T2/T3 remain VERIFIED/locked; current resume target remains T4 unless higher-trust live evidence changes it.
- `PLUGIN_RELEASED` is the only success terminal state.
- A Codex turn terminal event may not set whole-run COMPLETE.
- No code path may instruct the user to open/continue/approve in Codex UI.
- Programmatic App Server is primary transport; programmatic `codex exec` is fallback.
- Routine reversible approvals are handled by Bridge Approval Proxy.
- All automatic release decisions remain constrained by Release Gate, Rollback Gate, Expected Diff and project safety rules.

---

### Task 26: Enforce continuous redispatch invariant

**Files:**
- Modify: `bridge/lib/Bridge.Orchestrator.psm1`
- Modify: `bridge/lib/Bridge.State.psm1`
- Modify: `bridge/schemas/state.schema.json`
- Modify: `bridge/schemas/result.schema.json`
- Create: `tests/bridge/Test-ContinuousLoop.ps1`

**Interfaces:**
- Add lifecycle state `CODEX_TURN_COMPLETED`.
- Whole-run success state `PLUGIN_RELEASED`.
- `Move-BridgeState` must reject `CODEX_TURN_COMPLETED -> COMPLETE/PLUGIN_RELEASED`.

- [ ] Write failing tests proving a terminal Codex turn must transition to `RESULT_COLLECT` then `GPT_REVIEW`.
- [ ] Test two sequential Codex turns execute with no external input.
- [ ] Test `TEST_PASS`, `CI_PASS`, `T4_COMPLETE`, `RELEASE_READY` are non-terminal.
- [ ] Implement the state invariant.
- [ ] Run `Test-ContinuousLoop.ps1` and all existing state/orchestrator tests.
- [ ] Commit: `feat(bridge): enforce continuous GPT Codex loop`.

---

### Task 27: Add Executor Handoff Violation detector

**Files:**
- Create: `bridge/lib/Bridge.HandoffGuard.psm1`
- Create: `tests/bridge/Test-HandoffGuard.ps1`
- Modify: `bridge/lib/Bridge.Orchestrator.psm1`
- Modify: `bridge/prompts/gpt-controller.md`

**Interfaces:**
- `Test-ExecutorHandoffViolation -Text <string>` returns violation types.
- `Get-HandoffRepairDecision` sends violation evidence to GPT and returns executable redispatch.

- [ ] Write fixtures containing “请在 Codex UI 点击继续”, “把结果发给 GPT”, “请手动执行命令”, and ordinary harmless status text.
- [ ] Verify only responsibility-transfer output triggers violation.
- [ ] On violation, persist evidence, call GPT, redispatch automatically.
- [ ] Do not expose the violating instruction as the user next action.
- [ ] Commit: `feat(bridge): prevent executor handoff to user`.

---

### Task 28: Implement Approval Proxy and no-UI authorization path

**Files:**
- Create: `bridge/lib/Bridge.Approval.psm1`
- Create: `tests/bridge/Test-Approval.ps1`
- Modify: `bridge/lib/Bridge.AppServer.psm1`
- Modify: `bridge/lib/Bridge.Orchestrator.psm1`

**Interfaces:**
- `Resolve-BridgeApproval -Request <object> -Context <object>` returns `APPROVE|DENY_BLOCKED` plus policy evidence.

- [ ] Write tests for reversible code edits/tests/local runtime/Git push to dev branch -> APPROVE.
- [ ] Write tests for force-push/production destructive operation/`zb_system` modification -> DENY_BLOCKED.
- [ ] Connect App Server server-initiated approval requests to Approval Proxy.
- [ ] Assert no approval request is converted into a Codex-UI user action.
- [ ] Commit: `feat(bridge): add programmatic approval proxy`.

---

### Task 29: Add Watchdog, heartbeat and executor recovery

**Files:**
- Create: `bridge/lib/Bridge.Watchdog.psm1`
- Create: `tests/bridge/Test-Watchdog.ps1`
- Modify: `bridge/lib/Bridge.AppServer.psm1`
- Modify: `bridge/lib/Bridge.Orchestrator.psm1`
- Modify: `bridge/schemas/state.schema.json`

**Interfaces:**
- Watchdog statuses: `HEALTHY|IDLE_WAIT|STALL_SUSPECTED|PROCESS_LOST|RETRYABLE_INFRA|BLOCKED`.
- Track `last_event_at`, `last_progress_at`, `turn_started_at`, retries/restarts.

- [ ] Simulate normal long-running turn with heartbeat -> no false restart.
- [ ] Simulate terminal turn -> immediate GPT review, no wait for user.
- [ ] Simulate App Server crash -> reconnect/restart and resume thread where possible.
- [ ] Simulate unrecoverable App Server -> programmatic `codex exec` fallback with generated handoff.
- [ ] If both transports unavailable, persist `BLOCKED_EXECUTOR_TRANSPORT`; do not instruct user to open Codex UI.
- [ ] Commit: `feat(bridge): add executor watchdog and recovery`.

---

### Task 30: Add Release-First impact/baseline/diff gates

**Files:**
- Create: `bridge/lib/Bridge.Impact.psm1`
- Create: `bridge/lib/Bridge.Baseline.psm1`
- Create: `bridge/lib/Bridge.Diff.psm1`
- Create: `tests/bridge/Test-ImpactGates.ps1`
- Modify: `bridge/lib/Bridge.Orchestrator.psm1`
- Modify: `bridge/schemas/requirement.schema.json`

**Interfaces:**
- `Get-ChangeImpact`
- `Get-BaselineInheritance`
- `Get-ExpectedDiff`
- `Test-ExpectedDiff`

- [ ] Test current T4 preserves T2/T3 unless actual changed surface proves otherwise.
- [ ] Test Expected Diff rejects unrelated plugin/core edits.
- [ ] Test planned additional file can be accepted only through GPT review with explicit reason.
- [ ] Orchestrator admission order becomes Resume→Requirement→Reuse→Impact→Baseline→ExpectedDiff→Lane→Dispatch.
- [ ] Commit: `feat(bridge): add release-first change gates`.

---

### Task 31: Add Candidate Manifest and artifact identity gate

**Files:**
- Create: `bridge/schemas/candidate.schema.json`
- Create: `bridge/lib/Bridge.Candidate.psm1`
- Create: `bridge/lib/Bridge.Artifact.psm1`
- Create: `tests/bridge/Test-Candidate.ps1`
- Modify: `bridge/lib/Bridge.Gates.psm1`

**Interfaces:**
- `New-CandidateManifest`
- `Test-CandidateIdentity`
- `Test-VersionConsistency`
- `Test-ReleaseArtifactHash`

- [ ] Candidate binds branch, exact commit SHA, version, changed files, schema version, required gates and SHA256.
- [ ] Runtime and CI evidence must reference candidate SHA.
- [ ] Tag must point to candidate/release commit.
- [ ] Candidate ZIP SHA256 must equal GitHub Release artifact SHA256.
- [ ] plugin.xml/docs VERSION/CHANGELOG/tag/manifest/release version must agree.
- [ ] Commit: `feat(bridge): add candidate and artifact identity gates`.

---

### Task 32: Real single-input continuous-loop acceptance

**Files:**
- Update after evidence: `knowledge/PROJECT-STATE.md`
- Update after PASS only: `AGENTS.md`
- Update: `docs/ZBLOG-AUTONOMOUS-DEVELOPMENT-v2.2.md`

- [ ] User submits exactly one business goal for the acceptance run.
- [ ] Record `user_input_count=1` in run metadata.
- [ ] Execute at least two real Codex turns automatically.
- [ ] Verify first Codex terminal event goes Bridge→GPT→Bridge→Codex without user action.
- [ ] Exercise handoff guard with a synthetic fixture and prove self-redispatch.
- [ ] Interrupt App Server and prove Watchdog programmatic recovery.
- [ ] Resume current real project state; do not repeat T2/T3.
- [ ] Complete required real local Z-Blog runtime/DB/SQL/EXPLAIN checks for the active lane.
- [ ] Complete exact-SHA GitHub CI.
- [ ] Build/verify Candidate ZIP/Manifest/SHA256.
- [ ] Pass Release Gate and Rollback Gate.
- [ ] Create and verify real Tag + GitHub Release + artifact hash.
- [ ] Complete Notion and PROJECT-STATE read-back/writeback.
- [ ] Final state must equal `PLUGIN_RELEASED`.
- [ ] Any Codex UI/manual relay/manual next-step dependency makes acceptance FAIL.

Only after all steps PASS may `AGENTS.md` activate:

```text
AUTONOMOUS_EXECUTION = REQUIRED
SUCCESS_TERMINAL_STATE = PLUGIN_RELEASED
CODEX_UI_DEPENDENCY = FORBIDDEN
MANUAL_GPT_CODEX_COPY_PASTE = FORBIDDEN
MANUAL_NEXT_STEP = FORBIDDEN
CODEX_TURN_TERMINAL_IS_INTERMEDIATE = TRUE
```

---

## Self-review

- Continuous result→GPT→Codex redispatch: Task 26.
- No Codex-to-user handoff: Task 27.
- No Codex UI approvals: Task 28.
- Mid-run stall/crash recovery: Task 29.
- Latest firmware-inspired Release-First gates: Task 30.
- Candidate/release identity: Task 31.
- Real zero-touch release proof before activation: Task 32.
- v2.0/v2.1 remain intact and authoritative for their existing engineering/safety requirements.
