<?php

if (!defined('ZBP_PATH')) exit('Access denied');

function xz_visit_stats_v4_event_same_origin($origin, $host, $scheme)
{
    $origin = trim((string) $origin);
    $host = trim((string) $host);
    $scheme = strtolower(trim((string) $scheme));
    if ($origin === '' || $host === '' || ($scheme !== 'http' && $scheme !== 'https')) return false;

    $originParts = parse_url($origin);
    if (!is_array($originParts) || !isset($originParts['scheme'], $originParts['host']) || isset($originParts['path']) || isset($originParts['query']) || isset($originParts['fragment'])) return false;
    $requestParts = parse_url($scheme . '://' . $host);
    if (!is_array($requestParts) || !isset($requestParts['host'])) return false;

    $originScheme = strtolower($originParts['scheme']);
    $originHost = strtolower($originParts['host']);
    $requestHost = strtolower($requestParts['host']);
    $originPort = isset($originParts['port']) ? (int) $originParts['port'] : ($originScheme === 'https' ? 443 : 80);
    $requestPort = isset($requestParts['port']) ? (int) $requestParts['port'] : ($scheme === 'https' ? 443 : 80);
    return ($originScheme === $scheme && $originHost === $requestHost && $originPort === $requestPort);
}

function xz_visit_stats_v4_event_normalize($payload)
{
    if (!is_array($payload) || !isset($payload['name'], $payload['path_key'], $payload['triggered_at'])) return null;
    $name = (string) $payload['name']; $path = (string) $payload['path_key'];
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/', $name) || !preg_match('/^[a-f0-9]{64}$/', $path) || !is_numeric($payload['triggered_at'])) return null;
    $allowed = array('category' => true, 'action' => true, 'label' => true, 'method' => true, 'plan' => true, 'value' => true);
    $params = array();
    foreach ((array) (isset($payload['params']) ? $payload['params'] : array()) as $key => $value) {
        if (!isset($allowed[$key]) || is_array($value) || is_object($value)) continue;
        $params[$key] = is_numeric($value) ? 0 + $value : xz_visit_stats_limit((string) $value, 128);
    }
    $encoded = json_encode($params);
    if (!is_string($encoded) || strlen($encoded) > 2048) return null;
    return array('name' => $name, 'path_key' => $path, 'triggered_at' => max(0, (int) $payload['triggered_at']), 'params' => $params, 'session_key' => isset($payload['session_key']) && preg_match('/^[a-f0-9]{64}$/', (string) $payload['session_key']) ? (string) $payload['session_key'] : '');
}
