# xz_visit_stats v4.0 T5 Windows Final Acceptance Plan

> **For agentic workers:** Use `superpowers:executing-plans` to carry this plan through in the current Codex task. The current Codex conversation is the T5 task; do not create a duplicate Notion task.

**Goal:** Complete the existing T5 Windows acceptance and, only when the existing Release Gate passes, complete the v4.0.0 formal release without touching production or the Z-Blog application center.

**Architecture:** Reconcile the existing `feature/visit-stats-4.0` checkout and the single established Windows Z-Blog/IIS runtime. Use isolated, uniquely identified acceptance data and preserve all T2/T3/T4 artifacts and historical rows; make a code change only for a reproduced T5/review finding. After local evidence is complete, align release metadata, create a whitelist-based install ZIP, run the existing Release Dry Run, then open a PR, merge, tag and publish only if every documented gate passes and exact-SHA CI succeeds.

**Tech Stack:** Windows PowerShell, PHP 8.3, Z-BlogPHP, IIS, MySQL 5.7, existing PHPUnit and JavaScript syntax tooling, GitHub Actions, Notion.

**Spec:** Notion task “T5：在 Windows 本地 Z-Blog 完成升级与回归验证” (`https://app.notion.com/p/3c8887d3643281fc8e41c4115a72062e?pvs=204`), Notion v4.0 implementation plan, `docs/v4.0.0/PRD-v1.0.md`, `docs/TESTING.md`, `docs/RELEASE.md`, and the current user’s T5 acceptance instructions.

## Global Constraints

- Preserve the existing T2/T3/T4 commits, verified data, retained runtime/database backups, and v3 history.
- Use the existing feature branch and single local runtime; do not create a parallel runtime, duplicate task, or additional release gate.
- Do not run `reset`, `clean`, force-push, production deployment/database writes, or Z-Blog application-center upload. Merge, tag and GitHub Release are allowed only after the existing documented release gates pass.
- Isolate acceptance records and remove only rows created for this run, identified by their test keys or primary IDs.
- Preserve the meaning of `vs_DurationMs` as server processing time; never use it for visitor dwell.
- Match report values to direct SQL and the PRD’s PV/UV/IP/session/dwell/bounce definitions.
- Retain required runtime vendor code and its license in the package; exclude development tools, tests, caches, secrets/configuration, and backups.
- Do not refactor existing PHPStan report-only diagnostics unless a reproduced T5 failure is caused by them.
- Use TDD for any production code fix: reproduce with a failing focused test, make the smallest fix, then rerun focused and affected regression checks.

## Review Recheck — 2026-10-13

The first T5 package and its successful CI remain historical evidence. Independent review found reproducible correctness/security gaps, so they no longer authorize a PASS Release Gate. Continue within this same T5 task and existing Release Gate; do not create a duplicate task, Runtime, or gate. The current user explicitly authorizes the documented PR/merge/tag/release flow only if every gate below and `docs/RELEASE.md` passes.

- [x] Make session keys server-authoritative, sync the key to the browser before lifecycle/event beacons, and verify first-page dwell plus timed-out revisit. The real first-page probe measured 7,007 ms; unit coverage verifies expired-session rotation.
- [x] Calculate PV/UV/IP at each requested trend grain and apply device, new/returning, source, and region filters to traffic and session metrics; PHPUnit and SQL/contract tests pass.
- [x] Purge expired v4 sessions, session pages, and events alongside v3 logs; manual purge scope and confirmation text are explicit and covered by PHPUnit.
- [x] Replace the unavailable new/returning overview placeholder with bounded v4-history classification and display its historical coverage caveat.
- [x] Neutralize CSV cells with leading control/whitespace before formula characters; keep event JSON flags compatible with PHP 7.1.
- [x] Run the isolated v3-to-v4 migration using the real Windows Z-BlogPHP/PHP 8.3/MySQL 5.7 adapter; repeat it, verify the v3 schema/history fields and remove only unique temporary-prefix tables.
- [x] Recheck optional carrier/operator support against privacy and available local data; no local GeoIP/operator source is configured, so no external lookup or unsupported result is claimed.
- [x] Re-run PHPUnit, HTTP, privacy, PHP/JS syntax, PHP 7.1 syntax, MySQL EXPLAIN, candidate installation and data-preservation checks after fixes.
- [x] Recheck all 15 current/compatibility admin pages, filters, empty/partial states and 680/800/1100 px responsive layout in the existing authenticated localhost admin session; see the final acceptance matrix.
- [x] Push responsive-fix source `8da69fb9c63ba7ff131c44f6df3bb1cecf29285f`; exact-SHA GitHub Actions Run `38354704930` passed.
- [x] Complete the releasable Release Dry Run: metadata, local acceptance, candidate ZIP hash/allowlist/install and candidate source exact-SHA CI passed; `可发布`, Release Gate `PASS`.
- [x] Write the final T5 acceptance and candidate-package evidence to the existing project state and official Notion task/plan; perform readback after the final exact-SHA checkpoint.

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
- [x] Execute the releasable Release Dry Run; result is `可发布` and Release Gate `PASS` after authenticated UI, local runtime, exact-SHA CI and 94-file candidate package verification.

