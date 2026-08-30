# GPT-Codex Autonomous Bridge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a resumable local GPT↔Codex App Server control plane that can continue the current v4 T4 development flow, repair failures automatically, advance through T5, and execute the existing gated release workflow without manual prompt/result copying.

**Architecture:** Keep the plugin workflow untouched and add an outer PHP CLI controller. The controller drives `codex app-server` over JSONL stdio, calls the OpenAI Responses API for strict structured control decisions, persists local atomic state, and enforces project phase/release invariants independently of model output. A thin PowerShell wrapper starts the PHP controller on Windows.

**Tech Stack:** PHP 8.3 CLI, `proc_open`, cURL, JSON, Codex App Server JSONL protocol, OpenAI Responses API Structured Outputs, PHPUnit 11, PowerShell, existing Git/GitHub/Notion release workflow.

**Spec:** `docs/superpowers/specs/2026-08-30-gpt-codex-autonomous-bridge-design.md`

## Global Constraints

- Bridge development is isolated on `feature/gpt-codex-bridge`, based on the current `feature/visit-stats-4.0` remote state.
- Do not modify T4 product code merely to make Bridge tests pass.
- T2 and T3 remain verified and locked unless real Git/runtime evidence contradicts them.
- The legacy `.codex-state.json` is never an authoritative v4 state source.
- Real Git/runtime/DB/GitHub evidence outranks persisted Bridge/Notion state.
- T4 may not Merge, Tag or Release; Release Gate remains `NOT READY` at T4 completion.
- T5 must exist and verify release readiness before any formal v4.0.0 release action.
- Production website/database deployment and Z-Blog app-center publication stay outside the automated default release scope.
- Plaintext secrets must never be written to tracked files, prompts, Bridge state, or logs.
- No new Composer runtime dependency is required for the Bridge.

---

### Task 1: Bridge contracts, config and atomic state

**Files:**
- Create: `bridge/config.example.json`
- Create: `bridge/state.example.json`
- Create: `bridge/phases/v4.0.json`
- Create: `bridge/schemas/controller-decision.schema.json`
- Create: `bridge/schemas/codex-result.schema.json`
- Create: `bridge/src/BridgeConfig.php`
- Create: `bridge/src/BridgeStateStore.php`
- Create: `tests/BridgeConfigStateTest.php`
- Modify: `.gitignore`

**Interfaces:**
- Produces: `XzVisitStats\Bridge\BridgeConfig::load(string $repoRoot): self`
- Produces: `BridgeConfig::get(string $path, mixed $default = null): mixed`
- Produces: `BridgeStateStore::load(): array`
- Produces: `BridgeStateStore::save(array $state): void`
- Produces: `BridgeStateStore::update(array $patch): array`

- [ ] **Step 1: Write failing config/state tests** that require environment overrides, reject an invalid phase manifest, preserve T2/T3 locks, and prove runtime paths are Git-ignored.

```php
public function testStateSaveIsAtomicAndPreservesVerifiedStages(): void
{
    $store = new BridgeStateStore($this->tmp . '/state.json');
    $store->save(array('verified_stages' => array('T2', 'T3'), 'current_phase' => 'T4_ANALYTICS_ADMIN'));
    $state = $store->update(array('current_stage' => 'CODEX_RUNNING'));
    $this->assertSame(array('T2', 'T3'), $state['verified_stages']);
    $this->assertSame('CODEX_RUNNING', $state['current_stage']);
    $this->assertFileDoesNotExist($this->tmp . '/state.json.tmp');
}
```

- [ ] **Step 2: Run focused tests and confirm RED** because the Bridge classes do not exist.

Run: `vendor/bin/phpunit tests/BridgeConfigStateTest.php`

- [ ] **Step 3: Implement config loading** with JSON validation, repo-root defaults, environment overrides for model/credentials, and no secret serialization.

- [ ] **Step 4: Implement atomic state storage** using same-directory temporary write + `fflush()` + rename/replace, with schema version and timestamp normalization.

- [ ] **Step 5: Add the v4 phase manifest** with `T4_ANALYTICS_ADMIN -> T5_FINAL_VERIFICATION_RELEASE_PREP -> RELEASE` and a hard `release_allowed=false` on T4/T5 until the Release Gate is PASS.

- [ ] **Step 6: Run focused tests and full PHPUnit**, then commit.

Expected commit: `feat(bridge): add config and resumable state contracts`

---

### Task 2: Codex App Server JSONL client

**Files:**
- Create: `bridge/src/CodexAppServerClient.php`
- Create: `tests/fixtures/fake-codex-app-server.php`
- Create: `tests/CodexAppServerClientTest.php`

