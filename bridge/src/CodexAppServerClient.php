<?php

namespace XzVisitStats\Bridge;

use RuntimeException;

final class CodexAppServerClient
{
    private array $command;
    private int $requestTimeoutMs;
    private int $turnTimeoutMs;
    private int $maxLineBytes;
    private mixed $process = null;
    private array $pipes = array();
    private int $nextId = 1;
    private string $stdoutBuffer = '';
    private string $stderrBuffer = '';
    private array $queuedMessages = array();

    public function __construct(
        array $command,
        int $requestTimeoutMs = 5000,
        int $turnTimeoutMs = 3600000,
        int $maxLineBytes = 10485760
    ) {
        if ($command === array()) {
            throw new RuntimeException('Codex App Server command is empty.');
        }
        $this->command = array_values($command);
        $this->requestTimeoutMs = max(100, $requestTimeoutMs);
        $this->turnTimeoutMs = max(100, $turnTimeoutMs);
        $this->maxLineBytes = max(4096, $maxLineBytes);
    }

    public function start(): void
    {
        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if (($status['running'] ?? false) === true) {
                return;
            }
        }

        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $pipes = array();
        $process = proc_open($this->command, $descriptors, $pipes, null, null, array('bypass_shell' => true));
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start Codex App Server.');
        }

        $this->process = $process;
        $this->pipes = $pipes;
        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);
        stream_set_write_buffer($this->pipes[0], 0);
        $this->stdoutBuffer = '';
        $this->stderrBuffer = '';
        $this->queuedMessages = array();
    }

    public function stop(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                @fclose($pipe);
            }
        }
        $this->pipes = array();

        if (is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if (($status['running'] ?? false) === true) {
                @proc_terminate($this->process);
            }
            @proc_close($this->process);
        }
        $this->process = null;
    }

    public function initialize(string $cwd): string
    {
        $this->assertRunning();

        $initialize = $this->request('initialize', array(
            'clientInfo' => array(
                'name' => 'xz-visit-stats-gpt-codex-bridge',
                'version' => '1.0.0',
            ),
            'capabilities' => array(),
        ), $this->requestTimeoutMs);

        if (isset($initialize['error'])) {
            throw new RuntimeException('Codex App Server initialize failed: ' . $this->errorText($initialize['error']));
        }

        $this->send(array('method' => 'initialized', 'params' => array()));

        $thread = $this->request('thread/start', array(
            'cwd' => $cwd,
            'approvalPolicy' => 'never',
            'sandbox' => 'workspace-write',
        ), $this->requestTimeoutMs);
        if (isset($thread['error'])) {
            throw new RuntimeException('Codex App Server thread/start failed: ' . $this->errorText($thread['error']));
        }

        $threadId = $thread['result']['thread']['id']
            ?? $thread['result']['threadId']
            ?? null;
        if (!is_string($threadId) || $threadId === '') {
            throw new RuntimeException('Codex App Server did not return a thread id.');
        }

        return $threadId;
    }

    public function runTurn(string $threadId, string $prompt, string $cwd, string $title): array
    {
        $this->assertRunning();
        if ($threadId === '') {
            throw new RuntimeException('Codex thread id is required.');
        }

        $response = $this->request('turn/start', array(
            'threadId' => $threadId,
            'cwd' => $cwd,
            'title' => $title,
            'approvalPolicy' => 'never',
            'sandboxPolicy' => array('type' => 'workspaceWrite'),
            'input' => array(array('type' => 'text', 'text' => $prompt)),
        ), $this->requestTimeoutMs);
        if (isset($response['error'])) {
            throw new RuntimeException('Codex App Server turn/start failed: ' . $this->errorText($response['error']));
        }

        $turnId = $response['result']['turn']['id']
            ?? $response['result']['turnId']
            ?? null;
        if (!is_string($turnId) || $turnId === '') {
            throw new RuntimeException('Codex App Server did not return a turn id.');
        }

        $events = array();
        $approvals = 0;
        $deadline = microtime(true) + ($this->turnTimeoutMs / 1000);

        while (true) {
            $remainingMs = (int)max(1, ceil(($deadline - microtime(true)) * 1000));
            if ($remainingMs <= 1 && microtime(true) >= $deadline) {
                throw new RuntimeException('Timed out waiting for Codex turn completion.');
            }

            $message = $this->nextMessage(min($remainingMs, 1000));
            if ($message === null) {
                $this->assertStillRunning();
                continue;
            }

            if ($this->isServerRequest($message)) {
                $requestResult = $this->handleServerRequest($message);
                if ($requestResult === 'approval') {
                    $approvals++;
                    continue;
                }
                if ($requestResult === 'user_input') {
                    throw new RuntimeException('Codex requested operator input; Bridge run is blocked and resumable.');
                }
                continue;
            }

            $events[] = $message;
            $method = (string)($message['method'] ?? '');
            if ($method === 'turn/completed') {
                return array(
                    'status' => 'completed',
                    'turn_id' => $turnId,
                    'events' => $events,
                    'approvals' => $approvals,
                    'stderr' => $this->stderrBuffer,
                );
            }
            if ($method === 'turn/failed') {
                throw new RuntimeException('Codex turn failed: ' . $this->errorText($message['params']['error'] ?? $message['params'] ?? array()));
            }
            if ($method === 'turn/cancelled') {
                throw new RuntimeException('Codex turn was cancelled.');
            }
        }
    }

    private function request(string $method, array $params, int $timeoutMs): array
    {
        $id = $this->nextId++;
        $this->send(array('id' => $id, 'method' => $method, 'params' => $params));
        $deadline = microtime(true) + ($timeoutMs / 1000);
        $deferred = array();

        try {
            while (true) {
                $remainingMs = (int)max(1, ceil(($deadline - microtime(true)) * 1000));
                if ($remainingMs <= 1 && microtime(true) >= $deadline) {
                    throw new RuntimeException('Timed out waiting for Codex App Server response to ' . $method . '.');
                }

                $message = $this->nextMessageDirect(min($remainingMs, 1000));
                if ($message === null) {
                    $this->assertStillRunning();
                    continue;
                }

                if (($message['id'] ?? null) === $id && !isset($message['method'])) {
                    return $message;
                }

                if ($this->isServerRequest($message)) {
                    $result = $this->handleServerRequest($message);
                    if ($result === 'user_input') {
                        throw new RuntimeException('Codex requested operator input; Bridge run is blocked and resumable.');
                    }
                    continue;
                }

                $deferred[] = $message;
            }
        } finally {
            if ($deferred !== array()) {
                $this->queuedMessages = array_merge($deferred, $this->queuedMessages);
            }
        }
    }

    private function send(array $message): void
    {
        $this->assertRunning();
        $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode Codex App Server protocol message.');
        }
        $payload = $json . PHP_EOL;
        $length = strlen($payload);
        $written = 0;
        while ($written < $length) {
            $part = fwrite($this->pipes[0], substr($payload, $written));
            if ($part === false || $part === 0) {
                $this->assertStillRunning();
                usleep(1000);
                continue;
            }
            $written += $part;
        }
        fflush($this->pipes[0]);
    }

    private function nextMessage(int $timeoutMs): ?array
    {
        if ($this->queuedMessages !== array()) {
            return array_shift($this->queuedMessages);
        }
        return $this->nextMessageDirect($timeoutMs);
    }

    private function nextMessageDirect(int $timeoutMs): ?array
    {
        $line = $this->extractBufferedLine();
        if ($line !== null) {
            return $this->decodeLine($line);
        }

        $deadline = microtime(true) + ($timeoutMs / 1000);
        while (microtime(true) < $deadline) {
            $read = array();
            if (isset($this->pipes[1]) && is_resource($this->pipes[1])) {
                $read[] = $this->pipes[1];
            }
            if (isset($this->pipes[2]) && is_resource($this->pipes[2])) {
                $read[] = $this->pipes[2];
            }
            if ($read === array()) {
                $this->assertStillRunning();
                return null;
            }

            $remaining = max(0.0, $deadline - microtime(true));
            $seconds = (int)floor($remaining);
            $microseconds = (int)(($remaining - $seconds) * 1000000);
            $write = null;
            $except = null;
            $selected = @stream_select($read, $write, $except, $seconds, $microseconds);
            if ($selected === false) {
                throw new RuntimeException('Unable to read Codex App Server protocol stream.');
            }
            if ($selected === 0) {
                $this->assertStillRunning();
                return null;
            }

            foreach ($read as $stream) {
                $chunk = fread($stream, 8192);
                if ($chunk === false || $chunk === '') {
                    continue;
                }
                if ($stream === $this->pipes[2]) {
                    $this->stderrBuffer .= $chunk;
                    if (strlen($this->stderrBuffer) > $this->maxLineBytes) {
                        $this->stderrBuffer = substr($this->stderrBuffer, -$this->maxLineBytes);
                    }
                    continue;
                }

                $this->stdoutBuffer .= $chunk;
                if (strlen($this->stdoutBuffer) > $this->maxLineBytes && !str_contains($this->stdoutBuffer, "\n")) {
                    throw new RuntimeException('Codex App Server protocol line exceeded the configured size limit.');
                }
                $line = $this->extractBufferedLine();
                if ($line !== null) {
                    return $this->decodeLine($line);
                }
            }
        }

        $this->assertStillRunning();
        return null;
    }

    private function extractBufferedLine(): ?string
    {
        $position = strpos($this->stdoutBuffer, "\n");
        if ($position === false) {
            return null;
        }
        $line = substr($this->stdoutBuffer, 0, $position);
        $this->stdoutBuffer = substr($this->stdoutBuffer, $position + 1);
        if (strlen($line) > $this->maxLineBytes) {
            throw new RuntimeException('Codex App Server protocol line exceeded the configured size limit.');
        }
        return rtrim($line, "\r");
    }

    private function decodeLine(string $line): array
    {
        if ($line === '') {
            return $this->nextMessageDirect(1) ?? array();
        }
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Malformed Codex App Server protocol line.');
        }
        return $decoded;
    }

    private function isServerRequest(array $message): bool
    {
        return array_key_exists('id', $message) && isset($message['method']) && is_string($message['method']);
    }

    private function handleServerRequest(array $message): string
    {
        $method = (string)$message['method'];
        $id = $message['id'];

        if (stripos($method, 'requestUserInput') !== false || stripos($method, 'userInput') !== false) {
            $this->send(array(
                'id' => $id,
                'error' => array('code' => -32001, 'message' => 'operator_input_required'),
            ));
            return 'user_input';
        }

        if (stripos($method, 'requestApproval') !== false || stripos($method, 'approval') !== false) {
            $this->send(array(
                'id' => $id,
                'result' => array('approved' => true, 'decision' => 'accept'),
            ));
            return 'approval';
        }

        $this->send(array(
            'id' => $id,
            'error' => array('code' => -32601, 'message' => 'unsupported_client_request'),
        ));
        return 'unsupported';
    }

    private function assertRunning(): void
    {
        if (!is_resource($this->process) || !isset($this->pipes[0], $this->pipes[1], $this->pipes[2])) {
            throw new RuntimeException('Codex App Server is not running.');
        }
        $this->assertStillRunning();
    }

    private function assertStillRunning(): void
    {
        if (!is_resource($this->process)) {
            throw new RuntimeException('Codex App Server exited.');
        }
        $status = proc_get_status($this->process);
        if (($status['running'] ?? false) !== true) {
            $exitCode = $status['exitcode'] ?? null;
            $suffix = is_int($exitCode) && $exitCode >= 0 ? ' with exit code ' . $exitCode : '';
            throw new RuntimeException('Codex App Server exited' . $suffix . '.');
        }
    }

    private function errorText(mixed $error): string
    {
        if (is_string($error)) {
            return $error;
        }
        if (is_array($error)) {
            $message = $error['message'] ?? $error['error'] ?? null;
            if (is_string($message) && $message !== '') {
                return $message;
            }
            $json = json_encode($error, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            return is_string($json) ? $json : 'unknown error';
        }
        return 'unknown error';
    }
}
