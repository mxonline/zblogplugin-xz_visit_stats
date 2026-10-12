# v4.0.0 candidate ZIP manifest

Candidate file: `dist/xz_visit_stats-v4.0.0.zip`
Archive root: `xz_visit_stats/`

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