**Interfaces:**
- Produces: `CodexAppServerClient::start(): void`
- Produces: `CodexAppServerClient::initialize(string $cwd): string` returning `threadId`
- Produces: `CodexAppServerClient::runTurn(string $threadId, string $prompt, string $cwd, string $title): array`
- Produces: `CodexAppServerClient::stop(): void`

- [ ] **Step 1: Write a fake App Server** that accepts newline-delimited `initialize`, `initialized`, `thread/start`, and `turn/start`; it returns deterministic IDs and emits `turn/completed`.

```php
if ($method === 'thread/start') {
    echo json_encode(array('id' => $msg['id'], 'result' => array('thread' => array('id' => 'thread-test')))) . PHP_EOL;
    flush();
}
```

- [ ] **Step 2: Write failing protocol tests** for startup ordering, thread ID extraction, turn completion, malformed output, timeout, subprocess exit, approval auto-response and user-input-required failure.

- [ ] **Step 3: Run focused tests and confirm RED**.

Run: `vendor/bin/phpunit tests/CodexAppServerClientTest.php`

- [ ] **Step 4: Implement `proc_open` transport** with array-form command, non-blocking stdout/stderr, line buffering, maximum protocol-line size, and separate diagnostic stderr collection.

- [ ] **Step 5: Implement startup handshake** in the exact logical order `initialize -> initialized -> thread/start` and extract `result.thread.id` while tolerating equivalent compatible nesting.

- [ ] **Step 6: Implement turn processing** until `turn/completed`, `turn/failed`, `turn/cancelled`, timeout or process exit.

- [ ] **Step 7: Implement client-request handling**: auto-approve recognized development command/file approvals; convert user-input-required to a structured blocker; return unsupported-client-request for unknown dynamic requests instead of hanging.

- [ ] **Step 8: Run focused/full tests and syntax checks**, then commit.

Expected commit: `feat(bridge): add Codex App Server transport`

---

### Task 3: OpenAI GPT controller with Structured Outputs

**Files:**
- Create: `bridge/src/OpenAIController.php`
- Create: `bridge/src/HttpTransport.php`
- Create: `bridge/prompts/gpt-controller.md`
- Create: `tests/OpenAIControllerTest.php`

**Interfaces:**
- Produces: `OpenAIController::decide(array $evidence, ?string $previousResponseId = null): array`
- Produces decision fields: `action`, `phase`, `next_prompt`, `gate`, `blocker`, `reason`, `response_id`.

- [ ] **Step 1: Write failing tests** using an injected fake HTTP transport; cover valid schema output, API error, malformed output, forbidden action, and previous-response continuation.

```php
$decision = $controller->decide(array('current_phase' => 'T4_ANALYTICS_ADMIN'));
$this->assertSame('CONTINUE_CODEX', $decision['action']);
$this->assertSame('resp_test', $decision['response_id']);
```

- [ ] **Step 2: Run focused tests and confirm RED**.

- [ ] **Step 3: Implement Responses API request creation** to `/v1/responses`, reading `OPENAI_API_KEY` only at request time and sending `text.format.type=json_schema` with the committed controller schema.

- [ ] **Step 4: Use a documented API model default** in `config.example.json` and allow `XZ_BRIDGE_GPT_MODEL` override; never hard-code a ChatGPT-only product model name.

- [ ] **Step 5: Parse `output_text` defensively** across response output items and validate the decoded controller decision against the committed action allowlist.

- [ ] **Step 6: Add bounded retry/backoff** for transport/429/5xx errors without treating quota/auth failures as normal retries.

- [ ] **Step 7: Run focused/full tests**, then commit.

Expected commit: `feat(bridge): add structured GPT controller`

---

### Task 4: Resume Gate and repair state machine

**Files:**
- Create: `bridge/src/CommandRunner.php`
- Create: `bridge/src/ResumeGate.php`
- Create: `bridge/src/BridgeOrchestrator.php`
- Create: `tests/ResumeGateTest.php`
- Create: `tests/BridgeOrchestratorTest.php`

**Interfaces:**
- Produces: `ResumeGate::inspect(): array`
- Produces: `BridgeOrchestrator::run(): array`
- Produces: `BridgeOrchestrator::runOneIteration(): array`

- [ ] **Step 1: Write failing Resume Gate tests** using fake command outputs for clean T4, dirty T4, recorded-HEAD mismatch, T2/T3 verified locks, and stale legacy `.codex-state.json`.

- [ ] **Step 2: Write failing orchestrator tests** for PASS, REPAIR, phase advance, repeated-fingerprint escalation, repair limit, BLOCKED, and restart from saved state.

