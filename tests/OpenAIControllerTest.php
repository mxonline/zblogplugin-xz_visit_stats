<?php

use PHPUnit\Framework\TestCase;

final class OpenAIControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('OPENAI_API_KEY');
    }

    public function testValidStructuredDecisionIsReturnedAndPreviousResponseIsForwarded(): void
    {
        [$controller, $requests] = $this->newController(array(
            array(
                'status' => 200,
                'body' => json_encode(array(
                    'id' => 'resp_test',
                    'output' => array(array(
                        'type' => 'message',
                        'content' => array(array(
                            'type' => 'output_text',
                            'text' => json_encode($this->validDecision('CONTINUE_CODEX')),
                        )),
                    )),
                )),
            ),
        ));

        $decision = $controller->decide(array('current_phase' => 'T4_ANALYTICS_ADMIN'), 'resp_previous');

        $this->assertSame('CONTINUE_CODEX', $decision['action']);
        $this->assertSame('resp_test', $decision['response_id']);
        $sent = $requests();
        $this->assertCount(1, $sent);
        $this->assertSame('resp_previous', $sent[0]['body']['previous_response_id']);
        $this->assertSame('json_schema', $sent[0]['body']['text']['format']['type']);
        $this->assertTrue($sent[0]['body']['text']['format']['strict']);
    }

    public function testTopLevelOutputTextIsAccepted(): void
    {
        [$controller] = $this->newController(array(
            array(
                'status' => 200,
                'body' => json_encode(array(
                    'id' => 'resp_top',
                    'output_text' => json_encode($this->validDecision('RUN_GATE')),
                )),
            ),
        ));

        $decision = $controller->decide(array('current_stage' => 'UNIT_TEST'));
        $this->assertSame('RUN_GATE', $decision['action']);
        $this->assertSame('resp_top', $decision['response_id']);
    }

    public function testForbiddenActionIsRejectedEvenWhenJsonIsValid(): void
    {
        $decision = $this->validDecision('CONTINUE_CODEX');
        $decision['action'] = 'FORCE_RELEASE';
        [$controller] = $this->newController(array(
            array('status' => 200, 'body' => json_encode(array('id' => 'resp_bad', 'output_text' => json_encode($decision)))),
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsupported action');
        $controller->decide(array());
    }

    public function testMalformedStructuredOutputIsRejected(): void
    {
        [$controller] = $this->newController(array(
            array('status' => 200, 'body' => json_encode(array('id' => 'resp_bad', 'output_text' => '{bad json'))),
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('invalid JSON');
        $controller->decide(array());
    }

    public function testServerErrorsRetryButAuthenticationFailureDoesNot(): void
    {
        [$controller, $requests] = $this->newController(array(
            array('status' => 500, 'body' => json_encode(array('error' => array('message' => 'temporary')))),
            array('status' => 200, 'body' => json_encode(array('id' => 'resp_retry', 'output_text' => json_encode($this->validDecision('REPAIR'))))),
        ), 3, static function (int $milliseconds): void {
        });

        $decision = $controller->decide(array('failure' => 'test'));
        $this->assertSame('REPAIR', $decision['action']);
        $this->assertCount(2, $requests());

        [$authController, $authRequests] = $this->newController(array(
            array('status' => 401, 'body' => json_encode(array('error' => array('message' => 'invalid_api_key')))),
            array('status' => 200, 'body' => json_encode(array('id' => 'should_not_happen', 'output_text' => '{}'))),
        ), 3, static function (int $milliseconds): void {
        });

        try {
            $authController->decide(array());
            $this->fail('Expected auth failure.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('HTTP 401', $e->getMessage());
        }
        $this->assertCount(1, $authRequests());
    }

    private function newController(array $responses, int $maxRetries = 3, ?callable $sleeper = null): array
    {
        $httpFile = dirname(__DIR__) . '/bridge/src/HttpTransport.php';
        $controllerFile = dirname(__DIR__) . '/bridge/src/OpenAIController.php';
        $this->assertFileExists($httpFile, 'HttpTransport must exist before this contract can pass.');
        $this->assertFileExists($controllerFile, 'OpenAIController must exist before this contract can pass.');
        require_once $httpFile;
        require_once $controllerFile;

        $captured = array();
        $queue = $responses;
        $transport = new \XzVisitStats\Bridge\HttpTransport(
            static function (string $url, array $headers, array $body, int $timeout) use (&$captured, &$queue): array {
                $captured[] = array('url' => $url, 'headers' => $headers, 'body' => $body, 'timeout' => $timeout);
                if ($queue === array()) {
                    throw new RuntimeException('Fake HTTP response queue exhausted.');
                }
                return array_shift($queue);
            }
        );

        putenv('OPENAI_API_KEY=test-key-for-controller-contract');
        $controller = new \XzVisitStats\Bridge\OpenAIController(
            array(
                'model' => 'gpt-test',
                'api_base' => 'https://api.openai.test/v1',
                'request_timeout_seconds' => 10,
                'max_http_retries' => $maxRetries,
            ),
            $transport,
            dirname(__DIR__) . '/bridge/schemas/controller-decision.schema.json',
            dirname(__DIR__) . '/bridge/prompts/gpt-controller.md',
            $sleeper
        );

        return array($controller, static function () use (&$captured): array {
            return $captured;
        });
    }

    private function validDecision(string $action): array
    {
        return array(
            'action' => $action,
            'phase' => 'T4_ANALYTICS_ADMIN',
            'next_prompt' => 'Continue the verified current task.',
            'gate' => null,
            'blocker' => null,
            'reason' => 'The current evidence supports the requested action.',
            'confidence' => 0.99,
        );
    }
}
