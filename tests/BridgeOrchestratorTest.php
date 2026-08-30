<?php

use PHPUnit\Framework\TestCase;

final class BridgeOrchestratorTest extends TestCase
{
    public function testT4CannotExecuteReleaseEvenIfModelRequestsIt(): void
    {
        $orchestrator = $this->newOrchestrator();
        $state = $this->baseState();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Release is not allowed from T4');
        $orchestrator->applyDecision($state, $this->decision('EXECUTE_RELEASE'));
    }

    public function testT4AdvancesToT5OnlyWhenRequiredGatesPassed(): void
    {
        $orchestrator = $this->newOrchestrator();
        $state = $this->baseState();
        $state['phase_gates'] = array(
            'CODEX_DEVELOPMENT' => 'PASS',
            'LOCAL_RUNTIME' => 'PASS',
            'GITHUB_CI' => 'PASS',
        );
        $state['release_gate'] = 'NOT READY';

        $next = $orchestrator->applyDecision($state, $this->decision('ADVANCE_PHASE'));

        $this->assertSame('T5_FINAL_VERIFICATION_RELEASE_PREP', $next['current_phase']);
        $this->assertSame('.codex-tasks/09-v4-t5-final-release.md', $next['current_task']);
        $this->assertSame('RESUME_GATE', $next['current_stage']);
    }

    public function testRepairFingerprintBlocksAfterThreeNoProgressRepeats(): void
    {
        $orchestrator = $this->newOrchestrator();
        $state = $this->baseState();
        $decision = $this->decision('REPAIR');
        $failure = array('gate' => 'PHPUNIT', 'fingerprint' => 'same-failure', 'summary' => 'same assertion failed');

        $state = $orchestrator->applyDecision($state, $decision, $failure);
        $this->assertSame('REPAIR', $state['next_action']);
        $state = $orchestrator->applyDecision($state, $decision, $failure);
        $this->assertTrue($state['broader_diagnosis_required']);
        $state = $orchestrator->applyDecision($state, $decision, $failure);

        $this->assertSame('BLOCKED', $state['status']);
        $this->assertSame('REPEATED_NO_PROGRESS', $state['blocked_reason']);
        $this->assertSame(3, $state['repair_round']);
    }

    public function testCompleteRequiresRealReleaseAndNotionWriteback(): void
    {
        $orchestrator = $this->newOrchestrator();
        $state = $this->baseState();
        $state['current_phase'] = 'RELEASE';
        $state['release_gate'] = 'PASS';
        $state['release_state'] = 'NOT RELEASED';
        $state['notion_state'] = 'PENDING';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('COMPLETE requires verified release and Notion writeback');
        $orchestrator->applyDecision($state, $this->decision('COMPLETE'));
    }

    public function testCompleteIsAcceptedOnlyWithReleaseEvidenceAndNotionPass(): void
    {
        $orchestrator = $this->newOrchestrator();
        $state = $this->baseState();
        $state['current_phase'] = 'RELEASE';
        $state['release_gate'] = 'PASS';
        $state['release_state'] = 'RELEASED';
        $state['notion_state'] = 'PASS';
        $state['release_evidence'] = array(
            'tag' => 'v4.0.0',
            'github_release' => 'https://github.test/release/v4.0.0',
            'zip' => 'xz_visit_stats-v4.0.0.zip',
            'sha256' => str_repeat('a', 64),
        );

        $next = $orchestrator->applyDecision($state, $this->decision('COMPLETE'));
        $this->assertSame('COMPLETE', $next['status']);
        $this->assertSame('COMPLETE', $next['current_stage']);
    }

    private function newOrchestrator(): object
    {
        $file = dirname(__DIR__) . '/bridge/src/BridgeOrchestrator.php';
        $this->assertFileExists($file, 'BridgeOrchestrator must exist before this contract can pass.');
        require_once $file;
        return new \XzVisitStats\Bridge\BridgeOrchestrator(
            array(
                'T4_ANALYTICS_ADMIN' => array(
                    'task' => '.codex-tasks/08-v4-t4-analytics-admin.md',
                    'next' => 'T5_FINAL_VERIFICATION_RELEASE_PREP',
                    'release_allowed' => false,
                    'required_gates' => array('CODEX_DEVELOPMENT', 'LOCAL_RUNTIME', 'GITHUB_CI'),
                ),
                'T5_FINAL_VERIFICATION_RELEASE_PREP' => array(
                    'task' => '.codex-tasks/09-v4-t5-final-release.md',
                    'next' => 'RELEASE',
                    'release_allowed' => false,
                    'required_gates' => array('CODEX_DEVELOPMENT', 'LOCAL_RUNTIME', 'GITHUB_CI', 'RELEASE_DRY_RUN'),
                ),
                'RELEASE' => array(
                    'task' => '.codex-tasks/09-v4-t5-final-release.md',
                    'next' => null,
                    'release_allowed' => true,
                    'required_gates' => array('LOCAL_RUNTIME', 'GITHUB_CI', 'RELEASE_GATE', 'GITHUB_RELEASE', 'NOTION_WRITEBACK'),
                ),
            ),
            6
        );
    }

    private function baseState(): array
    {
        return array(
            'status' => 'RUNNING',
            'current_phase' => 'T4_ANALYTICS_ADMIN',
            'current_task' => '.codex-tasks/08-v4-t4-analytics-admin.md',
            'current_stage' => 'GPT_REVIEW',
            'repair_round' => 0,
            'repair_fingerprints' => array(),
            'phase_gates' => array(),
            'release_gate' => 'NOT READY',
            'release_state' => 'NOT RELEASED',
            'notion_state' => 'PENDING',
        );
    }

    private function decision(string $action): array
    {
        return array(
            'action' => $action,
            'phase' => null,
            'next_prompt' => 'Continue.',
            'gate' => null,
            'blocker' => null,
            'reason' => 'test',
            'confidence' => 1.0,
        );
    }
}
