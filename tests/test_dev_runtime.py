from __future__ import annotations

import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCRIPTS = ROOT / "scripts"
sys.path.insert(0, str(SCRIPTS))

try:
    import dev_runtime
except ModuleNotFoundError as exc:  # RED until the runtime exists
    raise AssertionError("scripts/dev_runtime.py must implement the canonical development runtime") from exc


class DevRuntimeContractTests(unittest.TestCase):
    def new_state(self, **overrides):
        args = {
            "execution_id": "DEV-20260909-001",
            "project": "xz_visit_stats",
            "repository": "mxonline/zblogplugin-xz_visit_stats",
            "current_version": "3.0.0",
            "target_version": "3.0.1",
            "task_type": "maintenance",
            "task_summary": "Harden unattended development runtime",
            "branch": "feat/unattended-dev-runtime-20260909",
            "base_branch": "main",
            "head_sha": "a" * 40,
            "created_at": "2026-09-09T00:00:00Z",
            "updated_at": "2026-09-09T00:00:00Z",
        }
        args.update(overrides)
        return dev_runtime.new_state(**args)

    def register(self, index, evidence_id, evidence_type, *, result="PASS", ref=None, sha=None, run_id=None):
        return dev_runtime.register_evidence(
            index,
            evidence_id=evidence_id,
            evidence_type=evidence_type,
            result=result,
            ref=ref or f"test://{evidence_id}",
            observed_at="2026-09-09T00:01:00Z",
            sha=sha,
            run_id=run_id,
        )

    def gate(self, state, gate, status, evidence_id, *, updated_at="2026-09-09T00:02:00Z"):
        return dev_runtime.set_gate(
            state,
            gate=gate,
            status=status,
            evidence_refs=[f"evidence:{evidence_id}"],
            updated_at=updated_at,
        )

    def test_paths_are_version_independent_and_reject_traversal(self):
        paths = dev_runtime.runtime_paths("DEV-20260909-001")
        self.assertEqual(paths["state"], ".development/runtime/DEV-20260909-001/state.json")
        self.assertEqual(paths["events"], ".development/runtime/DEV-20260909-001/events.jsonl")
        self.assertEqual(paths["evidence"], ".development/runtime/DEV-20260909-001/evidence/index.json")
        for bad in ("../escape", "DEV-20260909-001/../../x", "not-a-run"):
            with self.assertRaises(ValueError):
                dev_runtime.runtime_paths(bad)

    def test_new_state_has_stable_run_identity_revision_and_exact_six_gates(self):
        state = self.new_state()
        self.assertEqual(state["schema_version"], 1)
        self.assertEqual(state["execution_id"], "DEV-20260909-001")
        self.assertEqual(state["state_revision"], 1)
        self.assertEqual(state["status"], "IDENTIFIED")
        self.assertEqual(
            set(state["gates"]),
            {"notion_context", "codex_development", "local_runtime", "github_ci", "release_gate", "notion_writeback"},
        )
        self.assertTrue(all(gate["status"] == "PENDING" for gate in state["gates"].values()))

    def test_transition_preserves_execution_id_and_increments_revision(self):
        state = self.new_state()
        changed = dev_runtime.next_state(
            state,
            status="DEVELOPING",
            current_phase="implementation",
            next_action="RUN_CODEX_DEVELOPMENT",
            updated_at="2026-09-09T00:03:00Z",
        )
        self.assertEqual(changed["execution_id"], state["execution_id"])
        self.assertEqual(changed["state_revision"], 2)
        self.assertEqual(changed["status"], "DEVELOPING")

    def test_pass_gate_without_resolvable_evidence_is_invalid(self):
        state = self.new_state()
        state = self.gate(state, "notion_context", "PASS", "notion-context")
        evidence = dev_runtime.new_evidence_index(state)
        result = dev_runtime.validate_bundle(state, evidence, [])
        self.assertFalse(result["valid"])
        self.assertTrue(any("notion-context" in conflict for conflict in result["conflicts"]))

    def test_evidence_from_another_execution_is_invalid(self):
        state = self.new_state()
        evidence = dev_runtime.new_evidence_index(state)
        evidence["execution_id"] = "DEV-20260909-999"
        result = dev_runtime.validate_bundle(state, evidence, [])
        self.assertFalse(result["valid"])
        self.assertTrue(any("execution_id" in conflict for conflict in result["conflicts"]))

    def test_event_sequence_and_revision_are_execution_aware(self):
        state = self.new_state()
        evidence = dev_runtime.new_evidence_index(state)
        events = [
            dev_runtime.new_event(state, seq=1, event="RUN_CREATED", phase="bootstrap", time="2026-09-09T00:00:01Z"),
            dev_runtime.new_event(state, seq=3, event="BAD_GAP", phase="bootstrap", time="2026-09-09T00:00:02Z"),
        ]
        result = dev_runtime.validate_bundle(state, evidence, events)
        self.assertFalse(result["valid"])
        self.assertTrue(any("seq" in conflict for conflict in result["conflicts"]))

    def test_snapshot_roundtrip_and_append_only_events_survive_cold_resume(self):
        state = self.new_state()
        evidence = dev_runtime.new_evidence_index(state)
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            dev_runtime.write_snapshot(root, state, evidence)
            event1 = dev_runtime.new_event(state, seq=1, event="RUN_CREATED", phase="bootstrap", time="2026-09-09T00:00:01Z")
            event2 = dev_runtime.new_event(state, seq=2, event="CONTEXT_REQUESTED", phase="context", time="2026-09-09T00:00:02Z")
            dev_runtime.append_event(root, state["execution_id"], event1)
            dev_runtime.append_event(root, state["execution_id"], event2)
            loaded = dev_runtime.load_bundle(root, state["execution_id"])
            self.assertTrue(loaded["validation"]["valid"], loaded["validation"]["conflicts"])
            self.assertEqual(loaded["state"], state)
            self.assertEqual([event["seq"] for event in loaded["events"]], [1, 2])
            event_path = root / dev_runtime.runtime_paths(state["execution_id"])["events"]
            self.assertEqual(len(event_path.read_text(encoding="utf-8").splitlines()), 2)

    def test_append_event_rejects_wrong_run_and_non_monotonic_seq(self):
        state = self.new_state()
        evidence = dev_runtime.new_evidence_index(state)
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            dev_runtime.write_snapshot(root, state, evidence)
            first = dev_runtime.new_event(state, seq=1, event="RUN_CREATED", phase="bootstrap", time="2026-09-09T00:00:01Z")
            dev_runtime.append_event(root, state["execution_id"], first)
            with self.assertRaises(ValueError):
                dev_runtime.append_event(root, state["execution_id"], first)
            wrong = dict(first)
            wrong["seq"] = 2
            wrong["execution_id"] = "DEV-20260909-999"
            with self.assertRaises(ValueError):
                dev_runtime.append_event(root, state["execution_id"], wrong)

    def test_resume_routes_gates_in_existing_six_gate_order(self):
        state = self.new_state()
        evidence = dev_runtime.new_evidence_index(state)
        self.assertEqual(dev_runtime.evaluate_next_action(state, evidence)["action"], "RUN_NOTION_CONTEXT")

        evidence = self.register(evidence, "notion", "notion_context")
        state = self.gate(state, "notion_context", "PASS", "notion")
        self.assertEqual(dev_runtime.evaluate_next_action(state, evidence)["action"], "RUN_CODEX_DEVELOPMENT")

        evidence = self.register(evidence, "codex", "codex_development")
        state = self.gate(state, "codex_development", "PASS", "codex")
        self.assertEqual(dev_runtime.evaluate_next_action(state, evidence)["action"], "RUN_LOCAL_RUNTIME")

    def test_stale_ci_pass_is_not_reused_for_new_head(self):
        state = self.new_state(head_sha="b" * 40)
        evidence = dev_runtime.new_evidence_index(state)
        for evidence_id, evidence_type, gate, status in (
            ("notion", "notion_context", "notion_context", "PASS"),
            ("codex", "codex_development", "codex_development", "PASS"),
            ("runtime", "local_runtime", "local_runtime", "PASS"),
        ):
            evidence = self.register(evidence, evidence_id, evidence_type)
            state = self.gate(state, gate, status, evidence_id)

        evidence = self.register(evidence, "ci-old", "github_ci", sha="a" * 40, run_id=100)
        state = dev_runtime.set_ci(
            state,
            status="PASS",
            sha="a" * 40,
            run_id=100,
            evidence_ref="evidence:ci-old",
            updated_at="2026-09-09T00:04:00Z",
        )
        state = self.gate(state, "github_ci", "PASS", "ci-old")
        decision = dev_runtime.evaluate_next_action(state, evidence)
        self.assertEqual(decision["action"], "RUN_GITHUB_CI")

    def test_git_reconcile_detects_branch_or_head_drift(self):
        state = self.new_state()
        ok = dev_runtime.reconcile_git(state, branch=state["branch"], head_sha=state["head_sha"], dirty=False)
        self.assertEqual(ok["action"], "OK")
        drift = dev_runtime.reconcile_git(state, branch="other-branch", head_sha="f" * 40, dirty=False)
        self.assertEqual(drift["action"], "RECONCILE_GIT")
        self.assertTrue(drift["conflicts"])

    def test_complete_allows_release_not_ready_but_requires_notion_writeback(self):
        state = self.new_state()
        evidence = dev_runtime.new_evidence_index(state)
        gate_specs = [
            ("notion_context", "notion", "notion_context", "PASS"),
            ("codex_development", "codex", "codex_development", "PASS"),
            ("local_runtime", "runtime-na", "local_runtime", "NOT_REQUIRED"),
            ("github_ci", "ci", "github_ci", "PASS"),
            ("release_gate", "release-check", "release_gate", "NOT_READY"),
            ("notion_writeback", "writeback", "notion_writeback", "PASS"),
        ]
        for gate, evidence_id, evidence_type, status in gate_specs:
            sha = state["head_sha"] if gate == "github_ci" else None
            run_id = 101 if gate == "github_ci" else None
            evidence = self.register(evidence, evidence_id, evidence_type, sha=sha, run_id=run_id)
            if gate == "github_ci":
                state = dev_runtime.set_ci(
                    state,
                    status="PASS",
                    sha=state["head_sha"],
                    run_id=101,
                    evidence_ref="evidence:ci",
                    updated_at="2026-09-09T00:05:00Z",
                )
            state = self.gate(state, gate, status, evidence_id)

        completion = dev_runtime.evaluate_completion(state, evidence)
        self.assertEqual(completion["final"], "COMPLETE")
        self.assertEqual(completion["release"], "NOT RELEASED")
        self.assertEqual(dev_runtime.evaluate_next_action(state, evidence)["action"], "COMPLETE")

        missing_writeback = json.loads(json.dumps(state))
        missing_writeback["gates"]["notion_writeback"] = {"status": "PENDING", "evidence": []}
        completion = dev_runtime.evaluate_completion(missing_writeback, evidence)
        self.assertEqual(completion["final"], "INCOMPLETE")

    def test_release_released_requires_tag_release_and_zip_evidence(self):
        state = self.new_state()
        evidence = dev_runtime.new_evidence_index(state)
        for gate, evidence_id, evidence_type, status in (
            ("notion_context", "notion", "notion_context", "PASS"),
            ("codex_development", "codex", "codex_development", "PASS"),
            ("local_runtime", "runtime", "local_runtime", "PASS"),
            ("github_ci", "ci", "github_ci", "PASS"),
            ("release_gate", "release-gate", "release_gate", "PASS"),
            ("notion_writeback", "writeback", "notion_writeback", "PASS"),
        ):
            evidence = self.register(
                evidence,
                evidence_id,
                evidence_type,
                sha=state["head_sha"] if gate == "github_ci" else None,
                run_id=200 if gate == "github_ci" else None,
            )
            if gate == "github_ci":
                state = dev_runtime.set_ci(state, status="PASS", sha=state["head_sha"], run_id=200, evidence_ref="evidence:ci", updated_at="2026-09-09T00:06:00Z")
            state = self.gate(state, gate, status, evidence_id)

        self.assertEqual(dev_runtime.evaluate_completion(state, evidence)["release"], "NOT RELEASED")
        for evidence_id, evidence_type in (("tag", "git_tag"), ("release", "github_release"), ("zip", "release_zip")):
            evidence = self.register(evidence, evidence_id, evidence_type)
        self.assertEqual(dev_runtime.evaluate_completion(state, evidence)["release"], "RELEASED")


if __name__ == "__main__":
    unittest.main()
