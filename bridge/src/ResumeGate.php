<?php

namespace XzVisitStats\Bridge;

use RuntimeException;

final class ResumeGate
{
    private string $repoRoot;
    private CommandRunner $runner;
    private BridgeStateStore $store;
    private string $expectedBranch;

    public function __construct(
        string $repoRoot,
        CommandRunner $runner,
        BridgeStateStore $store,
        string $expectedBranch
    ) {
        $this->repoRoot = rtrim($repoRoot, '/\\');
        $this->runner = $runner;
        $this->store = $store;
        $this->expectedBranch = $expectedBranch;
    }

    public function inspect(): array
    {
        $state = $this->store->load();
        $branch = trim($this->checked(array('git', 'rev-parse', '--abbrev-ref', 'HEAD'), 'current branch')['stdout']);
        $head = trim($this->checked(array('git', 'rev-parse', 'HEAD'), 'current HEAD')['stdout']);
        $status = $this->checked(array('git', 'status', '--porcelain=v1', '--untracked-files=all'), 'working tree status')['stdout'];
        $remoteHead = trim($this->checked(array('git', 'rev-parse', 'origin/' . $this->expectedBranch), 'remote development HEAD')['stdout']);

        $recordedHead = isset($state['head_sha']) && is_string($state['head_sha']) && trim($state['head_sha']) !== ''
            ? trim($state['head_sha'])
            : null;

        $projectStatePath = $this->repoRoot . DIRECTORY_SEPARATOR . 'knowledge' . DIRECTORY_SEPARATOR . 'PROJECT-STATE.md';
        $projectState = is_file($projectStatePath) ? (string)file_get_contents($projectStatePath) : '';

        $verified = $this->normalizeVerifiedStages($state['verified_stages'] ?? array(), $projectState);
        $phase = isset($state['current_phase']) && is_string($state['current_phase']) && trim($state['current_phase']) !== ''
            ? trim($state['current_phase'])
            : 'T4_ANALYTICS_ADMIN';
        if (!in_array($phase, array('T4_ANALYTICS_ADMIN', 'T5_FINAL_VERIFICATION_RELEASE_PREP', 'RELEASE'), true)) {
            $phase = 'T4_ANALYTICS_ADMIN';
        }

        $task = $phase === 'T4_ANALYTICS_ADMIN'
            ? '.codex-tasks/08-v4-t4-analytics-admin.md'
            : '.codex-tasks/09-v4-t5-final-release.md';

        $releaseGate = isset($state['release_gate']) && is_string($state['release_gate'])
            ? $state['release_gate']
            : ($phase === 'T4_ANALYTICS_ADMIN' ? 'NOT READY' : 'BLOCKED');

        return array(
            'expected_branch' => $this->expectedBranch,
            'branch' => $branch,
            'branch_matches_expected' => $branch === $this->expectedBranch,
            'head_sha' => $head,
            'remote_head_sha' => $remoteHead,
            'remote_head_mismatch' => $remoteHead !== '' && $remoteHead !== $head,
            'recorded_head_sha' => $recordedHead,
            'head_mismatch' => $recordedHead !== null && $recordedHead !== $head,
            'git_status' => $status,
            'dirty' => trim($status) !== '',
            'current_phase' => $phase,
            'current_task' => $task,
            'verified_stages' => $verified,
            'release_gate' => $releaseGate,
            'legacy_state_ignored' => is_file($this->repoRoot . DIRECTORY_SEPARATOR . '.codex-state.json'),
        );
    }

    private function checked(array $command, string $label): array
    {
        $result = $this->runner->run($command, $this->repoRoot);
        if (($result['exit_code'] ?? 1) !== 0) {
            $stderr = trim((string)($result['stderr'] ?? ''));
            throw new RuntimeException('Unable to inspect ' . $label . ($stderr !== '' ? ': ' . $stderr : '.'));
        }
        return $result;
    }

    private function normalizeVerifiedStages(mixed $recorded, string $projectState): array
    {
        $verified = array();
        if (is_array($recorded)) {
            foreach ($recorded as $stage) {
                if (is_string($stage) && $stage !== '' && !in_array($stage, $verified, true)) {
                    $verified[] = $stage;
                }
            }
        }

        $lower = strtolower($projectState);
        if ((str_contains($lower, 't2') && str_contains($lower, 'verified')) || str_contains($lower, 'verified t2 baseline')) {
            if (!in_array('T2_SCHEMA_AUDIT', $verified, true)) {
                $verified[] = 'T2_SCHEMA_AUDIT';
            }
        }
        if ((str_contains($lower, 't3') && str_contains($lower, 'verified')) || str_contains($lower, 'verified t3')) {
            if (!in_array('T3_FOUNDATION', $verified, true)) {
                $verified[] = 'T3_FOUNDATION';
            }
        }

        $locked = array();
        foreach (array('T2_SCHEMA_AUDIT', 'T3_FOUNDATION') as $required) {
            if (in_array($required, $verified, true)) {
                $locked[] = $required;
            }
        }
        foreach ($verified as $stage) {
            if (!in_array($stage, $locked, true)) {
                $locked[] = $stage;
            }
        }
        return $locked;
    }
}
