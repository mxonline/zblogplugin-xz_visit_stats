# Development Runtime Store

This directory contains the machine-readable execution state for unattended Z-Blog development runs.

Each real run uses:

```text
.development/runtime/DEV-YYYYMMDD-NNN/
  state.json
  events.jsonl
  evidence/index.json
```

`state.json` is the canonical accepted execution snapshot; `events.jsonl` is append-only; `evidence/index.json` stores lightweight evidence references. Real Git, CI, local runtime, release and Notion facts remain authoritative evidence when they conflict with a stored state string.

Legacy `.codex-state.json` and `.codex/tasks.json` are not copied into this store and are not authority for new runs.
