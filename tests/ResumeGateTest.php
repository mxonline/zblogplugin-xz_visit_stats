<?php

use PHPUnit\Framework\TestCase;

final class ResumeGateTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xz-resume-' . bin2hex(random_bytes(5));
        mkdir($this->tmp . '/knowledge', 0777, true);
        mkdir($this->tmp . '/.codex-tasks', 0777, true);
        mkdir($this->tmp . '/bridge/runtime', 0777, true);
        file_put_contents($this->tmp . '/knowledge/PROJECT-STATE.md', "# Project State\n\n- Current phase: `T4 — analytics/admin reports, filters and session drill-down`\n- T2: VERIFIED\n- T3: VERIFIED\n- Release Gate: `NOT READY`\n");
        file_put_contents($this->tmp . '/.codex-tasks/08-v4-t4-analytics-admin.md', '# T4');
        file_put_contents($this->tmp . '/.codex-state.json', json_encode(array('current' => 99, 'last_run' => 'legacy')));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tmp);
    }

    public function testResumeGateLocksVerifiedT2T3AndIgnoresLegacyController(): void
    {
        [$gate, $commands] = $this->newGate(array(
            'git rev-parse --abbrev-ref HEAD' => $this->ok("feature/visit-stats-4.0\n"),
            'git rev-parse HEAD' => $this->ok("aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\n"),
            'git status --porcelain=v1 --untracked-files=all' => $this->ok(''),
            'git rev-parse origin/feature/visit-stats-4.0' => $this->ok("aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\n"),
        ), array(
            'schema_version' => '1.0',
            'head_sha' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
            'verified_stages' => array('T2_SCHEMA_AUDIT', 'T3_FOUNDATION'),
            'current_phase' => 'T4_ANALYTICS_ADMIN',
        ));

        $result = $gate->inspect();

        $this->assertSame('T4_ANALYTICS_ADMIN', $result['current_phase']);
        $this->assertSame('.codex-tasks/08-v4-t4-analytics-admin.md', $result['current_task']);
        $this->assertSame(array('T2_SCHEMA_AUDIT', 'T3_FOUNDATION'), $result['verified_stages']);
        $this->assertTrue($result['legacy_state_ignored']);
        $this->assertFalse($result['dirty']);
        $this->assertFalse($result['head_mismatch']);
        $this->assertNotContains('git reset --hard', $commands());
        $this->assertNotContains('git clean -fd', $commands());
    }

    public function testDirtyTreeIsReportedWithoutDestructiveCleanup(): void
    {
        [$gate, $commands] = $this->newGate(array(
            'git rev-parse --abbrev-ref HEAD' => $this->ok("feature/visit-stats-4.0\n"),
            'git rev-parse HEAD' => $this->ok("bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb\n"),
            'git status --porcelain=v1 --untracked-files=all' => $this->ok(" M main.php\n?? local-note.txt\n"),
            'git rev-parse origin/feature/visit-stats-4.0' => $this->ok("bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb\n"),
        ), array('verified_stages' => array('T2_SCHEMA_AUDIT', 'T3_FOUNDATION')));

        $result = $gate->inspect();

        $this->assertTrue($result['dirty']);
        $this->assertStringContainsString('main.php', $result['git_status']);
        $joined = implode("\n", $commands());
        $this->assertStringNotContainsString('reset --hard', $joined);
        $this->assertStringNotContainsString('clean -fd', $joined);
        $this->assertStringNotContainsString('stash', $joined);
    }

    public function testRecordedHeadMismatchUsesRealHeadAsAuthority(): void
    {
        [$gate] = $this->newGate(array(
            'git rev-parse --abbrev-ref HEAD' => $this->ok("feature/visit-stats-4.0\n"),
            'git rev-parse HEAD' => $this->ok("cccccccccccccccccccccccccccccccccccccccc\n"),
            'git status --porcelain=v1 --untracked-files=all' => $this->ok(''),
            'git rev-parse origin/feature/visit-stats-4.0' => $this->ok("cccccccccccccccccccccccccccccccccccccccc\n"),
        ), array(
            'head_sha' => 'dddddddddddddddddddddddddddddddddddddddd',
            'verified_stages' => array('T2_SCHEMA_AUDIT', 'T3_FOUNDATION'),
        ));

        $result = $gate->inspect();
        $this->assertTrue($result['head_mismatch']);
        $this->assertSame('cccccccccccccccccccccccccccccccccccccccc', $result['head_sha']);
        $this->assertSame('dddddddddddddddddddddddddddddddddddddddd', $result['recorded_head_sha']);
    }

    private function newGate(array $responses, array $initialState): array
    {
        foreach (array('CommandRunner.php', 'BridgeStateStore.php', 'ResumeGate.php') as $file) {
            $path = dirname(__DIR__) . '/bridge/src/' . $file;
            $this->assertFileExists($path, $file . ' must exist before this contract can pass.');
            require_once $path;
        }

        $seen = array();
        $runner = new \XzVisitStats\Bridge\CommandRunner(
            static function (array $command, string $cwd) use (&$responses, &$seen): array {
                $key = implode(' ', $command);
                $seen[] = $key;
                if (!array_key_exists($key, $responses)) {
                    return array('exit_code' => 127, 'stdout' => '', 'stderr' => 'unexpected command: ' . $key);
                }
                return $responses[$key];
            }
        );
        $store = new \XzVisitStats\Bridge\BridgeStateStore($this->tmp . '/bridge/runtime/state.json');
        $store->save($initialState);
        $gate = new \XzVisitStats\Bridge\ResumeGate(
            $this->tmp,
            $runner,
            $store,
            'feature/visit-stats-4.0'
        );

        return array($gate, static function () use (&$seen): array { return $seen; });
    }

    private function ok(string $stdout): array
    {
        return array('exit_code' => 0, 'stdout' => $stdout, 'stderr' => '');
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: array() as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($full) ? $this->removeTree($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
