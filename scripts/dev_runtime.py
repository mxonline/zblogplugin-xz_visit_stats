from __future__ import annotations

import copy
import json
import os
import re
from pathlib import Path
from typing import Any

SCHEMA_VERSION = 1
RUN_ID_RE = re.compile(r"^DEV-\d{8}-\d{3}$")
SHA_RE = re.compile(r"^[0-9a-fA-F]{40}$")

STATUSES = {
    "IDENTIFIED",
    "CONTEXT_RESTORED",
    "ANALYZING",
    "PRD_UPDATED",
    "DEVELOPING",
    "TESTING",
    "FIXING",
    "CI_VERIFYING",
    "RELEASE_PREPARING",
    "COMPLETED",
    "BLOCKED",
    "CANCELED",
}

GATE_ORDER = (
    "notion_context",
    "codex_development",
    "local_runtime",
    "github_ci",
    "release_gate",
    "notion_writeback",
)

BASE_GATE_STATUSES = {"PENDING", "PASS", "BLOCKED"}
GATE_STATUSES = {
    "notion_context": BASE_GATE_STATUSES,
    "codex_development": BASE_GATE_STATUSES,
    "local_runtime": BASE_GATE_STATUSES | {"NOT_REQUIRED"},
    "github_ci": BASE_GATE_STATUSES | {"NOT_REQUIRED"},
    "release_gate": BASE_GATE_STATUSES | {"NOT_READY"},
    "notion_writeback": BASE_GATE_STATUSES,
}


def _copy(value: Any) -> Any:
    return copy.deepcopy(value)


def _validate_execution_id(execution_id: str) -> None:
    if not isinstance(execution_id, str) or not RUN_ID_RE.fullmatch(execution_id):
        raise ValueError(f"invalid execution_id: {execution_id!r}")


def runtime_paths(execution_id: str) -> dict[str, str]:
    _validate_execution_id(execution_id)
    base = f".development/runtime/{execution_id}"
    return {
        "state": f"{base}/state.json",
        "events": f"{base}/events.jsonl",
        "evidence": f"{base}/evidence/index.json",
    }


def _pending_gates() -> dict[str, dict[str, Any]]:
    return {name: {"status": "PENDING", "evidence": []} for name in GATE_ORDER}


def new_state(
    *,
    execution_id: str,
    project: str,
    repository: str,
    current_version: str,
    target_version: str,
    task_type: str,
    task_summary: str,
    branch: str,
    base_branch: str,
    head_sha: str,
    created_at: str,
    updated_at: str,
) -> dict[str, Any]:
    _validate_execution_id(execution_id)
    return {
        "schema_version": SCHEMA_VERSION,
        "execution_id": execution_id,
        "project": project,
        "repository": repository,
        "current_version": current_version,
        "target_version": target_version,
        "task_type": task_type,
        "task_summary": task_summary,
        "status": "IDENTIFIED",
        "current_phase": "identified",
        "next_action": "RUN_NOTION_CONTEXT",
        "state_revision": 1,
        "branch": branch,
        "base_branch": base_branch,
        "head_sha": head_sha,
        "pr_number": None,
        "pr_url": None,
        "ci": {
            "status": "PENDING",
            "sha": None,
            "run_id": None,
            "evidence": None,
        },
        "gates": _pending_gates(),
        "blocked": None,
        "notion": {
            "project_ref": None,
            "prd_ref": None,
            "writeback_ref": None,
        },
        "created_at": created_at,
        "updated_at": updated_at,
    }


def new_evidence_index(state: dict[str, Any]) -> dict[str, Any]:
    _validate_execution_id(state["execution_id"])
    return {
        "schema_version": SCHEMA_VERSION,
        "execution_id": state["execution_id"],
        "items": [],
    }


