from __future__ import annotations

import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / "scripts" / "dev_runtime.py"
sys.path.insert(0, str(ROOT / "scripts"))
import dev_runtime


class DevRuntimeCliTests(unittest.TestCase):
    def test_generate_execution_id_is_monotonic_per_day(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            first = dev_runtime.generate_execution_id(root, "20260909")
            self.assertEqual(first, "DEV-20260909-001")
            (root / ".development/runtime/DEV-20260909-001").mkdir(parents=True)
            second = dev_runtime.generate_execution_id(root, "20260909")
            self.assertEqual(second, "DEV-20260909-002")

    def test_cli_new_bootstraps_a_cold_resumable_run(self):
        with tempfile.TemporaryDirectory() as tmp:
            cmd = [
                sys.executable,
                str(SCRIPT),
                "new",
                "--root", tmp,
                "--date", "20260909",
                "--project", "xz_visit_stats",
                "--repository", "mxonline/zblogplugin-xz_visit_stats",
                "--current-version", "3.0.0",
                "--target-version", "3.0.1",
                "--task-type", "maintenance",
                "--task-summary", "Unattended runtime",
                "--branch", "feat/runtime",
                "--base-branch", "main",
                "--head-sha", "a" * 40,
                "--time", "2026-09-09T00:00:00Z",
            ]
            result = subprocess.run(cmd, cwd=ROOT, text=True, capture_output=True)
            self.assertEqual(result.returncode, 0, result.stderr)
            payload = json.loads(result.stdout)
            self.assertEqual(payload["execution_id"], "DEV-20260909-001")
            self.assertEqual(payload["action"], "RUN_NOTION_CONTEXT")
            bundle = dev_runtime.load_bundle(Path(tmp), payload["execution_id"])
            self.assertTrue(bundle["validation"]["valid"], bundle["validation"]["conflicts"])
            self.assertEqual(bundle["events"][0]["event"], "RUN_CREATED")

    def test_cli_status_and_evaluate_return_machine_readable_json(self):
        with tempfile.TemporaryDirectory() as tmp:
            state = dev_runtime.new_state(
                execution_id="DEV-20260909-001",
                project="xz_visit_stats",
                repository="mxonline/zblogplugin-xz_visit_stats",
                current_version="3.0.0",
                target_version="3.0.1",
                task_type="maintenance",
                task_summary="Unattended runtime",
                branch="feat/runtime",
                base_branch="main",
                head_sha="a" * 40,
                created_at="2026-09-09T00:00:00Z",
                updated_at="2026-09-09T00:00:00Z",
            )
            dev_runtime.write_snapshot(Path(tmp), state, dev_runtime.new_evidence_index(state))
            for command in ("status", "evaluate", "resume"):
                result = subprocess.run(
                    [sys.executable, str(SCRIPT), command, "--root", tmp, "--execution-id", state["execution_id"]],
                    cwd=ROOT,
                    text=True,
                    capture_output=True,
                )
                self.assertEqual(result.returncode, 0, result.stderr)
                payload = json.loads(result.stdout)
                self.assertEqual(payload["execution_id"], state["execution_id"])
                self.assertIn("action", payload)

    def test_cli_reconcile_detects_real_git_drift_without_mutating_state(self):
        with tempfile.TemporaryDirectory() as tmp:
            state = dev_runtime.new_state(
                execution_id="DEV-20260909-001",
                project="xz_visit_stats",
                repository="mxonline/zblogplugin-xz_visit_stats",
                current_version="3.0.0",
                target_version="3.0.1",
                task_type="maintenance",
                task_summary="Unattended runtime",
                branch="feat/runtime",
                base_branch="main",
                head_sha="a" * 40,
                created_at="2026-09-09T00:00:00Z",
                updated_at="2026-09-09T00:00:00Z",
            )
            dev_runtime.write_snapshot(Path(tmp), state, dev_runtime.new_evidence_index(state))
            result = subprocess.run(
                [
                    sys.executable, str(SCRIPT), "reconcile",
                    "--root", tmp,
                    "--execution-id", state["execution_id"],
                    "--branch", "other",
                    "--head-sha", "b" * 40,
                ],
                cwd=ROOT,
                text=True,
                capture_output=True,
            )
            self.assertEqual(result.returncode, 0, result.stderr)
            payload = json.loads(result.stdout)
            self.assertEqual(payload["action"], "RECONCILE_GIT")
            reloaded = dev_runtime.load_bundle(Path(tmp), state["execution_id"])
            self.assertEqual(reloaded["state"], state)

    def test_cli_resume_can_compare_supplied_real_git_facts(self):
        with tempfile.TemporaryDirectory() as tmp:
            state = dev_runtime.new_state(
                execution_id="DEV-20260909-001",
                project="xz_visit_stats",
                repository="mxonline/zblogplugin-xz_visit_stats",
                current_version="3.0.0",
                target_version="3.0.1",
                task_type="maintenance",
                task_summary="Unattended runtime",
                branch="feat/runtime",
                base_branch="main",
                head_sha="a" * 40,
                created_at="2026-09-09T00:00:00Z",
                updated_at="2026-09-09T00:00:00Z",
            )
            dev_runtime.write_snapshot(Path(tmp), state, dev_runtime.new_evidence_index(state))
            result = subprocess.run(
                [
                    sys.executable, str(SCRIPT), "resume",
                    "--root", tmp,
                    "--execution-id", state["execution_id"],
                    "--branch", "feat/runtime",
                    "--head-sha", "b" * 40,
                ],
                cwd=ROOT,
                text=True,
                capture_output=True,
            )
            self.assertEqual(result.returncode, 0, result.stderr)
            payload = json.loads(result.stdout)
            self.assertEqual(payload["action"], "RECONCILE_GIT")
            self.assertTrue(payload["decision"]["conflicts"])

    def test_cli_git_checkpoint_accepts_new_clean_head_and_invalidates_old_ci(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            state = dev_runtime.new_state(
                execution_id="DEV-20260909-001",
                project="xz_visit_stats",
                repository="mxonline/zblogplugin-xz_visit_stats",
                current_version="3.0.0",
                target_version="3.0.1",
                task_type="maintenance",
                task_summary="Unattended runtime",
                branch="feat/runtime",
                base_branch="main",
                head_sha="a" * 40,
                created_at="2026-09-09T00:00:00Z",
                updated_at="2026-09-09T00:00:00Z",
            )
            evidence = dev_runtime.new_evidence_index(state)
            evidence = dev_runtime.register_evidence(
                evidence,
                evidence_id="ci-old",
                evidence_type="github_ci",
                result="PASS",
                ref="https://example.test/actions/100",
                observed_at="2026-09-09T00:01:00Z",
                sha="a" * 40,
                run_id=100,
            )
            state = dev_runtime.set_ci(
                state,
                status="PASS",
                sha="a" * 40,
                run_id=100,
                evidence_ref="evidence:ci-old",
                updated_at="2026-09-09T00:01:00Z",
            )
            state = dev_runtime.set_gate(
                state,
                gate="github_ci",
                status="PASS",
                evidence_refs=["evidence:ci-old"],
                updated_at="2026-09-09T00:01:01Z",
            )
            dev_runtime.write_snapshot(root, state, evidence)

            result = subprocess.run(
                [
                    sys.executable, str(SCRIPT), "git",
                    "--root", tmp,
                    "--execution-id", state["execution_id"],
                    "--branch", "feat/runtime",
                    "--head-sha", "b" * 40,
                    "--time", "2026-09-09T00:02:00Z",
                ],
                cwd=ROOT,
                text=True,
                capture_output=True,
            )
            self.assertEqual(result.returncode, 0, result.stderr)
            payload = json.loads(result.stdout)
            self.assertEqual(payload["execution_id"], state["execution_id"])
            bundle = dev_runtime.load_bundle(root, state["execution_id"])
            self.assertEqual(bundle["state"]["head_sha"], "b" * 40)
            self.assertEqual(bundle["state"]["ci"]["status"], "PENDING")
            self.assertEqual(bundle["state"]["gates"]["github_ci"], {"status": "PENDING", "evidence": []})
            self.assertEqual(bundle["events"][-1]["event"], "GIT_RECONCILED")


if __name__ == "__main__":
    unittest.main()
