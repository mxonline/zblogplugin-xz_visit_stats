# xz_visit_stats v2.0 Codex Development Execution

## Current Phase
Phase 1: Database upgrade foundation

## Goal
Implement the v2.0 upgrade framework safely on top of v1.3.

## Execution Order

1. Read current plugin structure.
2. Confirm existing database tables and version storage.
3. Create inc/upgrade module.
4. Add version checker.
5. Add migration runner.
6. Add v2.0 table migration scripts.
7. Run compatibility tests.

## Development Rules

- Do not rewrite existing files unnecessarily.
- Keep v1.3 user data compatible.
- Use Z-Blog native database APIs.
- Add migration checks before creating tables.
- Keep every change reviewable.
- Run syntax checks after modifications.

## Files Planned

inc/upgrade/version.php
inc/upgrade/checker.php
inc/upgrade/runner.php
inc/upgrade/migrate_2_0.php

## Acceptance Criteria

- Existing v1.3 installation can upgrade.
- New installation can initialize v2.0 structure.
- Running upgrade twice does not damage data.
- Migration failure does not corrupt existing data.
