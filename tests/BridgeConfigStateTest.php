<?php

use PHPUnit\Framework\TestCase;

final class BridgeConfigStateTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'xz-bridge-' . bin2hex(random_bytes(6));
        mkdir($this->tmp . DIRECTORY_SEPARATOR . 'bridge' . DIRECTORY_SEPARATOR . 'runtime', 0777, true);
    }

    protected function tearDown(): void
    {
        putenv('XZ_BRIDGE_GPT_MODEL');
        putenv('OPENAI_API_KEY');
        $this->removeTree($this->tmp);
    }

    public function testConfigLoadsEnvironmentOverrideWithoutPersistingSecrets(): void
    {
        $configFile = dirname(__DIR__) . '/bridge/src/BridgeConfig.php';
        $this->assertFileExists($configFile, 'BridgeConfig must exist before this contract can pass.');
        require_once $configFile;

        file_put_contents(
            $this->tmp . '/bridge/config.json',
            json_encode(array(
                'controller' => array('model' => 'gpt-default'),
                'runtime' => array('state_file' => 'bridge/runtime/state.json'),
                'phases_file' => 'bridge/phases/v4.0.json',
            ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        putenv('XZ_BRIDGE_GPT_MODEL=gpt-test-override');
        putenv('OPENAI_API_KEY=sk-test-secret-must-not-persist');

        $config = \XzVisitStats\Bridge\BridgeConfig::load($this->tmp);

        $this->assertSame('gpt-test-override', $config->get('controller.model'));
        $this->assertSame('bridge/runtime/state.json', $config->get('runtime.state_file'));
        $this->assertStringNotContainsString('sk-test-secret-must-not-persist', json_encode($config->safeArray()));
        $this->assertArrayNotHasKey('OPENAI_API_KEY', $config->safeArray());
    }

    public function testStateSaveIsAtomicAndPreservesVerifiedStages(): void
    {
        $stateFile = dirname(__DIR__) . '/bridge/src/BridgeStateStore.php';
        $this->assertFileExists($stateFile, 'BridgeStateStore must exist before this contract can pass.');
        require_once $stateFile;

        $path = $this->tmp . '/bridge/runtime/state.json';
        $store = new \XzVisitStats\Bridge\BridgeStateStore($path);
        $store->save(array(
            'schema_version' => '1.0',
            'verified_stages' => array('T2', 'T3'),
            'current_phase' => 'T4_ANALYTICS_ADMIN',
        ));

        $state = $store->update(array('current_stage' => 'CODEX_RUNNING'));

        $this->assertSame(array('T2', 'T3'), $state['verified_stages']);
        $this->assertSame('CODEX_RUNNING', $state['current_stage']);
        $this->assertArrayHasKey('last_updated', $state);
        $this->assertFileExists($path);
        $this->assertFileDoesNotExist($path . '.tmp');
        $this->assertSame($state, $store->load());
    }

    public function testStateRejectsSecretBearingValues(): void
    {
        $stateFile = dirname(__DIR__) . '/bridge/src/BridgeStateStore.php';
        $this->assertFileExists($stateFile);
        require_once $stateFile;

        $store = new \XzVisitStats\Bridge\BridgeStateStore($this->tmp . '/bridge/runtime/state.json');

        $this->expectException(InvalidArgumentException::class);
        $store->save(array('next_action' => 'Bearer abcdefghijklmnopqrstuvwxyz123456'));
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $this->removeTree($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }
}
