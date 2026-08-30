<?php

namespace XzVisitStats\Bridge;

use RuntimeException;

final class OpenAIController
{
    private const ACTIONS = array(
        'CONTINUE_CODEX',
        'REPAIR',
        'RUN_GATE',
        'ADVANCE_PHASE',
        'PREPARE_RELEASE',
        'EXECUTE_RELEASE',
        'BLOCKED',
        'COMPLETE',
    );

    private array $config;
    private HttpTransport $http;
    private array $schema;
    private string $instructions;
    private $sleeper;

    public function __construct(
        array $config,
        HttpTransport $http,
        string $schemaPath,
        string $promptPath,
        ?callable $sleeper = null
    ) {
        $this->config = $config;
        $this->http = $http;
        $this->schema = $this->loadJsonObject($schemaPath, 'controller decision schema');
        $instructions = @file_get_contents($promptPath);
        if (!is_string($instructions) || trim($instructions) === '') {
            throw new RuntimeException('Unable to load GPT controller prompt.');
        }
        $this->instructions = $instructions;
        $this->sleeper = $sleeper ?? static function (int $milliseconds): void {
            usleep(max(0, $milliseconds) * 1000);
        };
    }

    public function decide(array $evidence, ?string $previousResponseId = null): array
    {
        $apiKey = getenv('OPENAI_API_KEY');
        if (!is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('OPENAI_API_KEY is required for the GPT controller.');
        }

        $model = trim((string)($this->config['model'] ?? 'gpt-5.6-sol'));
        if ($model === '') {
            throw new RuntimeException('GPT controller model is not configured.');
        }
        $apiBase = rtrim((string)($this->config['api_base'] ?? 'https://api.openai.com/v1'), '/');
        $timeout = max(1, (int)($this->config['request_timeout_seconds'] ?? 180));
        $maxRetries = max(0, (int)($this->config['max_http_retries'] ?? 3));
        $reasoningEffort = (string)($this->config['reasoning_effort'] ?? 'high');

        $evidenceJson = json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($evidenceJson)) {
            throw new RuntimeException('Unable to encode GPT controller evidence.');
        }

        $body = array(
            'model' => $model,
            'instructions' => $this->instructions,
            'input' => "Review this Bridge evidence and choose the next controller action:\n\n" . $evidenceJson,
            'reasoning' => array('effort' => $reasoningEffort),
            'text' => array(
                'format' => array(
                    'type' => 'json_schema',
                    'name' => 'xz_visit_stats_bridge_controller_decision',
                    'strict' => true,
                    'schema' => $this->schema,
                ),
            ),
            'store' => true,
        );
        if (is_string($previousResponseId) && trim($previousResponseId) !== '') {
            $body['previous_response_id'] = trim($previousResponseId);
        }

        $headers = array(
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        );

        $response = null;
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $response = $this->http->postJson($apiBase . '/responses', $headers, $body, $timeout);
            $status = (int)($response['status'] ?? 0);
            if ($status >= 200 && $status < 300) {
                break;
            }

            $retryable = $status === 408 || $status === 409 || $status === 429 || $status >= 500;
            if (!$retryable || $attempt >= $maxRetries) {
                throw new RuntimeException('OpenAI Responses API HTTP ' . $status . ': ' . $this->apiErrorText($response));
            }

            ($this->sleeper)(min(4000, 250 * (2 ** $attempt)));
        }

        if (!is_array($response)) {
            throw new RuntimeException('OpenAI Responses API returned no response.');
        }
        $decoded = json_decode((string)($response['body'] ?? ''), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('OpenAI Responses API returned invalid JSON.');
        }

        $responseId = $decoded['id'] ?? null;
        if (!is_string($responseId) || $responseId === '') {
            throw new RuntimeException('OpenAI Responses API response is missing an id.');
        }

        $outputText = $this->extractOutputText($decoded);
        $decision = json_decode($outputText, true);
        if (!is_array($decision)) {
            throw new RuntimeException('GPT controller structured output is invalid JSON.');
        }

        $this->validateDecision($decision);
        $decision['response_id'] = $responseId;
        return $decision;
    }

    private function extractOutputText(array $response): string
    {
        if (isset($response['output_text']) && is_string($response['output_text']) && trim($response['output_text']) !== '') {
            return $response['output_text'];
        }

        foreach (($response['output'] ?? array()) as $item) {
            if (!is_array($item)) {
                continue;
            }
            foreach (($item['content'] ?? array()) as $content) {
                if (!is_array($content)) {
                    continue;
                }
                if (($content['type'] ?? null) === 'output_text' && isset($content['text']) && is_string($content['text'])) {
                    return $content['text'];
                }
            }
        }

        throw new RuntimeException('GPT controller response did not contain output text.');
    }

    private function validateDecision(array $decision): void
    {
        $required = array('action', 'phase', 'next_prompt', 'gate', 'blocker', 'reason', 'confidence');
        foreach ($required as $field) {
            if (!array_key_exists($field, $decision)) {
                throw new RuntimeException('GPT controller structured output is missing field: ' . $field . '.');
            }
        }

        if (!is_string($decision['action']) || !in_array($decision['action'], self::ACTIONS, true)) {
            $value = is_scalar($decision['action'] ?? null) ? (string)$decision['action'] : 'invalid';
            throw new RuntimeException('GPT controller returned unsupported action: ' . $value . '.');
        }
        foreach (array('phase', 'next_prompt', 'gate', 'blocker') as $nullableString) {
            if ($decision[$nullableString] !== null && !is_string($decision[$nullableString])) {
                throw new RuntimeException('GPT controller field ' . $nullableString . ' must be a string or null.');
            }
        }
        if (!is_string($decision['reason']) || trim($decision['reason']) === '') {
            throw new RuntimeException('GPT controller reason must be a non-empty string.');
        }
        if (!is_int($decision['confidence']) && !is_float($decision['confidence'])) {
            throw new RuntimeException('GPT controller confidence must be numeric.');
        }
        $confidence = (float)$decision['confidence'];
        if ($confidence < 0 || $confidence > 1) {
            throw new RuntimeException('GPT controller confidence must be between 0 and 1.');
        }
    }

    private function loadJsonObject(string $path, string $label): array
    {
        $raw = @file_get_contents($path);
        if (!is_string($raw)) {
            throw new RuntimeException('Unable to load ' . $label . '.');
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException(ucfirst($label) . ' is invalid JSON.');
        }
        return $decoded;
    }

    private function apiErrorText(array $response): string
    {
        $body = json_decode((string)($response['body'] ?? ''), true);
        if (is_array($body)) {
            $message = $body['error']['message'] ?? $body['message'] ?? null;
            if (is_string($message) && $message !== '') {
                return $message;
            }
        }
        return 'request failed';
    }
}