def register_evidence(
    evidence_index: dict[str, Any],
    *,
    evidence_id: str,
    evidence_type: str,
    result: str,
    ref: str,
    observed_at: str,
    sha: str | None = None,
    run_id: int | str | None = None,
    details: Any = None,
) -> dict[str, Any]:
    if not evidence_id or not isinstance(evidence_id, str):
        raise ValueError("evidence_id is required")
    if evidence_id.startswith("evidence:"):
        raise ValueError("evidence_id must not include the evidence: prefix")
    updated = _copy(evidence_index)
    existing = {item.get("id") for item in updated.get("items", [])}
    if evidence_id in existing:
        raise ValueError(f"duplicate evidence id: {evidence_id}")
    item = {
        "id": evidence_id,
        "execution_id": updated.get("execution_id"),
        "type": evidence_type,
        "result": result,
        "ref": ref,
        "observed_at": observed_at,
    }
    if sha is not None:
        item["sha"] = sha
    if run_id is not None:
        item["run_id"] = run_id
    if details is not None:
        item["details"] = details
    updated.setdefault("items", []).append(item)
    return updated


def next_state(
    state: dict[str, Any],
    *,
    status: str | None = None,
    current_phase: str | None = None,
    next_action: str | None = None,
    updated_at: str,
    **fields: Any,
) -> dict[str, Any]:
    updated = _copy(state)
    updated["state_revision"] = int(state.get("state_revision", 0)) + 1
    updated["updated_at"] = updated_at
    if status is not None:
        if status not in STATUSES:
            raise ValueError(f"invalid status: {status}")
        updated["status"] = status
    if current_phase is not None:
        updated["current_phase"] = current_phase
    if next_action is not None:
        updated["next_action"] = next_action
    for key, value in fields.items():
        if key in {"schema_version", "execution_id", "state_revision", "created_at"}:
            raise ValueError(f"immutable field: {key}")
        updated[key] = _copy(value)
    return updated


def set_gate(
    state: dict[str, Any],
    *,
    gate: str,
    status: str,
    evidence_refs: list[str],
    updated_at: str,
) -> dict[str, Any]:
    if gate not in GATE_STATUSES:
        raise ValueError(f"unknown gate: {gate}")
    if status not in GATE_STATUSES[gate]:
        raise ValueError(f"invalid status {status!r} for gate {gate}")
    updated = next_state(state, updated_at=updated_at)
    updated["gates"][gate] = {"status": status, "evidence": list(evidence_refs)}
    return updated


def set_ci(
    state: dict[str, Any],
    *,
    status: str,
    sha: str | None,
    run_id: int | str | None,
    evidence_ref: str | None,
    updated_at: str,
) -> dict[str, Any]:
    updated = next_state(state, updated_at=updated_at)
    updated["ci"] = {
        "status": status,
        "sha": sha,
        "run_id": run_id,
        "evidence": evidence_ref,
    }
    return updated


def new_event(
    state: dict[str, Any],
    *,
    seq: int,
    event: str,
    phase: str,
    time: str,
    data: dict[str, Any] | None = None,
) -> dict[str, Any]:
    return {
        "seq": seq,
        "execution_id": state["execution_id"],
        "state_revision": state["state_revision"],
        "event": event,
        "phase": phase,
        "time": time,
        "data": {} if data is None else _copy(data),
    }


def _evidence_map(evidence_index: dict[str, Any]) -> dict[str, dict[str, Any]]:
    result: dict[str, dict[str, Any]] = {}
    for item in evidence_index.get("items", []):
        evidence_id = item.get("id")
        if isinstance(evidence_id, str):
            result[evidence_id] = item
    return result


def _resolve_evidence_ref(ref: Any, evidence: dict[str, dict[str, Any]]) -> bool:
    if not isinstance(ref, str) or not ref.startswith("evidence:"):
        return False
    evidence_id = ref.split(":", 1)[1]
    return bool(evidence_id) and evidence_id in evidence


