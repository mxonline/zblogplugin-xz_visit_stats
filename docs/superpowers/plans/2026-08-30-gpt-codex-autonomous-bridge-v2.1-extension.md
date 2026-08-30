# GPT-Codex Autonomous Development Bridge v2.1 Extension Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the approved Bridge implementation so Requirement Gate, Evidence Ledger, Lane Routing, Rollback Gate and complete v2.0 fallback are verified before autonomous execution becomes the default Z-Blog development mode.

**Architecture:** Keep the existing Bridge v1 plan intact and add five focused control-plane capabilities before activation. These capabilities do not replace existing T4 tasks or v2.0 engineering gates; they govern how work is admitted, routed, proven, released and safely degraded.

**Tech Stack:** Existing Bridge PowerShell modules, JSON schemas, Codex App Server, OpenAI Responses API, Git/GitHub, local Z-Blog/MySQL verification, Notion REST adapter.

**Specs:**
- `docs/superpowers/specs/2026-08-30-gpt-codex-autonomous-bridge-design.md`
- `docs/superpowers/specs/2026-08-30-gpt-codex-autonomous-bridge-notion-amendment.md`
- `docs/superpowers/specs/2026-08-30-gpt-codex-autonomous-bridge-v2.1-amendment.md`
- `docs/ZBLOG-AUTONOMOUS-DEVELOPMENT-v2.1.md`

## Global Constraints

- Base v2.0 engineering process remains mandatory and complete.
- v2.1 is additive; a Bridge failure must not weaken or delete v2.0 behavior.
- Preserve verified T2/T3; current `xz_visit_stats` resume target remains T4.
- Current T4 lane is `MAJOR_VERSION`.
- All PASS gates require durable sanitized evidence.
- Rollback Gate is mandatory for major-version/schema/release flows.
- Autonomous default policy is activated only after all extension acceptance tests pass in the real environment.

---

### Task 15: Implement Requirement Gate

**Files:**
- Create: `bridge/schemas/requirement.schema.json`
- Create: `bridge/lib/Bridge.Requirements.psm1`
- Create: `tests/bridge/Test-Requirements.ps1`
- Modify: `bridge/prompts/gpt-controller.md`
- Modify: `bridge/lib/Bridge.Orchestrator.psm1`

**Interfaces:**
- Produces: `Get-RequirementEnvelope`, `Test-RequirementGate`.
- Gate results: `PASS|NEEDS_REUSE_GATE|BLOCKED_BUSINESS_DECISION|BLOCKED_SAFETY_SCOPE`.

- [ ] **Step 1: Write failing contract tests**

Test that a non-trivial requirement envelope contains:

```text
business_goal
user_outcome
in_scope
out_of_scope
compatibility
schema_impact
security_privacy_impact
acceptance_criteria
release_target
reuse_gate
```

Test that missing safe-to-infer technical detail does not create a business blocker, while two incompatible user-visible outcomes do.

