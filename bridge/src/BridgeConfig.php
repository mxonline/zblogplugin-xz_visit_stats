<?php

namespace XzVisitStats\Bridge;

use InvalidArgumentException;
use RuntimeException;

final class BridgeConfig
{
    private string $repoRoot;
    private array $config;

    private function __construct(string $repoRoot, array $config)
    {
        $this->repoRoot = $repoRoot;
        $this->config = $config;
    }

    public static function load(string $repoRoot): self
    {
        $repoRoot = rtrim(str_replace('\\', '/', $repoRoot), '/');
        if ($repoRoot === '' || !is_dir($repoRoot)) {
            throw new InvalidArgumentException('Bridge repository root does not exist.');
        }

        $local = $repoRoot . '/bridge/config.json';
        $example = $repoRoot . '/bridge/config.example.json';
        $path = is_file($local) ? $local : $example;
        if (!is_file($path)) {
            throw new RuntimeException('Bridge config is missing. Expected bridge/config.json or bridge/config.example.json.');
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Unable to read Bridge config.');
        }

        $config = json_decode($raw, true);
        if (!is_array($config)) {
            throw new InvalidArgumentException('Bridge config must be a JSON object.');
        }

        $model = getenv('XZ_BRIDGE_GPT_MODEL');
        if (is_string($model) && trim($model) !== '') {
            if (!isset($config['controller']) || !is_array($config['controller'])) {
                $config['controller'] = array();
            }
            $config['controller']['model'] = trim($model);
        }

        $config['repo_root'] = $repoRoot;
        self::validate($config);

        return new self($repoRoot, $config);
    }

    public function get(string $path, mixed $default = null): mixed
    {
        if ($path === '') {
            return $this->config;
        }

        $value = $this->config;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public function repoRoot(): string
    {
        return $this->repoRoot;
    }

    public function safeArray(): array
    {
        return $this->config;
    }

    private static function validate(array $config): void
    {
        if (!isset($config['controller']) || !is_array($config['controller'])) {
            throw new InvalidArgumentException('Bridge config controller section is required.');
        }
        if (!isset($config['controller']['model']) || !is_string($config['controller']['model']) || trim($config['controller']['model']) === '') {
            throw new InvalidArgumentException('Bridge controller.model is required.');
        }
        if (!isset($config['runtime']) || !is_array($config['runtime'])) {
            throw new InvalidArgumentException('Bridge config runtime section is required.');
        }
        if (!isset($config['runtime']['state_file']) || !is_string($config['runtime']['state_file']) || trim($config['runtime']['state_file']) === '') {
            throw new InvalidArgumentException('Bridge runtime.state_file is required.');
        }
        if (!isset($config['phases_file']) || !is_string($config['phases_file']) || trim($config['phases_file']) === '') {
            throw new InvalidArgumentException('Bridge phases_file is required.');
        }
    }
}
