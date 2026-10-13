# v4.0.0 candidate ZIP manifest

Candidate file: `dist/xz_visit_stats-v4.0.0.zip`
Archive root: `xz_visit_stats/`

Current rechecked candidate: 94 allowlisted entries; SHA-256 `477EA666FD9DF3CF675B614135A6A6C9901D41FE654B2013B4F5D66B9F75F0BB`.

The ZIP and extracted file hashes pass. Release Gate remains `BLOCKED` until the post-review authenticated admin UI recheck and exact-SHA CI are complete; do not merge, tag, or publish while blocked. The superseded 95-entry candidate record is retained in the historical T5 notes only.

## Included

- Runtime entry points: `plugin.xml`, `main.php`, `include.php`, `realtime_api.php`, `rum.php`, `event.php`.
- Runtime source and local assets: `inc/`, `assets/`.
- Export-directory protections on Apache and IIS: `data/exports/.htaccess`, `data/exports/index.php`, and `data/exports/web.config`.
- Required IP parsing dependency: `vendor/mlocati/ip-lib/ip-lib.php`, `vendor/mlocati/ip-lib/src/`, and its original `LICENSE.txt`.
- User documentation: `README.md`, `THIRD-PARTY-NOTICES.md`, `docs/VERSION.md`, `docs/CHANGELOG.md`, `docs/RELEASE_NOTES_v4.0.0.md`, `docs/v4.0.0/UPGRADE-v3-to-v4.md`, and `third-party-licenses/`.

## Excluded

- `.git/`, `.github/`, Codex task state, plans, and local project state;
- `tests/`, `scripts/`, `tools/`, `phpstan/`, `phpunit.xml`, `phpstan.neon`;
- `composer.json`, `composer.lock`, PHPUnit/PHPStan or other development dependencies;
- local configuration, cache, export CSVs, temporary files, test fixtures, logs, backups, and database dumps;
- historical implementation notes and old release archives.

The archive must be audited against this whitelist after every rebuild. The vendored runtime dependencies and license files listed above are required and must remain in the ZIP.
