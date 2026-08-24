# v2.0 Migration Script Task

## Goal
Implement database migration scripts for xz_visit_stats v2.0.

## New Tables

### xz_visit_pages

Store page level statistics.

Fields:
- id
- url
- title
- pv
- uv
- first_visit
- last_visit
- updated

### xz_visit_keywords

Store search keyword information.

Fields:
- id
- engine
- keyword
- referer_url
- landing_url
- count
- updated

### xz_visit_errors

Store HTTP error records.

Fields:
- id
- url
- status
- referer
- ua
- created

### xz_visit_security

Store risk access information.

Fields:
- id
- ip
- ua
- requests
- risk_level
- blocked
- updated

## Rules

- Do not modify existing v1.3 tables directly unless required.
- Migration must be backward compatible.
- Use version checks before execution.
- Validate schema after migration.
