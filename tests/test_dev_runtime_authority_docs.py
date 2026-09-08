from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]


class DevRuntimeAuthorityDocsTests(unittest.TestCase):
    def test_runtime_contract_document_exists_and_names_canonical_bundle(self):
        path = ROOT / "docs" / "DEVELOPMENT-RUNTIME.md"
        self.assertTrue(path.is_file(), "docs/DEVELOPMENT-RUNTIME.md must define the runtime authority")
        text = path.read_text(encoding="utf-8")
        for token in (
            ".development/runtime/<execution_id>/state.json",
            "events.jsonl",
            "evidence/index.json",
            "DEV-YYYYMMDD-NNN",
            "Notion Context",
            "Codex Development",
            "Local Runtime",
            "GitHub CI",
            "Release Gate",
            "Notion Writeback",
            "VERIFY_RUNTIME_STATE",
        ):
            self.assertIn(token, text)

    def test_agents_routes_new_runs_through_canonical_runtime_not_legacy_state(self):
        text = (ROOT / "AGENTS.md").read_text(encoding="utf-8")
        self.assertIn(".development/runtime/<execution_id>/state.json", text)
        self.assertIn("scripts/dev_runtime.py", text)
        self.assertIn("scripts/dev-flow.ps1", text)
        self.assertIn(".codex-state.json", text)
        self.assertIn("legacy", text.lower())
        self.assertNotIn("`.codex-state.json` 作为当前开发运行的权威", text)

    def test_development_guide_loads_runtime_before_resuming_work(self):
        text = (ROOT / "docs" / "DEVELOPMENT.md").read_text(encoding="utf-8")
        self.assertIn("docs/DEVELOPMENT-RUNTIME.md", text)
        self.assertIn("DEV-YYYYMMDD-NNN", text)
        self.assertIn("resume", text.lower())
        self.assertIn(".development/runtime", text)

    def test_automation_readme_marks_v13_runner_as_legacy_compatibility_only(self):
        text = (ROOT / "README-AUTOMATION.md").read_text(encoding="utf-8")
        self.assertIn("scripts/dev-flow.ps1", text)
        self.assertIn(".development/runtime", text)
        self.assertIn("dev-v1.3.ps1", text)
        self.assertIn("legacy", text.lower())
        self.assertIn("canonical", text.lower())

    def test_codex_workflow_uses_single_runtime_contract(self):
        text = (ROOT / ".codex" / "workflow.md").read_text(encoding="utf-8")
        self.assertIn("scripts/dev_runtime.py", text)
        self.assertIn(".development/runtime", text)
        self.assertIn("Notion Writeback", text)
        self.assertIn("Release Gate", text)


if __name__ == "__main__":
    unittest.main()
