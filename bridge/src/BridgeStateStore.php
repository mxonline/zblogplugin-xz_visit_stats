<?php

namespace XzVisitStats\Bridge;

use InvalidArgumentException;
use RuntimeException;

final class BridgeStateStore
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function load(): array
    {
        if (!is_file($this->path)) {
            return array();
        }

        $raw = file_get_contents($this->path);
        if ($raw === false) {
            throw new RuntimeException('Unable to read Bridge state.');
        }

        $state = json_decode($raw, true);
        if (!is_array($state)) {
            throw new RuntimeException('Bridge state is not a valid JSON object.');
        }

        return $state;
    }

    public function save(array $state): void
    {
        $state['last_updated'] = gmdate('c');
        $this->assertNoSecrets($state);

        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create Bridge state directory.');
        }

        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Unable to encode Bridge state.');
        }
        $json .= PHP_EOL;

        $tmp = $this->path . '.tmp';
        $handle = fopen($tmp, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open temporary Bridge state file.');
        }

        try {
            if (fwrite($handle, $json) === false) {
                throw new RuntimeException('Unable to write temporary Bridge state file.');
            }
            if (!fflush($handle)) {
                throw new RuntimeException('Unable to flush temporary Bridge state file.');
            }
        } finally {
            fclose($handle);
        }

        if (!@rename($tmp, $this->path)) {
            if (is_file($this->path) && !@unlink($this->path)) {
                @unlink($tmp);
                throw new RuntimeException('Unable to replace existing Bridge state file.');
            }
            if (!@rename($tmp, $this->path)) {
                @unlink($tmp);
                throw new RuntimeException('Unable to install new Bridge state file.');
            }
        }
    }

    public function update(array $patch): array
    {
        $state = array_replace($this->load(), $patch);
        $this->save($state);
        return $this->load();
    }

    private function assertNoSecrets(array $value): void
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            throw new InvalidArgumentException('Bridge state could not be inspected for secrets.');
        }

        $patterns = array(
            '/\bsk-[A-Za-z0-9_-]{8,}\b/i',
            '/\bBearer\s+[A-Za-z0-9._~+\/-]{12,}\b/i',
            '/-----BEGIN(?: [A-Z]+)? PRIVATE KEY-----/i',
            '/\b(?:cookie|session|token|password)\s*[=:]\s*[^\s,;]{12,}/i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $encoded) === 1) {
                throw new InvalidArgumentException('Bridge state contains data that looks like a secret.');
            }
        }
    }
}
