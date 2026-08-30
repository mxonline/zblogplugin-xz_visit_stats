<?php

namespace XzVisitStats\Bridge;

use RuntimeException;

final class BridgeOrchestrator
{
    private array $phases;
    private int $maxRepairRounds;

    public function __construct(array $phases, int $maxRepairRounds = 6)
    {
        $this->phases = $phases;
        $this->maxRepairRounds = max(1, $maxRepairRounds);
    }

    public function applyDecision(array $state, array $decision, array $failure = array()): array
    {
        $phase = (string)($state['current_phase'] ?? '');
        if ($phase === '' || !isset($this->phases[$phase])) {
            throw new RuntimeException('Unknown Bridge phase: ' . ($phase !== '' ? $phase : 'empty') . '.');
        }

        $action = (string)($decision['action'] ?? '');
        switch ($action) {
            case 'CONTINUE_CODEX':
                $state['status'] = 'RUNNING';
                $state['current_stage'] = 'CODEX_RUNNING';
                $state['next_action'] = 'CONTINUE_CODEX';
                $state['broader_diagnosis_required'] = false;
                return $state;

            case 'RUN_GATE':
                $state['status'] = 'RUNNING';
                $state['current_stage'] = 'GATE';
                $state['next_action'] = 'RUN_GATE';
                $state['pending_gate'] = $decision['gate'] ?? null;
                return $state;

            case 'REPAIR':
                return $this->applyRepair($state, $failure);

            case 'ADVANCE_PHASE':
                return $this->advancePhase($state);

            case 'PREPARE_RELEASE':
                if ($phase !== 'T5_FINAL_VERIFICATION_RELEASE_PREP') {
                    throw new RuntimeException('Release preparation is only allowed from T5.');
                }
                $this->assertRequiredGatesPassed($state, $phase);
                $state['status'] = 'RUNNING';
                $state['current_stage'] = 'RELEASE_PREP';
                $state['next_action'] = 'PREPARE_RELEASE';
                return $state;

            case 'EXECUTE_RELEASE':
                if ($phase === 'T4_ANALYTICS_ADMIN') {
                    throw new RuntimeException('Release is not allowed from T4.');
                }
                if (($this->phases[$phase]['release_allowed'] ?? false) !== true) {
                    throw new RuntimeException('Release is not allowed from the current phase.');
                }
                if (($state['release_gate'] ?? null) !== 'PASS') {
                    throw new RuntimeException('Release execution requires Release Gate PASS.');
                }
                $state['status'] = 'RUNNING';
                $state['current_stage'] = 'RELEASE';
                $state['next_action'] = 'EXECUTE_RELEASE';
                return $state;

            case 'BLOCKED':
                $state['status'] = 'BLOCKED';
                $state['current_stage'] = 'BLOCKED';
                $state['next_action'] = 'BLOCKED';
                $state['blocked_reason'] = $decision['blocker'] ?? $decision['reason'] ?? 'UNKNOWN_BLOCKER';
                return $state;

            case 'COMPLETE':
                $this->assertCompleteEvidence($state);
                $state['status'] = 'COMPLETE';
                $state['current_stage'] = 'COMPLETE';
                $state['next_action'] = 'COMPLETE';
                return $state;
        }

        throw new RuntimeException('Unsupported Bridge decision action: ' . $action . '.');
    }

    private function applyRepair(array $state, array $failure): array
    {
        $round = (int)($state['repair_round'] ?? 0) + 1;
        $fingerprint = isset($failure['fingerprint']) && is_string($failure['fingerprint']) && $failure['fingerprint'] !== ''
            ? $failure['fingerprint']
            : hash('sha256', (string)($failure['gate'] ?? '') . "\n" . (string)($failure['summary'] ?? ''));

        $history = is_array($state['repair_fingerprints'] ?? null) ? $state['repair_fingerprints'] : array();
        $history[] = $fingerprint;
        $sameCount = 0;
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if ($history[$i] !== $fingerprint) {
                break;
            }
            $sameCount++;
        }

        $state['repair_round'] = $round;
        $state['repair_fingerprints'] = $history;
        $state['last_failure'] = array(
            'gate' => $failure['gate'] ?? null,
            'fingerprint' => $fingerprint,
            'summary' => $failure['summary'] ?? '',
        );

        if ($sameCount >= 3) {
            $state['status'] = 'BLOCKED';
            $state['current_stage'] = 'BLOCKED';
            $state['next_action'] = 'BLOCKED';
            $state['blocked_reason'] = 'REPEATED_NO_PROGRESS';
            $state['broader_diagnosis_required'] = true;
            return $state;
        }
        if ($round > $this->maxRepairRounds) {
            $state['status'] = 'BLOCKED';
            $state['current_stage'] = 'BLOCKED';
            $state['next_action'] = 'BLOCKED';
            $state['blocked_reason'] = 'MAX_REPAIR_ROUNDS';
            return $state;
        }

        $state['status'] = 'RUNNING';
        $state['current_stage'] = 'CODEX_REPAIR';
        $state['next_action'] = 'REPAIR';
        $state['broader_diagnosis_required'] = $sameCount >= 2;
        return $state;
    }

    private function advancePhase(array $state): array
    {
        $phase = (string)$state['current_phase'];
        $this->assertRequiredGatesPassed($state, $phase);
        $next = $this->phases[$phase]['next'] ?? null;
        if (!is_string($next) || $next === '' || !isset($this->phases[$next])) {
            throw new RuntimeException('Current phase has no valid next phase.');
        }

        $state['current_phase'] = $next;
        $state['current_task'] = (string)($this->phases[$next]['task'] ?? '');
        $state['current_stage'] = 'RESUME_GATE';
        $state['status'] = 'RUNNING';
        $state['next_action'] = 'RESUME';
        $state['repair_round'] = 0;
        $state['repair_fingerprints'] = array();
        $state['broader_diagnosis_required'] = false;
        $state['phase_gates'] = array();
        return $state;
    }

    private function assertRequiredGatesPassed(array $state, string $phase): void
    {
        $required = $this->phases[$phase]['required_gates'] ?? array();
        $gates = is_array($state['phase_gates'] ?? null) ? $state['phase_gates'] : array();
        foreach ($required as $gate) {
            if (($gates[$gate] ?? null) !== 'PASS') {
                throw new RuntimeException('Cannot advance: required gate ' . $gate . ' is not PASS.');
            }
        }
    }

    private function assertCompleteEvidence(array $state): void
    {
        $release = is_array($state['release_evidence'] ?? null) ? $state['release_evidence'] : array();
        $valid = ($state['current_phase'] ?? null) === 'RELEASE'
            && ($state['release_gate'] ?? null) === 'PASS'
            && ($state['release_state'] ?? null) === 'RELEASED'
            && ($state['notion_state'] ?? null) === 'PASS'
            && is_string($release['tag'] ?? null) && $release['tag'] !== ''
            && is_string($release['github_release'] ?? null) && $release['github_release'] !== ''
            && is_string($release['zip'] ?? null) && $release['zip'] !== ''
            && is_string($release['sha256'] ?? null) && preg_match('/^[a-f0-9]{64}$/i', $release['sha256']) === 1;
        if (!$valid) {
            throw new RuntimeException('COMPLETE requires verified release and Notion writeback.');
        }
    }
}
