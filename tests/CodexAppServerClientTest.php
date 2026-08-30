<?php

use PHPUnit\Framework\TestCase;

final class CodexAppServerClientTest extends TestCase
{
    private array $clients = array();

    protected function tearDown(): void
    {
        foreach ($this->clients as $client) {
            $client->stop();
        }
        $this->clients = array();
    }

    public function testHandshakeAndTurnCompletionUseOneThread(): void
    {
        $client = $this->newClient();
        $client->start();

        $threadId = $client->initialize(dirname(__DIR__));
        $result = $client->runTurn($threadId, 'NORMAL', dirname(__DIR__), 'bridge test');

        $this->assertSame('thread-test', $threadId);
        $this->assertSame('completed', $result['status']);
        $this->assertStringStartsWith('turn-', $result['turn_id']);
        $this->assertNotEmpty($result['events']);
        $this->assertSame(0, $result['approvals']);
    }

    public function testNotificationBeforeRequestResponseDoesNotStarveMatchingResponse(): void
    {
        $client = $this->newClient();
        $client->start();

        $threadId = $client->initialize('NOTIFY_BEFORE_RESPONSE');
        $result = $client->runTurn($threadId, 'NORMAL', dirname(__DIR__), 'notification ordering test');

        $this->assertSame('thread-test', $threadId);
        $this->assertSame('completed', $result['status']);
        $methods = array_map(static fn(array $event): string => (string)($event['method'] ?? ''), $result['events']);
        $this->assertContains('thread/started', $methods);
    }

    public function testCommandApprovalIsAutoApprovedForAuthorizedDevelopmentTurn(): void
    {
        $client = $this->newClient();
        $client->start();
        $threadId = $client->initialize(dirname(__DIR__));

        $result = $client->runTurn($threadId, 'REQUEST_APPROVAL', dirname(__DIR__), 'approval test');

        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['approvals']);
    }

    public function testMalformedProtocolLineFailsTheTurn(): void
    {
        $client = $this->newClient();
        $client->start();
        $threadId = $client->initialize(dirname(__DIR__));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed Codex App Server protocol line');
        $client->runTurn($threadId, 'MALFORMED', dirname(__DIR__), 'malformed test');
    }

    public function testUserInputRequestBecomesAResumableBlockerInsteadOfHanging(): void
    {
        $client = $this->newClient();
        $client->start();
        $threadId = $client->initialize(dirname(__DIR__));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Codex requested operator input');
        $client->runTurn($threadId, 'USER_INPUT', dirname(__DIR__), 'input test');
    }

    public function testUnexpectedSubprocessExitFailsTheTurn(): void
    {
        $client = $this->newClient();
        $client->start();
        $threadId = $client->initialize(dirname(__DIR__));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Codex App Server exited');
        $client->runTurn($threadId, 'EXIT_EARLY', dirname(__DIR__), 'exit test');
    }

    private function newClient(): object
    {
        $classFile = dirname(__DIR__) . '/bridge/src/CodexAppServerClient.php';
        $this->assertFileExists($classFile, 'CodexAppServerClient must exist before this contract can pass.');
        require_once $classFile;

        $fixture = __DIR__ . '/fixtures/fake-codex-app-server.php';
        $client = new \XzVisitStats\Bridge\CodexAppServerClient(
            array(PHP_BINARY, $fixture),
            2000,
            4000,
            1024 * 1024
        );
        $this->clients[] = $client;
        return $client;
    }
}
