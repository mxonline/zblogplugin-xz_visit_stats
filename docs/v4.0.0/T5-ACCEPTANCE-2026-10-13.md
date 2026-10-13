# xz_visit_stats v4.0.0 T5 Windows acceptance

## Formal v4.0.0 release — 2026-10-13 (authoritative release outcome)

- Pull request [#26](https://github.com/mxonline/zblogplugin-xz_visit_stats/pull/26) was merged into `main`. The merge commit and release source SHA is `e3dcce5f34def6d70d860a9c4165968f257e1757`; its exact-head GitHub Actions Run [38356703851](https://github.com/mxonline/zblogplugin-xz_visit_stats/actions/runs/38356703851) completed successfully. PR-head Code Quality Run `38356571362` and Windows workspace run `38356571328` also passed on `c3b2bcf769ec1502aab91e885aec406d6aff0eca`.
- Annotated tag `v4.0.0` resolves to `e3dcce5f34def6d70d860a9c4165968f257e1757`. GitHub Release [v4.0.0](https://github.com/mxonline/zblogplugin-xz_visit_stats/releases/tag/v4.0.0) was published on 2026-10-13 and contains the formal install asset `xz_visit_stats-v4.0.0.zip`.
- The published ZIP has 94 allowlisted entries; GitHub reports SHA-256 `D217C4B5D11C358E5E70C5F16FD73FAE6547CD6ADED5A76B1F99D77577B9F890`. The builder verified every archive path/source hash; a read-only comparison confirmed all 94 uncompressed file hashes match the previously installed candidate and the existing Windows runtime.
- T5 acceptance matrix above remains authoritative for Windows install/upgrade, repeat migration, enable/disable, SQL/UI metrics, lifecycle, privacy/permissions, reports, responsive UI, syntax/tests, HTTP smoke, EXPLAIN and exact test-data cleanup. T2/T3/T4 history and backups remain preserved.
- Release Dry Run: `可发布`. Release Gate: `PASS`. Formal release: `RELEASED`. No production website/database operation and no Z-Blog application-centre upload occurred.

## Final recheck — 2026-10-13 (supersedes earlier Release Gate result)

The earlier `Release Gate: PASS` and 95-file package record below describe a superseded candidate. Code review reopened the existing T5 task; only the current evidence in this section is authoritative for the present release decision.

| Acceptance area | Current result | Evidence |
| --- | --- | --- |
| Review fixes and regression | PASS | PHPUnit 78 tests / 543 assertions; PHP 8.3 syntax across the repository; PHP 7.1 syntax across 43 runtime PHP files; Node syntax across all 16 shipped JavaScript files. The fixes cover session key/cookie lifecycle, exact trend UV/IP by grain, session report filters, v4 retention, new/returning overview, CSV formula prefixes, and PHP 7.1 JSON flag compatibility. |
| Fresh install and v3 → v4 migration | PASS | Real Windows Z-BlogPHP/PHP 8.3/MySQL 5.7 adapter. Isolated install and upgrade produced all 12 plugin tables. The synthetic source row used the 30-column v3.0.0 raw-log schema; its ID, path, path key, and visit time remained unchanged through migration and repeated migration. Zero historical table rows were touched; all test-prefix tables were removed. |
| Installed candidate and plugin lifecycle | PASS | Final 94-file candidate installed after a hash-verified backup of 101 current runtime files; 7 unrelated runtime extras remained. Z-Blog disable/enable hooks passed; while disabled, a marked homepage returned 200 and created zero marked logs; the plugin was re-enabled. |
| HTTP collection and statistics | PASS | Final marked HTTP smoke: homepage 200, IIS missing-path 404, crawler/referer request 200; direct SQL found PV 2 / human PV 1 / bot PV 1 / UV 1 / IP 1. Exact marker cleanup deleted 2 logs, 2 pages, 2 sessions; post-cleanup marker count was zero. |
| Session, dwell, RUM and events | PASS | Two same-browser visits reused one `xzvs_sk` session: SQL PV 2 / UV 1 / IP 1 / sessions 1 / pages 2. Same-origin lifecycle/RUM POST stored one RUM row and a 7,014 ms page/session dwell. One event was stored with only `action`, `category`, and `label`; unapproved `cookie`, `ip`, and `token` fields were discarded. Exact marker cleanup removed all test rows and Beacon was restored to disabled. |
| Database preservation and privacy configuration | PASS | Final Safe Mode/read-only audit after cleanup and lifecycle probes: raw logs 313, sessions 12, session pages 25, events 2, RUM 6, export tasks 0, IP rules 0; plugin enabled; Beacon disabled; `db_version=4.0.0`. T2/T3/T4 backups remain retained. |
| MySQL EXPLAIN | PASS | Six read-only MySQL 5.7 plans used their expected indexes: log time, visitor/time, IP/time, active sessions, page-log drill-down, event time. This small existing dataset proves index selection, not large-volume latency. |
| Admin visual/responsive recheck after review fixes | BLOCKED | The final code changes include overview and trend controls. The previously recorded 680/800/1100 px visual result predates this reopened review; no authenticated Z-Blog admin browser session is available now to recheck the current pages, filters, empty states, and responsive layout. No login automation or credential extraction was used. |
| Release candidate ZIP | PASS | `dist/xz_visit_stats-v4.0.0.zip`, 94 allowlisted entries, SHA-256 `477EA666FD9DF3CF675B614135A6A6C9901D41FE654B2013B4F5D66B9F75F0BB`; extracted paths exactly match the allowlist and all archived hashes match the current package sources. Required runtime IP library and licenses plus Apache/IIS export protections are present; tests, scripts, cache, config, and backups are excluded. This exact ZIP was installed to the local test site after a hash-verified backup. |
| Exact-SHA CI and formal release | CI PASS / RELEASE BLOCKED | Candidate source SHA `fb5bf02e68a1485aa3c6ac34cd2fcf958702caf8`; GitHub Actions Run `38353324295` completed successfully with an exact matching `head_sha`. PHP syntax, UI Terminology, PHPUnit, Semgrep, and report-only PHPStan job completed. The release remains blocked by the post-review authenticated admin UI check. No PR merge, tag, GitHub Release, production deployment, or App Centre upload has occurred. |

**Current Release Dry Run:** `不可发布` — exact-SHA CI passed, but the post-review authenticated admin visual/responsive recheck cannot be completed without an authorized local admin session. **Release Gate: `BLOCKED`. Formal status: `NOT RELEASED`.**

**Next action:** provide or open an authenticated session for the existing localhost Z-Blog admin UI so the remaining visual/filter recheck can run; then complete the exact-SHA CI and repeat the same Release Dry Run.

## Final authenticated UI and candidate recheck — 2026-10-13 (supersedes the preceding BLOCKED state)

| Acceptance area | Result | Evidence |
| --- | --- | --- |
| Admin views and responsive layout | PASS | With the existing authorized localhost admin session and the exact installed candidate, all 15 current and compatibility views had the expected heading and no PHP/HTTP 500 text at 680, 800 and 1100 px. At 680 px, the plugin menu collapses and expands, the fixed Z-Blog sidebar no longer covers plugin content, and the page has no horizontal overflow. At 800/1100 px, the plugin sidebar remains available. |
| Filters, empty state and partial coverage | PASS | The `source_type=search` filter produced only the search-engine row: 2 visits, 0 human PV and UV. A no-event query returned the explicit empty-event messages. Overview labels v3-era visitor history as partially covered instead of fabricating v4 lifecycle data. Checks were read-only. |
| Responsive regression fix | PASS | Source commit `8da69fb9c63ba7ff131c44f6df3bb1cecf29285f` removes the mobile override that cleared the Z-Blog 150 px navigation offset and forced `#divMain` to `100vw`; `admin.css` cache key is `4.0.0-t5-responsive`. The focused PHPUnit regression failed before the fix and passed after it. |
| Final local automated checks | PASS | `scripts/local-verify.ps1 -SkipHttp`: PHP 8.3 syntax and PHPUnit `79 tests / 549 assertions` passed; `git diff --check` passed (Git only reported expected CRLF normalization). No JavaScript changed; the previous Node syntax check covered all 16 shipped JS files. The existing T5 HTTP, SQL/data, migration, lifecycle, privacy, PHP 7.1 syntax, EXPLAIN and performance-boundary evidence remains applicable. |
| Exact-SHA CI | PASS | Candidate source commit `8da69fb9c63ba7ff131c44f6df3bb1cecf29285f`, GitHub Actions Run `38354704930`, completed success with an exactly matching `head_sha`. PHP syntax, UI terminology, PHPUnit, Semgrep and the existing report-only PHPStan job completed. |
| Final release candidate ZIP | PASS | `dist/xz_visit_stats-v4.0.0.zip`, 94 allowlisted entries, SHA-256 `0BC97E73563A71D7EEC2A62B34D86422370E51D37A188B4FF1467CF792C87FD8`. Archive entries exactly match the explicit allowlist and source hashes. The exact ZIP was installed after a hash-verified 101-file runtime backup; 7 unrelated runtime extras were preserved. |
| Historical data and safety | PASS | No database mutation was performed during the visual/filter recheck. Prior final Safe Mode audit remains 313 raw logs, 12 sessions, 25 pages, 2 events, 6 RUM, 0 export tasks and 0 IP rules; plugin enabled, Beacon disabled. Isolated migration/lifecycle test rows were cleaned by exact markers/IDs. T2/T3/T4 history and backups remain preserved. |

**Release Dry Run:** `可发布`. Version metadata, README/VERSION/CHANGELOG/Release Notes, upgrade notes, Windows acceptance, exact-SHA CI, 94-entry ZIP manifest, runtime dependency/license and Notion release-record precheck pass. **Release Gate: `PASS`. Formal status: `NOT RELEASED`** until the separate formal PR/merge/tag/GitHub Release sequence. Production website/database and Z-Blog App Centre remain outside this acceptance.

**Next action:** proceed with the existing formal PR/merge/tag/GitHub Release sequence only on the verified candidate and only after the exact final-checkpoint CI succeeds.

## Historical candidate record — superseded after review re-open

## Baseline and environment

- T4 commit: `af3cc7137555257c379877cb0d34ce7478220576`.
- T4 GitHub Actions run `38296309009`: successful on the exact T4 SHA.
- T5 reused the existing local Windows Z-BlogPHP site; it did not create another runtime or reset the site database.
- IIS 10, PHP 8.3.8, Z-BlogPHP build 173540, MySQL 5.7.38-log.
- Before T5 probes, the local database had 313 raw logs, 12 sessions, 25 session pages, and 2 events; configured `db_version` was `4.0.0`.

## Acceptance matrix

| Area | Result | Evidence |
| --- | --- | --- |
| Fresh install and v3 → v4 migration | PASS | Actual MySQL 5.7 connection and adapter with plugin install/migration functions against isolated temporary tables: fresh install, synthetic v3 row preservation, v3 → v4, repeated migration, deactivate/reactivate, and repeat fresh install passed. Temporary tables only; no persistent DDL. |
| Existing-site disable/enable | PASS | Z-BlogPHP plugin manager returned success for disable and re-enable. While disabled, homepage returned 200 and the marked probe produced no visit row. After re-enable, `db_version=4.0.0`; log/session/page/event counts remained 313/12/25/2. The install hook ran without reducing historical rows. |
| Repeated migration in the active runtime | PASS | Two marked homepage requests returned 200 and left `db_version=4.0.0`. Their two logs, one session, and two page rows were removed by exact IDs/markers. Other log rows remained 313. |
| PV / UV / IP / spider / HTTP status | PASS | Isolated SQL sample: total PV 5, human PV 4, UV 3, IP 3, spider PV 1, 4xx 1. Overview showed human PV 4, UV 3, IP 3; spider ranking showed Baiduspider 1. The trend row matched PV 5 / UV 3 / IP 3. |
| Session, dwell, bounce and drill-down | PASS | Three sample sessions, three unique visitors, two completed sessions, one bounce, 50% bounce rate, 20,000 ms mean lifecycle dwell. The two-page detail showed the sequence without exposing the internal session key. `vs_DurationMs` remained server processing time. |
| Real-time, sources, content and visitors | PASS | All ten v4 views loaded without visible PHP/SQL error text. Marker-only sample rows appeared in their expected views; source totals were 5/4 human, content pages and entries matched the fixture, and visitor depth/dwell matched direct SQL. |
| Spider and events | PASS | Baiduspider classification, IP and page count matched SQL. Three events, two unique visitors, and 1.50 average events per visitor matched SQL; only allowlisted event parameters were stored. |
| IP filtering | PASS | A temporary CIDR rule matched its reserved test IP, did not match an adjacent address, stopped matching when disabled, and was deleted by exact row ID/hash/note. Records IP filtering returned exactly two sample rows for `192.0.2.11`. |
| Export and permissions | PASS | Root-owned export generated exactly two CSV rows from the isolated reserved IP. A level-2 user was denied, the root owner could read/download, and the exact task rows and files were removed. |
| Beacon privacy | PASS | Beacon remained disabled. A valid same-origin event POST returned 204 and inserted no event. The Beacon setting was not changed; the separate temporary CIDR rule was removed by exact ID/hash/note. |
| HTTP smoke and static assets | PASS / LIMIT | Home and crawler/referer requests returned 200; Googlebot, Baiduspider, and bingbot records were classified correctly. The static `assets/admin.css` request returned 200 and created no ordinary visit row. A direct missing path returned 404 from IIS before PHP; the plugin did not receive that request. The v4 404 metric/report row was checked using the isolated marked SQL fixture. |
| Responsive UI | PASS | Body/document widths stayed within 680, 800, and 1100 px viewports; the 680 px collapsed-menu state had no horizontal page overflow. |
| MySQL EXPLAIN | PASS WITH SMALL-DATA NOTE | Log time, visitor/time, IP/time, page drill-down, and event/time plans exposed their expected indexes. The session-time query estimated only 20 rows and MySQL chose a table scan on this small local dataset despite `xzvs_session_time` being available. No large-load benchmark was run against the 313-row site. |
| Test data cleanup | PASS | Removed exactly 12 marked HTTP/fixture logs, 8 associated sessions, 11 page rows, 3 events, and 3 export tasks. The non-test raw-log count stayed 313. The two repeated-migration rows and associated lifecycle records were also removed by exact marker and primary IDs. Existing historical rows and all T2/T3/T4 backups remain. |

## Automated verification

- `scripts/local-verify.ps1 -SkipHttp`: PASS; PHPUnit `69 tests / 494 assertions`; PHP syntax pass for the verifier's PHP set; `git diff --check` had no errors (only Git's CRLF normalization notices).
- JavaScript syntax: PASS for all 16 asset files with Node `--check`.
- Metadata: PASS; `plugin.xml` ID `xz_visit_stats`, version `4.0.0`, PHP minimum `7.1`; README, VERSION, CHANGELOG, and release notes consistently call v4.0.0 a candidate, not a formal release.
- MySQL read-only schema audit after candidate ZIP installation: PASS on MySQL `5.7.38-log`; Z-BlogPHP `173540`; plugin enabled; Beacon disabled; `db_version=4.0.0`. Preserved rows: raw logs `313`, sessions `12`, session pages `25`, events `2`, export tasks `0`.
- PHP/IIS logs: PHP `error_log` is configured as `syslog` and no PHP error file is configured. Windows Application log had zero PHP/FastCGI/IIS warning or error events in the reviewed 24-hour window. The available IIS W3C log contained 19 historical HTTP 500 responses: 13 direct unauthenticated requests to the protected plugin admin entry (the code calls Z-BlogPHP `ShowError(6)`, whose default is 500), plus login/verification/admin routes and one homepage request. These predated the T5 smoke checks; the T5 homepage smoke returned 200 and no current PHP/SQL error text appeared in the tested v4 pages. A direct nonexistent path is short-circuited by IIS with 404 before PHP, so collector handling for that route could not be established; the 404 report metric was validated against the isolated fixture.
- Release candidate ZIP: `dist/xz_visit_stats-v4.0.0.zip`, SHA-256 `03DA00ACED38BA77B2CA0C59DCC51422456DD0830A999FF777B4D930EC0748E1`; 95 allowlisted entries, no missing or extra files, every archived file hash matched its source. Apache `.htaccess`, IIS `web.config`, and required vendor/third-party licenses are included; development, tests, config, cache, backup, and export files are excluded. The exact ZIP was installed into the local runtime and all 95 installed file hashes matched the source.
- Candidate installation: unpacked into the existing local Windows test site after copying the prior plugin tree to a separate T5 backup (92 files). Installed plugin ID/version and all runtime PHP/JS hashes match the source; the existing plugin remains enabled and historical DB row counts are unchanged.
- Exact candidate source commit `13def711c5fbc52de223d260b0315a3596570f8e`; GitHub Actions Run `38304017846` is `success` with an exactly matching `head_sha`.
- Release Dry Run: `可发布`. Version metadata, README/VERSION/CHANGELOG/Release Notes, migration and compatibility notes, Windows acceptance, exact-SHA CI, ZIP name and 95-file allowlist, and Notion release-record precheck passed. No v4.0.0 tag or formal release record exists.
- Release Gate: `PASS`. Formal status remains `NOT RELEASED`; no merge, tag, GitHub Release, production deployment, or Z-Blog App Centre upload has occurred. The existing official T5 task and implementation plan were updated in Notion and read back successfully.

## Release boundary

This is release preparation only. No merge, tag, GitHub Release, production deployment, database restore, or Z-Blog App Centre upload has been performed or is authorized by this task.

## Post-release artifact content audit — 2026-10-13

This section records a read-only continuation of the existing audit. It does not rerun T2–T5 or alter the published Tag, Release, or ZIP.

- **Formal release:** `RELEASED`. GitHub reports published, non-draft Release `v4.0.0`; annotated Tag peels to `e3dcce5f34def6d70d860a9c4165968f257e1757`. `main` is `1485098cac35766a69f2ad014ffed66b33588f50`; exact-SHA Actions Run `38357750526` is `success` with matching `headSha`.
- **Official asset:** downloaded and SHA-256 verified as `D217C4B5D11C358E5E70C5F16FD73FAE6547CD6ADED5A76B1F99D77577B9F890` (GitHub API digest `sha256:d217c4b5d11c358e5e70c5f16fd73fae6547cd6aded5a76b1f99d77577b9f890`); 94 extracted files.
- **Allowlist/runtime check:** reconstructed builder allowlist has 94 entries, with zero missing, extra, or forbidden entries. Required runtime dependency and license files are present. No substantive runtime-code or dependency difference exists between the official ZIP and the Tag.
- **Official ZIP vs Tag:** 37 files byte-identical; 57 text files differ only by CRLF/LF line endings; zero semantic/content differences. EOL-only files: `assets/rum.js`, `assets/v4_admin.js`, `assets/v4_events.js`, `assets/v4_sessions.js`, `data/exports/.htaccess`, `data/exports/index.php`, `data/exports/web.config`, `docs/RELEASE_NOTES_v4.0.0.md`, `docs/v4.0.0/UPGRADE-v3-to-v4.md`, `event.php`, `inc/collector.php`, `inc/events.php`, `inc/ip_filter.php`, `inc/page_lifecycle.php`, `inc/session.php`, `inc/upgrade/migrate.php`, `inc/upgrade/runner.php`, `inc/upgrade/version.php`, `inc/v4_admin_actions.php`, `inc/v4_content_query.php`, `inc/v4_events_query.php`, `inc/v4_export.php`, `inc/v4_metrics.php`, `inc/v4_report_filters.php`, `inc/v4_reports.php`, `inc/v4_sessions_query.php`, `inc/v4_spider_query.php`, `inc/v4_visitors_query.php`, `include.php`, `main.php`, `README.md`, `rum.php`, `third-party-licenses/Apache-2.0.txt`, `third-party-licenses/Apache-ECharts-NOTICE.txt`, `third-party-licenses/BSD-3-Clause-d3.txt`, `third-party-licenses/MIT-Alpine.js.txt`, `third-party-licenses/MIT-Vue-Reactivity.txt`, `THIRD-PARTY-NOTICES.md`, `vendor/mlocati/ip-lib/ip-lib.php`, `vendor/mlocati/ip-lib/LICENSE.txt`, `vendor/mlocati/ip-lib/src/Address/AddressInterface.php`, `vendor/mlocati/ip-lib/src/Address/AssignedRange.php`, `vendor/mlocati/ip-lib/src/Address/IPv4.php`, `vendor/mlocati/ip-lib/src/Address/IPv6.php`, `vendor/mlocati/ip-lib/src/Address/Type.php`, `vendor/mlocati/ip-lib/src/Factory.php`, `vendor/mlocati/ip-lib/src/ParseStringFlag.php`, `vendor/mlocati/ip-lib/src/Range/AbstractRange.php`, `vendor/mlocati/ip-lib/src/Range/Pattern.php`, `vendor/mlocati/ip-lib/src/Range/RangeInterface.php`, `vendor/mlocati/ip-lib/src/Range/Single.php`, `vendor/mlocati/ip-lib/src/Range/Subnet.php`, `vendor/mlocati/ip-lib/src/Range/Type.php`, `vendor/mlocati/ip-lib/src/Service/BinaryMath.php`, `vendor/mlocati/ip-lib/src/Service/NumberInChunks.php`, `vendor/mlocati/ip-lib/src/Service/RangesFromBoundaryCalculator.php`, `vendor/mlocati/ip-lib/src/Service/UnsignedIntegerMath.php`.
- **Official ZIP vs `main`:** 35 files byte-identical, 55 line-ending-only, and four actual text differences: `README.md`, `docs/VERSION.md`, `docs/CHANGELOG.md`, `docs/RELEASE_NOTES_v4.0.0.md`. Each says v4.0.0 is a candidate / not formally released in the published ZIP; `main` says formally released.
- **Official ZIP vs local dist:** all 94 extracted path/payload pairs are byte-identical. Local dist container SHA-256 is `0BC97E73563A71D7EEC2A62B34D86422370E51D37A188B4FF1467CF792C87FD8`; the different container digest is not a payload mismatch.
- **Git baseline:** at audit start, `feature/visit-stats-4.0` HEAD was `3044cb2c262486dc38133d773ef6e6d3b44daeea`; the only existing modifications were `docs/CHANGELOG.md`, `docs/RELEASE_NOTES_v4.0.0.md`, and `docs/VERSION.md`. All three were preserved. This audit added only the project-state and acceptance evidence sections; no commit or push was made.
- **Outcome:** `RELEASED`; `ARTIFACT_CONTENT_VERIFIED=NO`; `POST_RELEASE_AUDIT=BLOCKED`. The formal GitHub release remains valid, but the package's four release documents contradict that state.

## Corrective package candidate — 2026-10-13

- Built a separate, unpublished candidate using the already-preserved formal-status documentation changes: `dist/postrelease-correction-20261013/xz_visit_stats-v4.0.0-corrective-candidate-20261013.zip`, SHA-256 `A5D4E17549F9B0DE16BC0E8C36825740133CDD6D573B6C93D423E7C1AD20EE0E`, 94 entries. The package builder verified the exact allowlist and every included source-file hash.
- The four current v4 release documents in this candidate say formally published. The only remaining “release candidate” phrases in CHANGELOG belong to historical entries for earlier versions, not v4.0.0.
- This is a local review candidate, not a replacement or published attachment. The original v4.0.0 Tag, GitHub Release, and ZIP remain unchanged. Distribution and any new version/tag still require a separate explicit decision.

## Forward correction PR checkpoint — 2026-10-13

- Draft PR [#28](https://github.com/mxonline/zblogplugin-xz_visit_stats/pull/28) was opened against `main` with source commit `81912fe40753a25c1f3c2ec40a5efb1e75d666ca`; exact-SHA Runs [38413677655](https://github.com/mxonline/zblogplugin-xz_visit_stats/actions/runs/38413677655) and [38414205617](https://github.com/mxonline/zblogplugin-xz_visit_stats/actions/runs/38414205617) passed.
- An evidence-only follow-up commit `9c6f965ce3207fea7425c6643679bd96a3243dbd` updated the PR checkpoint; exact-SHA Runs [38414739386](https://github.com/mxonline/zblogplugin-xz_visit_stats/actions/runs/38414739386) and [38414730126](https://github.com/mxonline/zblogplugin-xz_visit_stats/actions/runs/38414730126) passed. At this checkpoint, PR #28 is OPEN/DRAFT and unmerged; it does not alter runtime code or the published asset.
- The local corrective ZIP payload compares to the committed source tree with 37 byte-identical files and 57 EOL-only differences; no content mismatch. The candidate remains unpublished, PR remains draft/unmerged, and the post-release artifact audit remains blocked pending an explicit distribution decision.

### Minimal forward-fix proposal

Historical proposal, superseded 2026-10-14: the user authorized a versioned v4.0.1 corrective release through existing PR #28. The v4.0.0 Tag, Release, and ZIP remain immutable.

## Authorized v4.0.1 corrective release — 2026-10-14 (in progress)

- Authorization: publish v4.0.1 as a forward correction while retaining the v4.0.0 Tag, GitHub Release, and ZIP unchanged. The original ZIP SHA-256 remains `D217C4B5D11C358E5E70C5F16FD73FAE6547CD6ADED5A76B1F99D77577B9F890`.
- Reuse the existing T5 acceptance matrix and Windows/MySQL 5.7 evidence; no T2–T5 installation, migration, or production operation was repeated. Runtime code and DB schema are unchanged; schema `db_version` remains `4.0.0`.
- Current local verification: PHP syntax and PHPUnit `79 tests / 549 assertions` passed via `scripts/local-verify.ps1 -SkipHttp`; Node syntax passed for all 16 shipped JavaScript files.
- Local package checkpoint `dist/release-candidates/2026-10-14-final/xz_visit_stats-v4.0.1.zip`: SHA-256 `5448AC30755A7E00790B9B6F90EC2799BBB0D0A8F9067D34F628859B5DBA5078`, 95 entries. Explicit allowlist and every uncompressed file hash match; runtime dependencies and licenses are present; development/test/config/cache/backup/export paths are absent. Full file-hash evidence: `docs/v4.0.1/PACKAGE-MANIFEST-v4.0.1-final.md`.
- At this checkpoint PR #28 remains OPEN/DRAFT at its prior audit head; the current v4.0.1 edits have not yet been pushed or covered by exact-SHA CI. Release Gate is `NOT READY`; the v4.0.1 package is local and unpublished. See the current project state for the only next action and subsequent CI/Release evidence.