def validate_bundle(
    state: dict[str, Any],
    evidence_index: dict[str, Any],
    events: list[dict[str, Any]] | None = None,
) -> dict[str, Any]:
    conflicts: list[str] = []
    warnings: list[str] = []
    events = [] if events is None else events

    execution_id = state.get("execution_id")
    try:
        _validate_execution_id(execution_id)
    except (ValueError, TypeError):
        conflicts.append("state.execution_id is invalid")

    if state.get("schema_version") != SCHEMA_VERSION:
        conflicts.append("state.schema_version must be 1")
    if state.get("status") not in STATUSES:
        conflicts.append("state.status is invalid")
    if not isinstance(state.get("state_revision"), int) or state.get("state_revision", 0) < 1:
        conflicts.append("state.state_revision must be >= 1")
    if not isinstance(state.get("head_sha"), str) or not SHA_RE.fullmatch(state.get("head_sha", "")):
        conflicts.append("state.head_sha must be a 40-character Git SHA")

    required_text = (
        "project",
        "repository",
        "current_version",
        "target_version",
        "task_type",
        "task_summary",
        "current_phase",
        "next_action",
        "branch",
        "base_branch",
        "created_at",
        "updated_at",
    )
    for field in required_text:
        if not isinstance(state.get(field), str) or not state.get(field):
            conflicts.append(f"state.{field} is required")

    if evidence_index.get("schema_version") != SCHEMA_VERSION:
        conflicts.append("evidence.schema_version must be 1")
    if evidence_index.get("execution_id") != execution_id:
        conflicts.append("evidence.execution_id does not match state.execution_id")
    items = evidence_index.get("items")
    if not isinstance(items, list):
        conflicts.append("evidence.items must be a list")
        items = []

    seen_ids: set[str] = set()
    for item in items:
        if not isinstance(item, dict):
            conflicts.append("evidence item must be an object")
            continue
        evidence_id = item.get("id")
        if not isinstance(evidence_id, str) or not evidence_id:
            conflicts.append("evidence item id is required")
            continue
        if evidence_id in seen_ids:
            conflicts.append(f"duplicate evidence id: {evidence_id}")
        seen_ids.add(evidence_id)
        if item.get("execution_id") != execution_id:
            conflicts.append(f"evidence {evidence_id} execution_id mismatch")
        for field in ("type", "result", "ref", "observed_at"):
            if not item.get(field):
                conflicts.append(f"evidence {evidence_id} missing {field}")

    evidence = _evidence_map(evidence_index)

    gates = state.get("gates")
    if not isinstance(gates, dict) or set(gates) != set(GATE_ORDER):
        conflicts.append("state.gates must contain exactly the six full-development gates")
        gates = {} if not isinstance(gates, dict) else gates
    for gate in GATE_ORDER:
        value = gates.get(gate)
        if not isinstance(value, dict):
            conflicts.append(f"gate {gate} must be an object")
            continue
        gate_status = value.get("status")
        if gate_status not in GATE_STATUSES[gate]:
            conflicts.append(f"gate {gate} has invalid status {gate_status!r}")
        refs = value.get("evidence")
        if not isinstance(refs, list):
            conflicts.append(f"gate {gate} evidence must be a list")
            refs = []
        if gate_status != "PENDING" and not refs:
            conflicts.append(f"gate {gate} status {gate_status} requires evidence")
        for ref in refs:
            if not _resolve_evidence_ref(ref, evidence):
                conflicts.append(f"gate {gate} references missing evidence {ref}")

    ci = state.get("ci")
    if not isinstance(ci, dict):
        conflicts.append("state.ci must be an object")
    else:
        ci_ref = ci.get("evidence")
        if ci_ref is not None and not _resolve_evidence_ref(ci_ref, evidence):
            conflicts.append(f"ci references missing evidence {ci_ref}")
        if ci.get("status") == "PASS" and ci.get("sha") is not None:
            if not isinstance(ci.get("sha"), str) or not SHA_RE.fullmatch(ci.get("sha", "")):
                conflicts.append("ci.sha must be a 40-character Git SHA")

    blocked = state.get("blocked")
    if state.get("status") == "BLOCKED":
        if not isinstance(blocked, dict):
            conflicts.append("BLOCKED state requires blocked details")
        else:
            for field in ("blocked_at", "reason", "evidence", "next_action"):
                if not blocked.get(field):
                    conflicts.append(f"blocked.{field} is required")
            blocked_refs = blocked.get("evidence", [])
            if isinstance(blocked_refs, list):
                for ref in blocked_refs:
                    if not _resolve_evidence_ref(ref, evidence):
                        conflicts.append(f"blocked references missing evidence {ref}")

    expected_seq = 1
    for event in events:
        if not isinstance(event, dict):
            conflicts.append("event must be an object")
            continue
        if event.get("seq") != expected_seq:
            conflicts.append(f"event seq mismatch: expected {expected_seq}, got {event.get('seq')}")
            expected_seq = int(event.get("seq", expected_seq)) + 1 if isinstance(event.get("seq"), int) else expected_seq + 1
        else:
            expected_seq += 1
        if event.get("execution_id") != execution_id:
            conflicts.append(f"event seq {event.get('seq')} execution_id mismatch")
        revision = event.get("state_revision")
        if not isinstance(revision, int) or revision < 1 or revision > state.get("state_revision", 0):
            conflicts.append(f"event seq {event.get('seq')} state_revision is invalid")

    return {"valid": not conflicts, "conflicts": conflicts, "warnings": warnings}


