# v2.0 Upgrade System Task

## Goal
Create a safe upgrade framework for xz_visit_stats v1.3 to v2.0.

## Requirements

- Detect current plugin database version.
- Detect missing tables and fields.
- Execute migration only once.
- Keep v1.3 existing data available.
- Record migration result.
- Provide rollback-safe checks.

## Implementation Plan

Create:

```
inc/upgrade/
├── version.php
├── checker.php
├── runner.php
└── migrate_2_0.php
```

## Validation

- Fresh install test.
- Upgrade from v1.3 test.
- Repeated upgrade execution test.
- Database compatibility test.
