# GPT-Codex Autonomous Development Bridge v2.1 Amendment

Date: 2026-08-30
Status: APPROVED
Applies to: `xz_visit_stats` Bridge v1 design and future Z-Blog autonomous development standard
Base process: Z-Blog plugin complete development flow v2.0

## Purpose

This amendment makes the GPT-Codex Bridge a safer long-term autonomous execution layer without replacing the existing Z-Blog plugin development process v2.0.

The invariant is:

```text
v2.0 = engineering method, gates, runtime verification, release rules
v2.1 = v2.0 + autonomous execution/control layer
```

If the v2.1 Bridge is unavailable or unstable, the system must be able to fall back to the complete v2.0 process without losing verified project state or weakening any gate.

## 1. Requirement Gate

Every non-trivial autonomous development run must pass `REQUIREMENT_GATE` before Codex receives a mutation task.

The GPT controller must produce and validate a requirement envelope containing at least:

- business goal;
- user-visible outcome;
- in-scope behavior;
- explicitly out-of-scope behavior;
- compatibility target;
- data/schema impact;
- security/privacy impact;
- runtime verification need;
- acceptance criteria;
- release target or stop condition;
- Reuse Gate requirement and decision when applicable.

The controller should infer ordinary technical details from the repository and project rules rather than interrupting the user. Human clarification is required only when two materially different business outcomes are both plausible and cannot be safely resolved from current evidence.

Possible Requirement Gate results:

- `PASS`
- `NEEDS_REUSE_GATE`
- `BLOCKED_BUSINESS_DECISION`
- `BLOCKED_SAFETY_SCOPE`

A missing implementation detail that GPT/Codex can safely determine is not a user blocker.

## 2. Evidence Ledger

Bridge state is not sufficient proof of completion. Every gate PASS must have a durable, sanitized evidence record.

Add a machine-readable Evidence Ledger under:

```text
bridge/evidence/
```

Each evidence item must include at least:

```json
{
  "schema_version": "1.0",
  "evidence_id": "EV-...",
  "request_id": "BRIDGE-...",
  "gate": "LOCAL_RUNTIME",
  "stage": "T4_ANALYTICS_ADMIN",
  "status": "PASS",
  "branch": "feature/visit-stats-4.0",
  "head_sha": "...",
  "environment": {},
  "commands": [],
  "checks": [],
  "artifact_refs": [],
  "timestamp": "..."
}
```

Rules:

- A PASS without corresponding evidence is invalid.
- Evidence must be tied to the exact relevant SHA where applicable.
- A new repair commit invalidates downstream evidence tied to an older SHA unless the gate is explicitly SHA-independent.
- Secrets, credentials, cookies, private visitor-level data and raw authorization headers must never be stored.
- Evidence files are append-only from the controller's perspective; corrections create a new evidence item and supersede the old ID rather than silently rewriting history.
- `bridge/state.json` may reference evidence IDs but may not substitute for them.

Authority remains real Git/runtime/CI first; the Evidence Ledger records observed proof, it does not override the underlying source.

## 3. Lane Routing

Every task must be classified before execution so small changes do not pay the cost of a major-version release flow, while runtime-sensitive changes cannot escape required gates.

Supported initial lanes:

- `DOC_ONLY`
- `FAST_FIX`
- `NORMAL_FEATURE`
- `RUNTIME_FEATURE`
- `SCHEMA_CHANGE`
- `MAJOR_VERSION`
- `RELEASE`

Minimum routing rules:

### DOC_ONLY

Documentation-only changes with no runtime/code behavior impact.

Required: diff/format/secret checks; CI only if repository policy requires it.

### FAST_FIX

Small isolated code fix with no schema, Hook lifecycle, admin endpoint or environment dependency.

Required: focused tests, syntax/static checks, relevant CI.

### NORMAL_FEATURE

Normal feature that is adequately represented by automated tests but does not require real Z-Blog runtime evidence under `AGENTS.md`.

Required: focused/full relevant tests and CI.

### RUNTIME_FEATURE

Changes involving admin UI/runtime endpoint/Z-Blog Hooks/request lifecycle/collector behavior or any behavior CI cannot faithfully represent.

Required: automated tests + local Z-Blog runtime + CI.

### SCHEMA_CHANGE

Database/index/migration/config persistence changes.

Required: backup + migration/data-preservation checks + local runtime + SQL/EXPLAIN when relevant + CI + rollback assessment.

### MAJOR_VERSION

Major release development such as current v4.

Required: full v2.0/v2.1 flow, runtime, SQL/performance as applicable, exact-SHA CI, release preparation and rollback gate.

### RELEASE

Release candidate/final release operations.

Required: final runtime, exact-SHA CI, Release Gate, Rollback Gate, artifact verification, tag/release verification, Notion/state writeback.

Lane selection is a GPT technical decision backed by repository evidence. If uncertain between two lanes, choose the stricter lane until evidence justifies lowering it.

## 4. Rollback Gate

A release is not ready merely because the new version passes. Before release, the Bridge must evaluate `ROLLBACK_GATE`.

The gate must verify, as applicable:

- previous known-good tag/release is identifiable;
- previous formal ZIP/artifact is available or reproducible;
- plugin/config backup exists before destructive or migration-sensitive deployment;
- database migration rollback/forward-compatibility strategy is documented and tested to the level required by risk;
- downgrade behavior is classified as `SAFE`, `FORWARD_ONLY`, or `BLOCKED`;
- rollback does not require deleting verified historical data unless explicitly authorized;
- recovery steps are executable from the actual environment;
- release artifact and rollback artifact checksums/identifiers are recorded.

Possible results:

- `PASS`
- `PASS_FORWARD_ONLY` — release may proceed only when the release documentation clearly states that database/schema downgrade is not supported and a forward-fix/backup recovery path is verified;
- `NOT_REQUIRED` — only for changes whose release mechanics cannot alter runtime/data state, with explicit evidence;
- `BLOCKED`

For schema/migration and major-version releases, `NOT_REQUIRED` is invalid.

Final release ordering:

```text
Final Runtime
→ exact-SHA CI
→ Release Gate
→ Rollback Gate
→ artifact/tag/GitHub Release
→ Notion + Project State writeback
→ RELEASED
```

## 5. v2.0 Fallback / Degraded Mode

Bridge adoption must never make the project impossible to develop when the controller is unavailable.

Define two execution modes:

- `AUTONOMOUS` — normal v2.1 mode; GPT↔Bridge↔Codex automatic loop.
- `DEGRADED_V2_FALLBACK` — complete v2.0 direct Codex workflow used only when Bridge control-plane failure prevents safe autonomous continuation.

Fallback rules:

- Preserve the current Git branch, HEAD, runtime evidence, Evidence Ledger and project state.
- Do not reset to an older version merely because Bridge failed.
- Do not re-run VERIFIED phases unless their evidence was invalidated by later changes.
- Direct Codex execution in degraded mode must still obey AGENTS, Reuse Gate, local-runtime rules, exact-SHA CI, Release Gate, Rollback Gate and Notion/state writeback.
- Manual GPT↔Codex copy/paste is not the preferred fallback. Where possible, direct Codex should execute an already-materialized task/plan from repository state.
- Every degraded-mode run must record why Bridge was unavailable and the exact resume point for returning to autonomous mode.
- Returning to AUTONOMOUS mode requires Resume Gate reconciliation against real Git/runtime/CI evidence.

## 6. Updated autonomous flow

The normative full flow becomes:

```text
User business goal
→ Resume Gate
→ Requirement Gate
→ GitHub/official ecosystem search when required
→ Reuse Gate: USE / REUSE / FORK / BUILD
→ PRD / architecture / acceptance criteria
→ Lane Routing
→ Bridge dispatch
→ Codex real execution
→ focused automated tests
→ required local Z-Blog runtime
→ required SQL/EXPLAIN/data checks
→ Evidence Ledger write
→ GPT review
   ├─ REPAIR → Codex repair → affected gates rerun
   ├─ RETRY_INFRA → bounded infrastructure retry
   ├─ NEXT_STAGE → continue
   └─ BLOCKED → safe persisted handoff
→ Git commit/push
→ exact-SHA GitHub CI
→ security/performance/compatibility checks by risk
→ version/changelog/release notes
→ final runtime verification
→ Release Gate
→ Rollback Gate
→ formal artifact + tag + GitHub Release
→ Notion writeback/read-back
→ PROJECT-STATE / reusable knowledge writeback
→ six-gate report + rollback evidence
→ RELEASED
```

## 7. Activation rule

The following policy must not become authoritative merely because documentation exists:

```text
AUTONOMOUS_EXECUTION = REQUIRED
MANUAL_GPT_CODEX_COPY_PASTE = FORBIDDEN
```

It becomes the normal development rule only after the Bridge has demonstrated:

- Requirement Gate PASS on fixtures and a real read-only context run;
- Evidence Ledger integrity and secret-redaction tests;
- Lane Router tests covering all initial lanes;
- Rollback Gate tests and at least one safe live release-readiness evaluation;
- v2.0 fallback/resume test;
- App Server round trip and crash resume;
- GPT automatic repair loop;
- local T4 takeover without re-running T2/T3;
- exact-SHA CI;
- direct Notion writeback/read-back.

## 8. Compatibility with current T4

This amendment does not change the current T4 product scope or reopen T2/T3.

For `xz_visit_stats v4.0`:

- Resume target remains T4.
- Lane is `MAJOR_VERSION`.
- Existing `.codex-tasks/08-v4-t4-analytics-admin.md` remains the execution specification for T4.
- T4 still requires UNIT_TEST → LOCAL_RUNTIME → SQL_EXPLAIN → exact-SHA GITHUB_CI.
- T4 completion may legitimately leave Release Gate `NOT READY` while downstream release stages remain.
- Release automation must later pass both Release Gate and Rollback Gate before `RELEASED`.

## 9. Acceptance additions

Bridge v2.1 activation additionally requires:

1. Routine user interaction is limited to business goal/true business ambiguity or hard safety/credential blockers.
2. Every required PASS gate is backed by an Evidence Ledger record.
3. Lane selection is deterministic from task attributes and can be escalated safely.
4. A major-version release cannot bypass Rollback Gate.
5. Bridge failure can enter `DEGRADED_V2_FALLBACK` without losing verified state or weakening v2.0 gates.
6. Returning from fallback to autonomous mode is proven through Resume Gate reconciliation.