- [ ] **Step 3: Run focused tests and confirm RED**.

- [ ] **Step 4: Implement deterministic Git reconciliation** with `git rev-parse`, `git status --porcelain`, `git diff`, remote branch lookup, and non-destructive snapshot metadata. Never issue `reset`, `clean`, force checkout or force push.

- [ ] **Step 5: Implement T4 locking** so real/recorded evidence keeps T2/T3 verified and selects `.codex-tasks/08-v4-t4-analytics-admin.md` as the current task.

- [ ] **Step 6: Implement repair fingerprints** as hashes of normalized failure class + failing gate + stable error summary; after two unchanged repeats force broader diagnosis, after three no-progress repeats block.

- [ ] **Step 7: Implement phase guards** that reject model requests to release from T4 and reject `COMPLETE` unless release/Notion invariants are satisfied.

- [ ] **Step 8: Run focused/full tests**, then commit.

Expected commit: `feat(bridge): add resume and repair orchestration`

---

### Task 5: Codex result contract, executor prompt and Windows CLI

**Files:**
- Create: `bridge/prompts/codex-executor.md`
- Create: `scripts/gpt-codex-bridge.php`
- Create: `scripts/gpt-codex-bridge.ps1`
- Create: `tests/BridgeCliTest.php`

**Interfaces:**
- CLI commands: `status`, `preflight`, `once`, `run`, `resume`, `dry-run`
- Codex writes: `bridge/runtime/result.json` conforming to `bridge/schemas/codex-result.schema.json`.

- [ ] **Step 1: Write failing CLI tests** for help/status, missing key, missing Codex executable, dry-run, and result-schema rejection.

- [ ] **Step 2: Run focused tests and confirm RED**.

- [ ] **Step 3: Write the Codex executor prompt** requiring it to read `AGENTS.md`, `knowledge/PROJECT-STATE.md`, task-specific knowledge and current task; it must execute rather than narrate, update project state only with observed evidence, and atomically write `bridge/runtime/result.json` at turn end.

- [ ] **Step 4: Implement PHP CLI entrypoint** that resolves repo root, loads autoload/classes, dispatches commands, prints compact stage updates, and exits nonzero only on real failure/blocker.

- [ ] **Step 5: Implement PowerShell wrapper** that detects the verified local PHP path first, then PATH PHP, passes arguments unchanged, and never echoes credential values.

- [ ] **Step 6: Run focused/full tests**, then commit.

Expected commit: `feat(bridge): add autonomous Bridge CLI`

---

### Task 6: T5 final verification and formal release task

**Files:**
- Create: `.codex-tasks/09-v4-t5-final-release.md`
- Create: `tests/BridgeReleaseGuardTest.php`
- Modify: `bridge/phases/v4.0.json`

**Interfaces:**
- T5 input: T4 VERIFIED result and exact implementation SHA.
- T5 output: release-quality result with Local Runtime, CI, Release Gate, formal ZIP and GitHub Release evidence.

- [ ] **Step 1: Write failing release-guard tests** proving T4 cannot release, T5 cannot release with failed runtime/CI, and `COMPLETE` is impossible without verified Tag + GitHub Release + formal ZIP.

- [ ] **Step 2: Run focused tests and confirm RED**.

- [ ] **Step 3: Write the T5 task** from `docs/RELEASE.md`, `AGENTS.md`, v4 PRD and T4 handoff. Require final Windows runtime regression, schema/data preservation, permissions/CSRF/privacy checks, release-level tests, version/docs consistency, exact-SHA CI, ZIP whitelist verification and Release Dry Run.

- [ ] **Step 4: Authorize formal release only after Release Gate PASS**. The task may merge/tag/create GitHub Release only when every release prerequisite is observed; it must never deploy to production or upload to the Z-Blog app center.

- [ ] **Step 5: Add post-release verification** of tag, Release, ZIP filename and SHA256 before reporting `RELEASED`.

- [ ] **Step 6: Run focused/full tests**, then commit.

Expected commit: `feat(bridge): add v4 T5 release automation gate`

---

### Task 7: Notion writeback and secret hygiene

**Files:**
- Create: `bridge/src/NotionWriteback.php`
- Create: `tests/NotionWritebackTest.php`
- Create: `tests/BridgeSecretHygieneTest.php`
- Modify: `bridge/config.example.json`

**Interfaces:**
- Produces: `NotionWriteback::appendReleaseEvidence(array $payload): array`

- [ ] **Step 1: Write failing tests** with fake HTTP transport for successful append, missing token, permission error, and redaction of token/cookie/key-shaped strings.

