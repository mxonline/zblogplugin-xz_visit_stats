<?php

if (!defined('ZBP_PATH')) exit('Access denied');

function xz_visit_stats_v4_lifecycle_duration($payload, $nowMs)
{
    if (!is_array($payload) || array_key_exists('vs_DurationMs', $payload)) return null;
    if (!isset($payload['entered_at'], $payload['left_at']) || !is_numeric($payload['entered_at']) || !is_numeric($payload['left_at'])) return null;
    $entered = (int) $payload['entered_at']; $left = (int) $payload['left_at'];
    if ($entered < 0 || $left < $entered || $left > (int) $nowMs + 300000) return null;
    $duration = $left - $entered;
    return $duration <= 43200000 ? $duration : null;
}

function xz_visit_stats_v4_lifecycle_normalize($payload, $nowMs)
{
    $duration = xz_visit_stats_v4_lifecycle_duration($payload, $nowMs);
    if ($duration === null || !isset($payload['session_key'], $payload['path_key'], $payload['sequence'])) return null;
    $key = (string) $payload['session_key']; $path = (string) $payload['path_key'];
    if (!preg_match('/^[a-f0-9]{64}$/', $key) || !preg_match('/^[a-f0-9]{64}$/', $path) || (int) $payload['sequence'] < 1) return null;
    return array('session_key' => $key, 'path_key' => $path, 'sequence' => (int) $payload['sequence'], 'left_at' => (int) $payload['left_at'], 'duration_ms' => $duration, 'exit_reason' => isset($payload['exit_reason']) ? xz_visit_stats_limit($payload['exit_reason'], 24) : 'pagehide');
}
