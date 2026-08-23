# xz_visit_stats v1.3 Codex project rules

## Current project state
- Project: Z-BlogPHP plugin `xz_visit_stats`
- Target version: v1.3.0
- Current branch: `feature/visit-stats-1.3`
- v1.2 is the behavioral baseline and source of truth.

## Already completed in this branch
1. Five analysis pages were changed to a compact common filter layout.
2. Common filter layout now uses "basic filters + advanced filters".
3. Advanced filters can expand/collapse.
4. Existing GET parameter names, query logic, and database structure were preserved.
5. Privacy-setting bug was fixed:
   - root cause: radio inputs incorrectly used `selected="selected"`
   - radios now use `checked="checked"`
   - settings save/read logic was confirmed unchanged
   - collector still uses `ip_mode`
   - `full` stores full IP
   - `masked` stores masked IP for new records only
6. `git diff --check` passed.
7. PHP CLI was unavailable in the prior Codex environment, so PHP syntax/runtime checks still need to be done when a PHP executable is available.

## Do not redo completed work
- Do not redesign the compact filter UI from scratch.
- Do not rewrite the privacy setting logic unless verification proves there is still a defect.
- Do not change the database schema without an explicit task requiring it.
- Do not rename existing GET parameters, form names, DB fields, or public functions.
- Do not make unrelated refactors.

## Development priorities
1. Functional correctness
2. Fast/safe iteration
3. Backward compatibility
4. Performance
5. UI consistency
6. Minimal, human-readable code

## Fast verification policy
Run only checks relevant to the current task:
- `git diff --check`
- PHP syntax check on changed PHP files if PHP CLI exists
- JavaScript syntax check if Node exists
- existing lightweight tests if present and fast
- local browser/database verification when the task needs runtime confirmation
- PHPStan is non-blocking and should not be allowed to slow down feature work

## Git safety
- Stay on `feature/visit-stats-1.3`.
- Do not create/switch branches.
- Do not commit, push, merge, tag, or release unless the task explicitly says to do so.
- Inspect the current diff before editing because earlier v1.3 work is already present.

## Completion report format
For every task report:
- current finding / root cause
- files changed
- exact change
- checks executed and results
- browser/runtime verification still needed
- remaining risks
