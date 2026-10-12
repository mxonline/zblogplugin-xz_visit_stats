# xz_visit_stats v4.0 T5 Windows Final Acceptance Plan

> **For agentic workers:** Use `superpowers:executing-plans` to carry this plan through in the current Codex task. The current Codex conversation is the T5 task; do not create a duplicate Notion task.

**Goal:** Complete the existing T5 Windows acceptance and prepare a verified v4.0.0 release candidate without merging, tagging, publishing, or touching production.

**Architecture:** Reconcile the existing `feature/visit-stats-4.0` checkout and the single established Windows Z-Blog/IIS runtime. Use isolated, uniquely identified acceptance data and preserve all T2/T3/T4 artifacts and historical rows; only make a code change if a reproduced failure proves it necessary. After runtime evidence is complete, align release metadata, create a whitelist-based install ZIP, run the release dry run, then push the final development commit and verify CI for its exact SHA.

**Tech Stack:** Windows PowerShell, PHP 8.3, Z-BlogPHP, IIS, MySQL 5.7, existing PHPUnit and JavaScript syntax tooling, GitHub Actions, Notion.

**Spec:** Notion task “T5：在 Windows 本地 Z-Blog 完成升级与回归验证” (`https://app.notion.com/p/3c8887d3643281fc8e41c4115a72062e?pvs=204`), Notion v4.0 implementation plan, `docs/v4.0.0/PRD-v1.0.md`, `docs/TESTING.md`, `docs/RELEASE.md`, and the current user’s T5 acceptance instructions.

## Global Constraints

- Preserve the existing T2/T3/T4 commits, verified data, retained runtime/database backups, and v3 history.
- Use the existing feature branch and single local runtime; do not create a parallel runtime, duplicate task, or additional release gate.
- Do not run `reset`, `clean`, force-push, production deployment/database writes, merge, tag, GitHub Release, or Z-Blog application-center upload.
- Isolate acceptance records and remove only rows created for this run, identified by their test keys or primary IDs.
- Preserve the meaning of `vs_DurationMs` as server processing time; never use it for visitor dwell.
- Match report values to direct SQL and the PRD’s PV/UV/IP/session/dwell/bounce definitions.
- Retain required runtime vendor code and its license in the package; exclude development tools, tests, caches, secrets/configuration, and backups.
- Do not refactor existing PHPStan report-only diagnostics unless a reproduced T5 failure is caused by them.
- Use TDD for any production code fix: reproduce with a failing focused test, make the smallest fix, then rerun focused and affected regression checks.

## Review Focus

- Existing historical tables/rows must remain unchanged by migration and test cleanup.
- Repeated migration and plugin re-enable must not duplicate DDL, indexes, or rows.
- Unknown historic session lifecycle values must remain unavailable/partial, not fabricated zeros.
- Visitor IP/hash, session keys, event params, and CSV exports must honor existing privacy and permission boundaries.
- Release metadata and archive contents must describe the same tested source SHA and preserve only required runtime dependencies.

---

## Execution Tasks

### Task 1: Reconcile the official T5 task and repository baseline

**Files:**
- Read: `AGENTS.md`, `knowledge/PROJECT-STATE.md`, `knowledge/INDEX.md`, `knowledge/KNOWN-FAILURES.md`, `knowledge/ZBLOG-DEVELOPMENT-KNOWLEDGE.md`, `docs/TESTING.md`, `docs/RELEASE.md`, `docs/v4.0.0/PRD-v1.0.md`, `docs/v4.0.0/MIGRATION-DESIGN-v1.0.md`, the T4 handoff, and the official Notion T5 task.
- Update: official Notion T5 status and `knowledge/PROJECT-STATE.md` as evidence is established.

- [x] Verify branch `feature/visit-stats-4.0`, HEAD `af3cc7137555257c379877cb0d34ce7478220576`, clean tree, and matching remote SHA.
- [x] Read the official T5 task and acceptance criteria; confirm the existing Codex task is the sole execution task.
- [x] Mark the existing T5 Notion task in progress; do not create another task.
- [x] Record the baseline T4 CI run `38296309009` as completed/success on the exact baseline SHA.