- [ ] **Step 2: Run tests and verify failure**

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tests/bridge/Test-Requirements.ps1
```

- [ ] **Step 3: Implement GPT-driven requirement envelope generation**

Use repository/project context plus the user's business goal. Do not ask the user about technical details that can be resolved from project rules and current evidence.

- [ ] **Step 4: Integrate Requirement Gate before mutation dispatch**

The orchestrator order becomes:

```text
RESUME_GATE → REQUIREMENT_GATE → REUSE_GATE(if required) → LANE_ROUTE → TASK_DISPATCH
```

- [ ] **Step 5: Run fixture tests and one real read-only T4 requirement classification**

Expected current T4 result: requirement context resolves without reopening T2/T3 and remains eligible for `MAJOR_VERSION` execution.

- [ ] **Step 6: Commit**

```bash
git add bridge/schemas/requirement.schema.json bridge/lib/Bridge.Requirements.psm1 bridge/prompts/gpt-controller.md bridge/lib/Bridge.Orchestrator.psm1 tests/bridge/Test-Requirements.ps1
git commit -m "feat(bridge): add requirement gate"
```

---

### Task 16: Implement Evidence Ledger

**Files:**
- Create: `bridge/schemas/evidence.schema.json`
- Create: `bridge/lib/Bridge.Evidence.psm1`
- Create: `bridge/evidence/.gitkeep`
- Create: `tests/bridge/Test-Evidence.ps1`
- Modify: `bridge/lib/Bridge.Gates.psm1`
- Modify: `.gitignore`

**Interfaces:**
- Produces: `Write-BridgeEvidence`, `Get-BridgeEvidence`, `Test-GateEvidence`, `Invalidate-ShaDependentEvidence`.

- [ ] **Step 1: Write failing evidence tests**

Test:

- PASS without evidence is rejected;
- evidence includes request/gate/stage/branch/SHA/timestamp;
- new SHA invalidates old SHA-bound CI/runtime evidence where required;
- token/cookie patterns never appear;
- evidence correction creates a superseding record rather than overwriting the old record.

- [ ] **Step 2: Run and verify failure**

- [ ] **Step 3: Implement append-oriented sanitized records**

Store generated run evidence under ignored runtime storage and commit only schemas/fixtures. Allow sanitized durable summaries to be retained according to repository policy.

- [ ] **Step 4: Integrate every Bridge gate**

`Invoke-UnitGate`, `Invoke-LocalRuntimeGate`, `Invoke-SqlExplainGate`, `Wait-ExactShaCi`, `Get-ReleaseGate` and `Get-RollbackGate` must return/reference evidence IDs.

- [ ] **Step 5: Run tests and secret scan**

- [ ] **Step 6: Commit**

```bash
git add bridge/schemas/evidence.schema.json bridge/lib/Bridge.Evidence.psm1 bridge/evidence/.gitkeep bridge/lib/Bridge.Gates.psm1 tests/bridge/Test-Evidence.ps1 .gitignore
git commit -m "feat(bridge): add evidence ledger"
```

---

### Task 17: Implement deterministic Lane Router

**Files:**
- Create: `bridge/lib/Bridge.Router.psm1`
- Create: `tests/bridge/Test-Router.ps1`
- Modify: `bridge/config.example.json`
- Modify: `bridge/lib/Bridge.Orchestrator.psm1`

**Interfaces:**
- Produces: `Get-DevelopmentLane` returning `DOC_ONLY|FAST_FIX|NORMAL_FEATURE|RUNTIME_FEATURE|SCHEMA_CHANGE|MAJOR_VERSION|RELEASE` plus `required_gates` and `reason`.

- [ ] **Step 1: Write lane matrix tests**

Fixtures must prove:

- docs-only → `DOC_ONLY`;
- isolated pure function bug → `FAST_FIX`;
- normal testable feature → `NORMAL_FEATURE`;
- admin/Hook/request lifecycle → `RUNTIME_FEATURE`;
- migration/index/config persistence → `SCHEMA_CHANGE`;
- v4 current program → `MAJOR_VERSION`;
- tag/artifact/publish work → `RELEASE`.

- [ ] **Step 2: Verify tests fail before router exists**

- [ ] **Step 3: Implement conservative routing**

When task properties map to multiple lanes, choose the stricter lane. A lower lane requires positive evidence that stricter runtime/schema gates are not applicable.

- [ ] **Step 4: Map lane to required gates**

Example:

```text
MAJOR_VERSION = REQUIREMENT_GATE + REUSE_GATE(if needed) + UNIT_TEST + LOCAL_RUNTIME + SQL_EXPLAIN(as applicable) + GITHUB_CI + RELEASE_GATE + ROLLBACK_GATE(at release)
```

- [ ] **Step 5: Verify current T4 routes to MAJOR_VERSION**

- [ ] **Step 6: Commit**

```bash
git add bridge/lib/Bridge.Router.psm1 bridge/lib/Bridge.Orchestrator.psm1 bridge/config.example.json tests/bridge/Test-Router.ps1
git commit -m "feat(bridge): add development lane router"
```

---

### Task 18: Implement Rollback Gate

**Files:**
- Create: `bridge/lib/Bridge.Rollback.psm1`
- Create: `scripts/bridge-rollback-gate.ps1`
- Create: `tests/bridge/Test-Rollback.ps1`
- Modify: `bridge/lib/Bridge.Gates.psm1`
- Modify: `bridge/lib/Bridge.Orchestrator.psm1`

**Interfaces:**
- Produces: `Get-RollbackGate` returning `PASS|PASS_FORWARD_ONLY|NOT_REQUIRED|BLOCKED` with evidence ID and recovery metadata.

- [ ] **Step 1: Write failing rollback tests**

Test that:

- major-version release cannot return `NOT_REQUIRED`;
- schema migration without backup/recovery classification is `BLOCKED`;
- verified previous tag/artifact + backup/recovery path can PASS;
- irreversible schema downgrade may return `PASS_FORWARD_ONLY` only with verified backup/forward-fix recovery and explicit release documentation.

- [ ] **Step 2: Verify tests fail**

- [ ] **Step 3: Implement known-good/artifact/backup checks**

Use real repository tags/releases, formal package identifiers and local backup evidence; never infer rollback safety from prose alone.

- [ ] **Step 4: Insert gate before release mutation**

Required order:

```text
FINAL_RUNTIME → EXACT_SHA_CI → RELEASE_GATE → ROLLBACK_GATE → RELEASE
```

- [ ] **Step 5: Run tests and read-only current v4 rollback-readiness evaluation**

Do not perform a release during this evaluation.

- [ ] **Step 6: Commit**

```bash
git add bridge/lib/Bridge.Rollback.psm1 bridge/lib/Bridge.Gates.psm1 bridge/lib/Bridge.Orchestrator.psm1 scripts/bridge-rollback-gate.ps1 tests/bridge/Test-Rollback.ps1
git commit -m "feat(bridge): add rollback gate"
```

---

### Task 19: Implement and verify complete v2.0 fallback

**Files:**
- Create: `bridge/lib/Bridge.Fallback.psm1`
- Create: `tests/bridge/Test-Fallback.ps1`
- Modify: `bridge/lib/Bridge.Orchestrator.psm1`
- Modify: `docs/BRIDGE-AUTONOMOUS-DEVELOPMENT.md`
- Modify at final activation only: `AGENTS.md`

**Interfaces:**
- Produces: `Enter-V2Fallback`, `Get-V2FallbackHandoff`, `Resume-AutonomousFromFallback`.
- Modes: `AUTONOMOUS`, `DEGRADED_V2_FALLBACK`.

- [ ] **Step 1: Write fallback tests**

Simulate Bridge transport failure after a verified gate. Assert fallback:

- preserves branch/HEAD/evidence/state;
- does not call reset/clean/stash-discard;
- materializes a direct-Codex handoff from the existing task/plan;
- retains v2.0 runtime/CI/release requirements;
- records degraded reason and resume point.

- [ ] **Step 2: Write return-to-autonomous test**

Resume must reconcile Git/runtime/CI/evidence through Resume Gate and continue from the earliest legitimately unfinished stage, not restart verified stages.

- [ ] **Step 3: Implement fallback mode**

Bridge failure is not permission to weaken engineering gates. Fallback is an execution transport change only.

- [ ] **Step 4: Live safe fallback drill**

Use a synthetic/control-plane interruption, not a destructive plugin change. Demonstrate AUTONOMOUS → DEGRADED_V2_FALLBACK → AUTONOMOUS with state preserved.

- [ ] **Step 5: Commit**

```bash
git add bridge/lib/Bridge.Fallback.psm1 bridge/lib/Bridge.Orchestrator.psm1 tests/bridge/Test-Fallback.ps1 docs/BRIDGE-AUTONOMOUS-DEVELOPMENT.md
git commit -m "feat(bridge): add v2 fallback and resume"
```

---

### Task 20: v2.1 activation gate

**Files:**
- Modify: `AGENTS.md`
- Modify: `knowledge/PROJECT-STATE.md`
- Modify: `README-AUTOMATION.md`
- Modify: `docs/ZBLOG-AUTONOMOUS-DEVELOPMENT-v2.1.md`

- [ ] **Step 1: Require all Bridge v1 Tasks 1–14 plus extension Tasks 15–19 PASS**

No documentation-only shortcut may activate autonomous mode.

- [ ] **Step 2: Require real current T4 takeover evidence**

The acceptance run must prove T2/T3 are not repeated and T4 runs under `MAJOR_VERSION` lane with required runtime/SQL/CI gates.

- [ ] **Step 3: Require Requirement/Evidence/Lane/Rollback/Fallback acceptance evidence**

Every result must reference concrete evidence IDs.

- [ ] **Step 4: Activate normative policy only now**

Add to `AGENTS.md`:

```text
AUTONOMOUS_EXECUTION = REQUIRED
MANUAL_GPT_CODEX_COPY_PASTE = FORBIDDEN
BASE_ENGINEERING_FLOW = ZBLOG_V2_COMPLETE
FALLBACK_MODE = DEGRADED_V2_FALLBACK
```

State explicitly that v2.1 automates v2.0; it never replaces or weakens it.

- [ ] **Step 5: Final exact-SHA CI and project-state writeback**

Record activation commit, CI, local Bridge acceptance, current T4 state and rollback/fallback evidence.

---

## Extension Self-Review

- Requirement Gate: Task 15.
- Evidence Ledger: Task 16.
- Lane Routing: Task 17.
- Rollback Gate: Task 18.
- Complete v2.0 fallback/resume: Task 19.
- Activation only after real proof: Task 20.
- Current T4 product scope remains governed by its existing task and implementation plan.
- No extension task reopens T2/T3 or changes current plugin semantics merely to test the Bridge.
