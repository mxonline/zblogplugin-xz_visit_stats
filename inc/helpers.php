<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_server_value($name, $default = '')
{
    if (!isset($_SERVER[$name]) || is_array($_SERVER[$name])) {
        return $default;
    }

    return (string) $_SERVER[$name];
}

function xz_visit_stats_limit($value, $length)
{
    $value = str_replace("\0", '', (string) $value);
    if (function_exists('mb_strcut')) {
        return mb_strcut($value, 0, $length, 'UTF-8');
    }

    return substr($value, 0, $length);
}

function xz_visit_stats_client_ip()
{
    $ip = trim(xz_visit_stats_server_value('REMOTE_ADDR'));
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
        return xz_visit_stats_limit($ip, 45);
    }

    return '';
}

function xz_visit_stats_request_path()
{
    $requestUri = xz_visit_stats_server_value('REQUEST_URI', '/');
    $path = parse_url($requestUri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = '/';
    }

    return xz_visit_stats_limit($path, 2048);
}

function xz_visit_stats_request_url()
{
    global $zbp;

    $https = !empty($zbp->isHttps)
        || strtolower(xz_visit_stats_server_value('HTTPS')) === 'on'
        || xz_visit_stats_server_value('SERVER_PORT') === '443';
    $scheme = $https ? 'https' : 'http';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', xz_visit_stats_server_value('HTTP_HOST'));
    if ($host === '') {
        $host = xz_visit_stats_server_value('SERVER_NAME', 'localhost');
    }

    return $scheme . '://' . xz_visit_stats_limit($host, 255)
        . xz_visit_stats_server_value('REQUEST_URI', '/');
}

function xz_visit_stats_visitor_hash($ip, $userAgent)
{
    global $zbp;

    $key = (string) $zbp->Config('xz_visit_stats')->visitor_secret;
    if ($key === '') {
        $key = xz_visit_stats_ensure_secret();
    }

    return hash_hmac('sha256', $ip . '|' . $userAgent, $key);
}

function xz_visit_stats_ensure_secret()
{
    global $zbp;

    $config = $zbp->Config('xz_visit_stats');
    $secret = (string) $config->visitor_secret;
    if ($secret !== '') {
        return $secret;
    }

    if (function_exists('random_bytes')) {
        $bytes = random_bytes(32);
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes(32);
    } else {
        $siteId = isset($zbp->option['ZC_BLOG_CLSID'])
            ? (string) $zbp->option['ZC_BLOG_CLSID']
            : (string) $zbp->guid;
        $bytes = hash('sha256', uniqid($siteId, true) . mt_rand(), true);
    }

    $secret = bin2hex($bytes);
    $config->visitor_secret = $secret;
    $zbp->SaveConfig('xz_visit_stats');

    return $secret;
}

function xz_visit_stats_response_status()
{
    $status = function_exists('http_response_code') ? http_response_code() : 200;
    $status = (int) $status;

    return ($status >= 100 && $status <= 599) ? $status : 200;
}

function xz_visit_stats_duration_ms()
{
    $start = isset($_SERVER['_start_time']) ? (float) $_SERVER['_start_time'] : 0.0;
    if ($start <= 0 && isset($_SERVER['REQUEST_TIME_FLOAT'])) {
        $start = (float) $_SERVER['REQUEST_TIME_FLOAT'];
    }
    if ($start <= 0) {
        return 0;
    }

    return max(0, (int) round((microtime(true) - $start) * 1000));
}

function xz_visit_stats_is_internal_path($path)
{
    $decoded = rawurldecode(str_replace('\\', '/', (string) $path));
    $decoded = '/' . ltrim($decoded, '/');

    return preg_match(
        '#^/(?:zb_system(?:/|$)|zb_install(?:/|$)|zb_users/plugin(?:/|$))#i',
        $decoded
    ) === 1;
}

function xz_visit_stats_is_static_asset_path($requestUri)
{
    $path = parse_url((string) $requestUri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return false;
    }

    $path = rawurldecode(str_replace('\\', '/', $path));
    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    $staticExtensions = array(
        'css', 'js', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico',
        'woff', 'woff2', 'ttf', 'eot', 'map',
    );

    return in_array($extension, $staticExtensions, true);
}

function xz_visit_stats_should_collect()
{
    global $zbp;

    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        return false;
    }
    if (!empty($zbp->ismanage)) {
        return false;
    }

    $requestUri = xz_visit_stats_server_value('REQUEST_URI', '/');
    $path = xz_visit_stats_request_path();

    return !xz_visit_stats_is_internal_path($path)
        && !xz_visit_stats_is_static_asset_path($requestUri);
}