### Task 2: Establish the single-runtime safety and data baseline

**Files:**
- Read: `scripts/local-verify.ps1`, `scripts/v4-schema-audit.ps1`, install/upgrade code and tests.
- Evidence: retained backup inventory, runtime versions, baseline table counts/aggregates, and relevant application/error logs.

- [x] Confirm IIS, PHP 8.3, Z-BlogPHP, MySQL 5.7 and localhost HTTP health against the existing Windows test site.
- [x] Preserve the most recent runtime backup and database backup; create a fresh plugin backup before any runtime plugin-file synchronization.
- [x] Select one isolated acceptance-data boundary compatible with the existing runtime; prove its identity before any write.
- [x] Record v3 table counts and T2/T3/T4 schema/index invariants without exporting visitor-level data or credentials; the live test site was already at v4, so the v3 history migration used isolated synthetic rows and temporary tables.

### Task 3: Execute lifecycle, metrics, report, privacy and compatibility acceptance

**Files:**
- Read and use existing acceptance/runtime scripts, PHPUnit tests, SQL definitions, and admin UI.
- Production code/tests: change only if a real failure is reproduced; use TDD for every such fix.

- [x] Verify fresh install, v3-to-v4 migration with synthetic/isolated v3 data, repeated migration, deactivate/reactivate, and retained legacy data/schema.
- [x] With uniquely marked test records, verify PV, UV, IP, sessions, lifecycle dwell, bounce rate, realtime, trend, source, spider, events and visit/session drill-down.
- [x] Verify IP filter behavior, controlled export, CSV safety and role/ownership denial; compare UI metrics with direct SQL using the same period and test key.
- [x] Verify mobile/tablet/desktop responsive layouts, empty/partial states, output escaping, masked identifiers, allowed event params and no visitor data leakage.
- [x] Run PHP/JavaScript syntax checks, PHPUnit, core regressions, HTTP smoke, MySQL 5.7 EXPLAIN and risk-based performance checks.
- [x] Inspect available PHP/IIS/MySQL logs; remove only test records created by this run and verify baseline historical rows remain. Document the IIS-short-circuited 404 and historical protected-route 500 context.

### Task 4: Prepare and verify the v4.0.0 release candidate

**Files:**
- Modify: `plugin.xml`, `README.md`, `docs/VERSION.md`, `docs/CHANGELOG.md`.
- Create/update: `docs/RELEASE_NOTES_v4.0.0.md`, `docs/v4.0.0/UPGRADE-v3-to-v4.md`, and `docs/v4.0.0/T5-ACCEPTANCE-2026-10-13.md`.
- Artifact: whitelist-built `xz_visit_stats-v4.0.0.zip` under the ignored release-candidate output directory.

- [x] Align version metadata to v4.0.0 and clearly mark it as a candidate, not formally released.
- [x] Document observed upgrade behavior, data preservation, prerequisites, privacy, compatibility and verified limitations.
- [x] Build the install ZIP from an explicit allowlist, including the required `mlocati/ip-lib` runtime source and MIT license.
- [x] Audit the archive for missing runtime files, dev/test/cache/config/backup files, secrets and unrelated dependencies.
- [x] Execute the final Release Dry Run; `Release Gate` may be `PASS` only after exact-SHA CI and final ZIP/readback checks pass.

### Task 5: Final delivery and writeback

**Files:**
- Update: `knowledge/PROJECT-STATE.md`, Notion official T5 task, and existing implementation-plan status.

- [x] Re-run final repository checks and inspect the complete diff/package manifest for unrelated content or secrets.
- [x] Commit and push the completed T5 evidence on `feature/visit-stats-4.0`; do not merge, tag, release or upload to the Z-Blog application center.
- [x] Verify a successful GitHub Actions run whose `head_sha` exactly matches the candidate source commit.
- [x] Write observed results and package hashes into the existing project state and Notion task; read both back.
- [x] Report the required six FULL DEVELOPMENT FLOW GATE items, acceptance matrix, final SHA, CI run ID, release gate, candidate artifacts, unreleased status and one next action.
