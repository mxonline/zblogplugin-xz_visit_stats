<?php

namespace XzVisitStats\Bridge;

use RuntimeException;

final class HttpTransport
{
    private $handler;

    public function __construct(?callable $handler = null)
    {
        $this->handler = $handler;
    }

    public function postJson(string $url, array $headers, array $body, int $timeoutSeconds): array
    {
        if ($this->handler !== null) {
            $result = ($this->handler)($url, $headers, $body, $timeoutSeconds);
            if (!is_array($result)) {
                throw new RuntimeException('HTTP transport handler must return an array.');
            }
            return $result;
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Bridge HTTP requests.');
        }

        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to encode Bridge HTTP request body.');
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new RuntimeException('Unable to initialize Bridge HTTP request.');
        }

        $responseHeaders = array();
        curl_setopt_array($curl, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(30, max(1, $timeoutSeconds)),
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $line) use (&$responseHeaders): int {
                $trimmed = trim($line);
                if ($trimmed !== '' && str_contains($trimmed, ':')) {
                    [$name, $value] = explode(':', $trimmed, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }
                return strlen($line);
            },
        ));

        $responseBody = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($responseBody === false) {
            throw new RuntimeException('Bridge HTTP request failed: ' . ($error !== '' ? $error : 'unknown cURL error'));
        }

        return array(
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => (string)$responseBody,
        );
    }
}
