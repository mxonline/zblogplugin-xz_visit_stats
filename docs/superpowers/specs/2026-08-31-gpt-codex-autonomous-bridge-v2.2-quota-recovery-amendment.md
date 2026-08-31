# GPT-Codex Autonomous Bridge v2.2 — Quota Exhaustion Recovery Amendment

Status: APPROVED ADDITIVE HARD REQUIREMENT
Date: 2026-08-31

## Goal

If GPT/OpenAI Responses API or Codex execution quota reaches zero, the autonomous development run must preserve all recoverable progress and enter a resumable pause instead of failing, restarting verified work, or asking the user to continue through Codex UI.

## Hard Rules

```text
QUOTA_ZERO_IS_NOT_CODE_FAILURE = TRUE
QUOTA_ZERO_CONSUMES_REPAIR_ROUND = FALSE
QUOTA_ZERO_CHECKPOINT_REQUIRED = TRUE
QUOTA_ZERO_USER_CODEX_UI_HANDOFF = FORBIDDEN
QUOTA_RESUME_POLICY = PRESERVE_VERIFIED
```

A bare HTTP 429 is not enough to classify quota exhaustion. Ordinary rate limiting remains `RETRYABLE_INFRA`. `PAUSED_QUOTA` is used only when evidence explicitly indicates exhausted quota/credits/usage allowance, including provider signals such as `insufficient_quota`, `usage limit reached`, `billing hard limit reached`, exhausted credits, or equivalent zero-remaining evidence.

## Required Checkpoint

Before the Bridge stops making model calls, it must atomically persist a sanitized checkpoint containing at least:

```text
request_id
saved_at
provider
reason
status_before_pause
stage
branch
head_sha
candidate_sha
thread_id
controller_response_id
repair_round
infra_retry_round
last_verified_gate
pending_action
evidence_ids
resume_policy
resume_after
```

The checkpoint must not contain API keys, cookies, authorization headers, raw secrets, or sensitive visitor data.

## State Machine

Quota exhaustion introduces a non-success, non-failure state:

```text
PAUSED_QUOTA
```

Any active GPT/Codex/control/gate state may move to `PAUSED_QUOTA` when quota exhaustion is proven. `PLUGIN_RELEASED`, `FAILED`, and idle completed workflows may not.

Resume path:

```text
PAUSED_QUOTA
→ quota availability recheck
→ Resume Gate
→ reconcile real Git/runtime/CI/evidence
→ restore thread/response continuity when safe
→ resume pending action
```

Verified T2/T3 or other VERIFIED gates must not be repeated merely because quota was exhausted.

## Automatic Resume

When the Bridge service remains alive, it should recheck quota/provider availability at a configured bounded interval without spending code-repair rounds. If the process, host, or service restarts, startup must discover the latest valid quota checkpoint and offer it to Resume Gate automatically.

If the provider exposes a reliable reset/retry time, store it as `resume_after`. Otherwise use the configured bounded retry interval and exponential/backoff policy appropriate to the provider.

The user must never be told to reopen Codex UI, paste the prior result, or manually reconstruct context after quota recovery.

## Acceptance Tests

Before v2.2 activation, tests must prove:

1. explicit `insufficient_quota` becomes `PAUSED_QUOTA`;
2. ordinary 429 rate limiting does not become quota pause;
3. checkpoint preserves branch/SHA/thread/GPT response/gate/repair counters/pending action/evidence pointers;
4. checkpoint text is sanitized;
5. quota pause does not increment code repair count;
6. restart can read the checkpoint and resume through Resume Gate;
7. verified stages remain locked;
8. no Codex UI/manual copy-paste is required;
9. eventual successful run can still reach `PLUGIN_RELEASED` after quota restoration.

## Relationship to v2.2

This amendment is additive and mandatory. It does not weaken Release-First, Zero-Touch, Release Gate, Rollback Gate, exact-SHA CI, local runtime verification, Evidence Ledger, or the complete v2.0 engineering process.
