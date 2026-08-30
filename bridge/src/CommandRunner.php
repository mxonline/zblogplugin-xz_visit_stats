<?php

namespace XzVisitStats\Bridge;

use RuntimeException;

final class CommandRunner
{
    private $handler;

    public function __construct(?callable $handler = null)
    {
        $this->handler = $handler;
    }

    public function run(array $command, string $cwd): array
    {
        if ($command === array()) {
            throw new RuntimeException('CommandRunner command is empty.');
        }

        if ($this->handler !== null) {
            return $this->normalize(($this->handler)(array_values($command), $cwd));
        }

        if (!is_dir($cwd)) {
            throw new RuntimeException('CommandRunner working directory does not exist.');
        }

        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );
        $pipes = array();
        $process = proc_open(array_values($command), $descriptors, $pipes, $cwd, null, array('bypass_shell' => true));
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start command.');
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }
        $stdout = isset($pipes[1]) && is_resource($pipes[1]) ? stream_get_contents($pipes[1]) : '';
        $stderr = isset($pipes[2]) && is_resource($pipes[2]) ? stream_get_contents($pipes[2]) : '';
        if (isset($pipes[1]) && is_resource($pipes[1])) {
            fclose($pipes[1]);
        }
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            fclose($pipes[2]);
        }
        $exitCode = proc_close($process);

        return array(
            'exit_code' => is_int($exitCode) ? $exitCode : 1,
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
        );
    }

    private function normalize(mixed $result): array
    {
        if (!is_array($result)) {
            throw new RuntimeException('CommandRunner handler must return an array.');
        }
        return array(
            'exit_code' => (int)($result['exit_code'] ?? 1),
            'stdout' => is_string($result['stdout'] ?? null) ? $result['stdout'] : '',
            'stderr' => is_string($result['stderr'] ?? null) ? $result['stderr'] : '',
        );
    }
}
