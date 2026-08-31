# GPT-Codex Autonomous Bridge v2.2 — Quota Checkpoint & Auto-Resume Amendment

Date: 2026-08-31
Status: binding amendment to `docs/ZBLOG-AUTONOMOUS-DEVELOPMENT-v2.2.md`

## Purpose

When GPT, Codex, OpenAI API, or another required programmatic execution provider reaches zero usable quota, the autonomous development run must preserve all progress and become resumable without requiring the user to operate Codex UI or manually relay GPT/Codex messages.

Quota exhaustion is a resource suspension, not a development failure.

## Mandatory states

Add the following lifecycle states:

- `QUOTA_CHECKPOINT`
- `WAITING_QUOTA`
- `QUOTA_RECHECK`
- `AUTO_RESUME`

`WAITING_QUOTA` is non-terminal. The only successful terminal state remains `PLUGIN_RELEASED`.

Quota-related result classification:

- `QUOTA_EXHAUSTED_GPT`
- `QUOTA_EXHAUSTED_CODEX`
- `QUOTA_EXHAUSTED_PROVIDER`
- `QUOTA_TEMPORARY_RATE_LIMIT`

A temporary HTTP 429 with usable quota remaining stays `RETRYABLE_INFRA`; explicit zero/insufficient quota enters the checkpoint flow.

## Detection

The Bridge must classify quota exhaustion from structured provider responses, App Server/Codex events, CLI exit/result metadata, or explicit provider reset information. It must not guess from arbitrary natural-language output when structured evidence exists.

If the provider supplies `retry_after` or `reset_at`, persist it. Otherwise use bounded backoff for recheck and preserve the ability to resume on Bridge restart/logon.

## Zero-quota checkpoint contract

Before entering `WAITING_QUOTA`, the Bridge must atomically persist a sanitized checkpoint containing at least:

```text
request_id
project
business_goal
requirement_id
lane
current_stage
stage_status
branch
head_sha
candidate_id
worktree_path
dirty_paths
last_verified_gate
verified_gates
pending_gates
thread_id
turn_id
controller_previous_response_id
last_codex_result_id
pending_gpt_decision
pending_codex_instruction
code_repair_round
infra_retry_count
evidence_ids
expected_diff_id
rollback_target
quota_provider
quota_classification
quota_reset_at
next_action
checkpoint_created_at
```

Secrets, cookies, API keys, raw authorization headers and private visitor data must never be written into the checkpoint.

## Worktree preservation

Quota exhaustion must never trigger `reset`, `clean`, destructive checkout, stash-discard, force push, or deletion of the current worktree.

If quota reaches zero while Codex has uncommitted task-owned changes:

1. stop dispatching new mutating turns;
2. wait for the currently executing reversible filesystem operation to reach a safe process boundary when possible;
3. record Git HEAD and dirty paths;
4. preserve the isolated worktree exactly as-is;
5. record whether the current turn is complete, interrupted, or uncertain;
6. on resume, run Resume Gate and reconcile real Git/files/runtime evidence before issuing another mutation.

The Bridge may create a local checkpoint commit only when all changed paths are proven to belong to the isolated autonomous worktree and repository policy explicitly permits checkpoint commits. It must not use a checkpoint commit to hide unrelated user changes and must not push incomplete checkpoint commits as a formal candidate.

## GPT quota exhausted

If GPT/controller quota reaches zero:

- persist the latest Codex result and evidence;
- do not invent the next technical decision;
- do not continue mutating code using stale instructions;
- enter `WAITING_QUOTA` with `next_action = GPT_REVIEW`;
- when quota returns, send the preserved latest execution result to GPT and continue the normal GPT → Bridge → Codex loop.

## Codex quota exhausted

If Codex/executor quota reaches zero:

- allow GPT to analyze already-returned evidence if GPT remains available;
- persist GPT's next machine decision and `codex_prompt` without requiring user action;
- enter `WAITING_QUOTA` with `next_action = TASK_DISPATCH`;
- when Codex quota returns, automatically dispatch the preserved instruction to the same recoverable thread when safe, otherwise create a new thread with a sanitized handoff.

## Simultaneous quota exhaustion

If both GPT and Codex are unavailable:

- checkpoint first;
- set `next_action` to the earliest unresolved control step;
- do not ask the user to copy any content or open Codex UI;
- resume automatically after programmatic capacity returns.

## Auto-resume policy

The Bridge must support automatic recovery by at least these mechanisms:

1. live process sleep/recheck using provider `reset_at`/`retry_after` when available;
2. Windows Scheduled Task/service `resume` on machine restart/logon;
3. manual Bridge process restart that reads the same checkpoint, without requiring any GPT/Codex copy-paste.

On quota recheck success:

```text
WAITING_QUOTA
→ QUOTA_RECHECK
→ RESUME_GATE
→ reconcile Git/runtime/CI/evidence
→ AUTO_RESUME
→ earliest unfinished legitimate state
```

Verified gates must not be repeated unless the changed SHA/runtime state invalidates their evidence.

## Release-First interaction

Quota exhaustion may pause P1 release progress, but it must not cause P4 automation work to replace the current release objective. After quota returns, the Bridge resumes the existing business goal and candidate chain first.

## User interaction rule

Quota exhaustion must never produce a normal instruction such as:

- open Codex UI;
- click Continue;
- paste this GPT message into Codex;
- paste Codex output back to GPT;
- restart development from the beginning.

The user may be informed that the run is safely suspended, but no execution action is required from them unless the underlying account itself requires an external billing/authorization decision that cannot be automated.

## Acceptance criteria

The feature is accepted only when tests demonstrate:

1. synthetic GPT zero-quota event persists a complete sanitized checkpoint;
2. synthetic Codex zero-quota event persists pending GPT/Codex continuation correctly;
3. dirty autonomous worktree is preserved without reset/clean/stash-discard;
4. Bridge restart resumes from `WAITING_QUOTA` rather than T2/T3 or task start;
5. previously VERIFIED gates remain locked when still valid;
6. restored quota triggers automatic GPT/Codex continuation without Codex UI;
7. no secret material appears in checkpoint/evidence files;
8. final successful run can continue from quota suspension to `PLUGIN_RELEASED`.