### Task 5: Final delivery and writeback

**Files:**
- Update: `knowledge/PROJECT-STATE.md`, Notion official T5 task, and existing implementation-plan status.

- [x] Re-run final repository checks and inspect the complete diff/package manifest for unrelated content or secrets.
- [x] Commit and push the review fixes and T5 candidate evidence on `feature/visit-stats-4.0`; do not create a PR merge, tag, or GitHub Release while the existing Release Gate is blocked. Do not upload to the Z-Blog application center.
- [x] Verify GitHub Actions Run `38353324295` succeeded with exact candidate source `head_sha=fb5bf02e68a1485aa3c6ac34cd2fcf958702caf8`.
- [x] Write current observed results and package hash into the existing project state and Notion task/plan; read both back.
- [x] Report the six FULL DEVELOPMENT FLOW GATE items, acceptance matrix, final SHA, CI run ID, Release Gate, published artifact, formal-release status and one next action.

## Formal v4.0.0 release completion — 2026-10-13

- PR [#26](https://github.com/mxonline/zblogplugin-xz_visit_stats/pull/26) merged to `main` as `e3dcce5f34def6d70d860a9c4165968f257e1757`; exact-main-SHA Run [38356703851](https://github.com/mxonline/zblogplugin-xz_visit_stats/actions/runs/38356703851) passed. PR-head runs `38356571362` and `38356571328` passed on `c3b2bcf769ec1502aab91e885aec406d6aff0eca`.
- Tag `v4.0.0` points to the merge commit; GitHub Release [v4.0.0](https://github.com/mxonline/zblogplugin-xz_visit_stats/releases/tag/v4.0.0) is published with a 94-file allowlisted ZIP, SHA-256 `D217C4B5D11C358E5E70C5F16FD73FAE6547CD6ADED5A76B1F99D77577B9F890`.
- Release Gate `PASS`; no production website/database operation or Z-Blog application-centre upload occurred. The existing T5 task and implementation plan are the only task/plan records.

## Authorized v4.0.1 forward correction and release

This continues PR #28 and the existing `DEV-20261013-001` runtime. It does not repeat T2–T5, create another task/Gate, alter production, or modify the published v4.0.0 tag/release/ZIP.

- [x] Correct current version metadata and user docs to v4.0.1; retain an explicit factual v4.0.0 release record and its original ZIP digest.
- [x] Build the v4.0.1 ZIP from the updated explicit allowlist; verify every entry and SHA-256, required runtime dependencies/licenses, and excluded development/config/cache/backup files. Distinguish EOL-only differences from payload changes.
- [ ] Reuse T5 runtime evidence; run the relevant local static/PHPUnit regressions, Release Dry Run, exact-head CI, and all six existing flow gates.
- [ ] Merge PR #28, verify CI on the merge SHA, create `v4.0.1` tag and GitHub Release, then upload the exact verified ZIP without changing v4.0.0 assets.
- [ ] Download the published asset again, verify its GitHub and local SHA-256 plus extracted manifest, update the existing project state and Notion release record, and read them back before marking `PLUGIN_RELEASED`.