def _absolute_paths(root: Path, execution_id: str) -> dict[str, Path]:
    relative = runtime_paths(execution_id)
    return {name: root / path for name, path in relative.items()}


def _atomic_write_json(path: Path, value: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp = path.with_name(path.name + ".tmp")
    payload = json.dumps(value, ensure_ascii=False, indent=2) + "\n"
    tmp.write_text(payload, encoding="utf-8")
    os.replace(tmp, path)


def _read_json(path: Path) -> Any:
    return json.loads(path.read_text(encoding="utf-8"))


def _read_events(path: Path) -> list[dict[str, Any]]:
    if not path.exists():
        return []
    events: list[dict[str, Any]] = []
    for line in path.read_text(encoding="utf-8").splitlines():
        if line.strip():
            events.append(json.loads(line))
    return events


def write_snapshot(root: Path | str, state: dict[str, Any], evidence_index: dict[str, Any]) -> dict[str, str]:
    root_path = Path(root)
    paths = _absolute_paths(root_path, state["execution_id"])
    events = _read_events(paths["events"])
    validation = validate_bundle(state, evidence_index, events)
    if not validation["valid"]:
        raise ValueError("invalid runtime bundle: " + "; ".join(validation["conflicts"]))
    _atomic_write_json(paths["state"], state)
    _atomic_write_json(paths["evidence"], evidence_index)
    paths["events"].parent.mkdir(parents=True, exist_ok=True)
    if not paths["events"].exists():
        paths["events"].touch()
    return runtime_paths(state["execution_id"])


def append_event(root: Path | str, execution_id: str, event: dict[str, Any]) -> None:
    _validate_execution_id(execution_id)
    root_path = Path(root)
    paths = _absolute_paths(root_path, execution_id)
    state = _read_json(paths["state"])
    evidence_index = _read_json(paths["evidence"])
    events = _read_events(paths["events"])
    expected_seq = len(events) + 1
    if event.get("seq") != expected_seq:
        raise ValueError(f"event seq must be {expected_seq}")
    if event.get("execution_id") != execution_id or state.get("execution_id") != execution_id:
        raise ValueError("event execution_id does not match runtime")
    revision = event.get("state_revision")
    if not isinstance(revision, int) or revision < 1 or revision > state.get("state_revision", 0):
        raise ValueError("event state_revision is invalid")
    candidate = events + [_copy(event)]
    validation = validate_bundle(state, evidence_index, candidate)
    if not validation["valid"]:
        raise ValueError("invalid event: " + "; ".join(validation["conflicts"]))
    with paths["events"].open("a", encoding="utf-8", newline="\n") as handle:
        handle.write(json.dumps(event, ensure_ascii=False, separators=(",", ":")) + "\n")


def load_bundle(root: Path | str, execution_id: str) -> dict[str, Any]:
    _validate_execution_id(execution_id)
    paths = _absolute_paths(Path(root), execution_id)
    state = _read_json(paths["state"])
    evidence_index = _read_json(paths["evidence"])
    events = _read_events(paths["events"])
    validation = validate_bundle(state, evidence_index, events)
    return {
        "state": state,
        "evidence_index": evidence_index,
        "events": events,
        "validation": validation,
        "paths": runtime_paths(execution_id),
    }


def reconcile_git(state: dict[str, Any], *, branch: str, head_sha: str, dirty: bool) -> dict[str, Any]:
    conflicts: list[str] = []
    if branch != state.get("branch"):
        conflicts.append(f"branch drift: state={state.get('branch')} actual={branch}")
    if head_sha != state.get("head_sha"):
        conflicts.append(f"head drift: state={state.get('head_sha')} actual={head_sha}")
    return {
        "action": "RECONCILE_GIT" if conflicts else "OK",
        "conflicts": conflicts,
        "dirty": bool(dirty),
    }


def _ci_is_fresh(state: dict[str, Any], evidence_index: dict[str, Any]) -> bool:
    ci = state.get("ci", {})
    if ci.get("status") != "PASS" or ci.get("sha") != state.get("head_sha"):
        return False
    ci_ref = ci.get("evidence")
    evidence = _evidence_map(evidence_index)
    if not _resolve_evidence_ref(ci_ref, evidence):
        return False
    item = evidence[ci_ref.split(":", 1)[1]]
    if item.get("result") != "PASS":
        return False
    item_sha = item.get("sha")
    return item_sha == state.get("head_sha")


def evaluate_next_action(state: dict[str, Any], evidence_index: dict[str, Any]) -> dict[str, Any]:
    validation = validate_bundle(state, evidence_index, [])
    if not validation["valid"]:
        return {"action": "VERIFY_RUNTIME_STATE", "reasons": validation["conflicts"]}

    if state.get("status") == "BLOCKED":
        return {"action": "BLOCKED", "reasons": [state.get("blocked", {}).get("reason", "blocked")]}

    gates = state["gates"]
    for gate in GATE_ORDER:
        status = gates[gate]["status"]
        if status == "BLOCKED":
            return {"action": "BLOCKED", "reasons": [f"{gate} is BLOCKED"]}
        if gate == "notion_context" and status != "PASS":
            return {"action": "RUN_NOTION_CONTEXT", "gate": gate}
        if gate == "codex_development" and status != "PASS":
            return {"action": "RUN_CODEX_DEVELOPMENT", "gate": gate}
        if gate == "local_runtime" and status not in {"PASS", "NOT_REQUIRED"}:
            return {"action": "RUN_LOCAL_RUNTIME", "gate": gate}
        if gate == "github_ci":
            if status == "PASS" and not _ci_is_fresh(state, evidence_index):
                return {"action": "RUN_GITHUB_CI", "gate": gate, "reason": "CI evidence is stale for current head"}
            if status not in {"PASS", "NOT_REQUIRED"}:
                return {"action": "RUN_GITHUB_CI", "gate": gate}
        if gate == "release_gate" and status not in {"PASS", "NOT_READY"}:
            return {"action": "RUN_RELEASE_GATE", "gate": gate}
        if gate == "notion_writeback" and status != "PASS":
            return {"action": "RUN_NOTION_WRITEBACK", "gate": gate}

    return {"action": "COMPLETE"}


def evaluate_completion(state: dict[str, Any], evidence_index: dict[str, Any]) -> dict[str, Any]:
    validation = validate_bundle(state, evidence_index, [])
    if not validation["valid"]:
        return {"final": "INCOMPLETE", "release": "NOT RELEASED", "reasons": validation["conflicts"]}

    decision = evaluate_next_action(state, evidence_index)
    final = "COMPLETE" if decision["action"] == "COMPLETE" else "INCOMPLETE"
    release = "NOT RELEASED"

    if final == "COMPLETE" and state["gates"]["release_gate"]["status"] == "PASS":
        successful_types = {
            item.get("type")
            for item in evidence_index.get("items", [])
            if item.get("result") == "PASS"
        }
        if {"git_tag", "github_release", "release_zip"}.issubset(successful_types):
            release = "RELEASED"

    reasons = [] if final == "COMPLETE" else decision.get("reasons", [decision.get("action")])
    return {"final": final, "release": release, "reasons": reasons}