- [ ] **Step 2: Run focused tests and confirm RED**.

- [ ] **Step 3: Implement Notion API writeback** using a dedicated local integration token and configured page ID; append only safe release/gate evidence and never reuse or assume access to ChatGPT's connected Notion session.

- [ ] **Step 4: Make Notion failure resumable**: real GitHub Release evidence remains saved, but full flow stays `INCOMPLETE` until required Notion writeback passes.

- [ ] **Step 5: Add secret hygiene scan** over tracked Bridge examples/prompts and persisted runtime state fixtures; reject obvious `sk-`, bearer-token, cookie and private-key payloads.

- [ ] **Step 6: Run focused/full tests**, then commit.

Expected commit: `feat(bridge): add Notion release writeback`

---

### Task 8: CI contract and mocked end-to-end loop

**Files:**
- Create: `tests/BridgeEndToEndTest.php`
- Modify: `.github/workflows/code-check.yml` only if Bridge tests need an explicit environment capability check.
- Create: `docs/GPT-CODEX-BRIDGE.md`

**Interfaces:**
- Test scenario: Resume T4 → fake Codex failure → GPT REPAIR → fake Codex PASS → ADVANCE T5 → Release Gate PASS → fake Release → Notion PASS → COMPLETE.

- [ ] **Step 1: Write the end-to-end test** with fake App Server and fake HTTP transports; it must execute at least two Codex turns and one repair loop with no manual input.

- [ ] **Step 2: Run the test and confirm it fails until all integration seams are wired**.

- [ ] **Step 3: Wire the orchestration seams** without adding network calls to PHPUnit.

- [ ] **Step 4: Document one-time local prerequisites**: Codex CLI authenticated, OpenAI API key available, local Z-Blog test environment, Git/GitHub release permission, and Notion token/page if mandatory.

- [ ] **Step 5: Run full release-independent verification**:

```text
git diff --check
php -l on all non-vendor PHP
vendor/bin/phpunit
UI terminology gate
Semgrep through existing CI
PHPStan report-only through existing CI
```

- [ ] **Step 6: Push and verify exact Bridge implementation SHA CI**.

Expected commit: `test(bridge): verify autonomous repair and release loop`

---

### Task 9: Real local App Server preflight without touching T4

**Files:**
- Runtime evidence only under ignored `bridge/runtime/`.
- Modify Bridge code/tests only if the preflight proves a Bridge defect.

**Interfaces:**
- Command: `.\scripts\gpt-codex-bridge.ps1 preflight`
- Command: `.\scripts\gpt-codex-bridge.ps1 once --read-only`

- [ ] **Step 1: In an isolated local worktree, reconcile branch/HEAD and run baseline PHPUnit before Bridge execution.**

- [ ] **Step 2: Run `codex --version` and the real `codex app-server` initialize/thread/turn handshake through the Bridge.**

- [ ] **Step 3: Run one explicitly read-only Codex turn** that reads `knowledge/PROJECT-STATE.md`, reports the current T4 task, writes a valid Bridge result, and makes zero repository changes.

- [ ] **Step 4: Call the real GPT controller once** and verify Structured Output is accepted without printing the API key.

- [ ] **Step 5: Verify `git status` shows no T4 product-code change caused by preflight.**

- [ ] **Step 6: If a Bridge defect appears, fix it with focused tests and repeat only the failed preflight.**

---

### Task 10: Production takeover of current T4

**Files:**
- Existing T4 files as defined by `.codex-tasks/08-v4-t4-analytics-admin.md`.
- Bridge runtime state/evidence under ignored `bridge/runtime/`.

**Interfaces:**
- Command: `.\scripts\gpt-codex-bridge.ps1 resume`

- [ ] **Step 1: Start with `RESUME` and verify the Bridge selects T4 rather than T2/T3.**

- [ ] **Step 2: Let Codex execute the existing T4 Task 1–10 continuously.**

- [ ] **Step 3: On ordinary failures, let GPT/Codex repair automatically and rerun invalidated gates.**

- [ ] **Step 4: Require real Windows Z-Blog admin/runtime and MySQL `EXPLAIN` evidence before T4 can be VERIFIED.**

- [ ] **Step 5: Verify exact-SHA GitHub CI and T4 Release Gate `NOT READY`, then advance to T5 automatically.**

- [ ] **Step 6: Execute T5, Release Gate and formal v4.0.0 release when all gates pass.**

- [ ] **Step 7: Write Notion release evidence, verify final six-gate report, and persist `FINAL: COMPLETE / RELEASE: RELEASED`.**

- [ ] **Step 8: If a true blocker occurs, persist state and stop without losing the completed development/release evidence.**
