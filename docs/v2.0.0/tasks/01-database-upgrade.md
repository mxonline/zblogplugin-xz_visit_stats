# xz_visit_stats v2.0.0 Task 01

## Database upgrade

Goal: upgrade the v1.3 data structure safely for v2.0.

Requirements:

- Keep all existing v1.x data compatible.
- Do not delete existing tables or fields.
- Add migration detection before executing upgrades.
- Support repeated execution without duplicate changes.

Implementation plan:

1. Detect current plugin database version.
2. Create migration handler.
3. Add new tables only when missing.
4. Record migration result.
5. Verify old access records remain readable.

Expected files:

- inc/upgrade/version.php
- inc/upgrade/checker.php
- inc/upgrade/migrate_2_0.php

Testing:

- Fresh install test.
- Upgrade from v1.3 test.
- Existing statistics query test.
