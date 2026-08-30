<?php

stream_set_blocking(STDIN, true);
stream_set_write_buffer(STDOUT, 0);

while (($line = fgets(STDIN)) !== false) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }

    $msg = json_decode($line, true);
    if (!is_array($msg)) {
        continue;
    }

    $method = $msg['method'] ?? null;
    $id = $msg['id'] ?? null;

    if ($method === 'initialize' && $id !== null) {
        echo json_encode(array('id' => $id, 'result' => array('serverInfo' => array('name' => 'fake-codex', 'version' => '1.0')))) . PHP_EOL;
        continue;
    }

    if ($method === 'initialized') {
        continue;
    }

    if ($method === 'thread/start' && $id !== null) {
        $cwd = (string)($msg['params']['cwd'] ?? '');
        if (str_contains($cwd, 'NOTIFY_BEFORE_RESPONSE')) {
            echo json_encode(array('method' => 'thread/started', 'params' => array('thread' => array('id' => 'thread-test')))) . PHP_EOL;
        }
        echo json_encode(array('id' => $id, 'result' => array('thread' => array('id' => 'thread-test')))) . PHP_EOL;
        continue;
    }

    if ($method === 'turn/start' && $id !== null) {
        $prompt = (string)($msg['params']['input'][0]['text'] ?? '');
        $turnId = 'turn-' . substr(hash('sha256', $prompt), 0, 8);
        echo json_encode(array('id' => $id, 'result' => array('turn' => array('id' => $turnId)))) . PHP_EOL;

        if (str_contains($prompt, 'MALFORMED')) {
            echo "this-is-not-json\n";
            continue;
        }

        if (str_contains($prompt, 'EXIT_EARLY')) {
            exit(7);
        }

        if (str_contains($prompt, 'REQUEST_APPROVAL')) {
            echo json_encode(array(
                'id' => 900,
                'method' => 'item/commandExecution/requestApproval',
                'params' => array('command' => 'php -v'),
            )) . PHP_EOL;
            $approvalLine = fgets(STDIN);
            $approval = is_string($approvalLine) ? json_decode(trim($approvalLine), true) : null;
            if (!is_array($approval) || ($approval['result']['approved'] ?? false) !== true) {
                echo json_encode(array('method' => 'turn/failed', 'params' => array('turn' => array('id' => $turnId), 'error' => 'approval_missing'))) . PHP_EOL;
                continue;
            }
        }

        if (str_contains($prompt, 'USER_INPUT')) {
            echo json_encode(array(
                'id' => 901,
                'method' => 'item/tool/requestUserInput',
                'params' => array('prompt' => 'Need operator input'),
            )) . PHP_EOL;
            continue;
        }

        echo json_encode(array('method' => 'item/completed', 'params' => array('item' => array('type' => 'agent_message', 'text' => 'done')))) . PHP_EOL;
        echo json_encode(array('method' => 'turn/completed', 'params' => array('turn' => array('id' => $turnId)))) . PHP_EOL;
        continue;
    }

    if ($id !== null) {
        echo json_encode(array('id' => $id, 'error' => array('code' => -32601, 'message' => 'method_not_found'))) . PHP_EOL;
    }
}
