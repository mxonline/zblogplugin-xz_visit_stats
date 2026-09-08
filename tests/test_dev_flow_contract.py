from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
FLOW = ROOT / "scripts" / "dev-flow.ps1"


class DevFlowContractTests(unittest.TestCase):
    def test_version_independent_flow_adapter_exists(self):
        self.assertTrue(FLOW.is_file(), "scripts/dev-flow.ps1 must be the version-independent unattended entry")

    def test_adapter_delegates_to_one_python_runtime_contract(self):
        text = FLOW.read_text(encoding="utf-8")
        self.assertIn("dev_runtime.py", text)
        self.assertIn(".development", text)
        for action in ("new", "status", "resume", "transition", "evidence", "gate", "reconcile", "evaluate"):
            self.assertIn(action, text.lower())

    def test_adapter_is_not_hardcoded_to_legacy_v13_or_manual_approval(self):
        text = FLOW.read_text(encoding="utf-8")
        self.assertNotIn("feature/visit-stats-1.3", text)
        self.assertNotIn("dev-v1.3.ps1", text)
        self.assertNotIn("approve", text.lower())


if __name__ == "__main__":
    unittest.main()
