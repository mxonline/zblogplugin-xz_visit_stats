<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_ip_in_cidr($ip, $cidr)
{
    $parts = explode('/', trim((string) $cidr), 2);
    $network = trim($parts[0]);
    if (filter_var($ip, FILTER_VALIDATE_IP) === false || filter_var($network, FILTER_VALIDATE_IP) === false) {
        return false;
    }
    $max = strpos($network, ':') !== false ? 128 : 32;
    $bits = isset($parts[1]) && $parts[1] !== '' ? (int) $parts[1] : $max;
    if ($bits < 0 || $bits > $max) {
        return false;
    }
    $ipBin = @inet_pton($ip);
    $netBin = @inet_pton($network);
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
        return false;
    }
    $full = (int) floor($bits / 8);
    $rest = $bits % 8;
    if ($full > 0 && substr($ipBin, 0, $full) !== substr($netBin, 0, $full)) {
        return false;
    }
    if ($rest === 0) {
        return true;
    }
    $mask = (0xff << (8 - $rest)) & 0xff;
    return (ord($ipBin[$full]) & $mask) === (ord($netBin[$full]) & $mask);
}

function xz_visit_stats_trusted_proxy_ip($remote)
{
    global $zbp;
    $settings = function_exists('xz_visit_stats_settings_values') ? xz_visit_stats_settings_values() : array();
    $trusted = isset($settings['trusted_proxies']) ? preg_split('/[\r\n,]+/', $settings['trusted_proxies']) : array();
    $isTrusted = false;
    foreach ((array) $trusted as $entry) {
        $entry = trim($entry);
        if ($entry !== '' && xz_visit_stats_ip_in_cidr($remote, $entry)) {
            $isTrusted = true;
            break;
        }
    }
    if (!$isTrusted) {
        return $remote;
    }
    $header = isset($settings['real_ip_header']) && $settings['real_ip_header'] !== '' ? $settings['real_ip_header'] : 'X-Forwarded-For';
    $value = xz_visit_stats_server_value('HTTP_' . strtoupper(str_replace('-', '_', $header)));
    if ($value === '' && $header === 'X-Forwarded-For') {
        $value = xz_visit_stats_server_value('HTTP_CF_CONNECTING_IP');
    }
    $candidates = array_map('trim', explode(',', $value));
    foreach ($candidates as $candidate) {
        if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
            return $candidate;
        }
    }
    return $remote;
}

function xz_visit_stats_source_dimensions($referer)
{
    global $zbp;
    $referer = (string) $referer;
    $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
    $site = is_object($zbp) && isset($zbp->host) ? strtolower((string) parse_url($zbp->host, PHP_URL_HOST)) : '';
    $type = 'other';
    if ($referer === '') {
        $type = 'direct';
    } elseif ($host !== '' && ($host === $site || $host === 'localhost' || $host === '127.0.0.1')) {
        $type = 'internal';
    } elseif (preg_match('/(^|\.)(google\.[^\/]+|bing\.com|baidu\.(com|cn)|sogou\.com|so\.com|360\.cn)$/i', $host)) {
        $type = 'search';
    } elseif (preg_match('/(^|\.)(facebook\.com|x\.com|twitter\.com|weibo\.com|wechat\.com|qq\.com|douyin\.com|zhihu\.com|bilibili\.com|xiaohongshu\.com)$/i', $host)) {
        $type = 'social';
    } elseif ($host !== '') {
        $type = 'external';
    }
    $ai = '';
    $aiMap = array('chat.openai.com' => 'ChatGPT', 'chatgpt.com' => 'ChatGPT', 'claude.ai' => 'Claude', 'gemini.google.com' => 'Gemini', 'perplexity.ai' => 'Perplexity', 'copilot.microsoft.com' => 'Copilot', 'grok.com' => 'Grok', 'deepseek.com' => 'DeepSeek');
    foreach ($aiMap as $needle => $name) {
        if ($host === $needle || substr($host, -strlen('.' . $needle)) === '.' . $needle) {
            $ai = $name;
            $type = 'ai';
            break;
        }
    }
    $query = array();
    parse_str((string) parse_url($referer, PHP_URL_QUERY), $query);
    $utm = array();
    foreach (array('source', 'medium', 'campaign', 'content', 'term') as $key) {
        $value = isset($query['utm_' . $key]) && !is_array($query['utm_' . $key]) ? (string) $query['utm_' . $key] : '';
        $utm[$key] = xz_visit_stats_limit($value, $key === 'campaign' || $key === 'content' || $key === 'term' ? 255 : 128);
    }
    if ($utm['source'] !== '' || $utm['medium'] !== '' || $utm['campaign'] !== '') {
        $type = 'campaign';
    }
    return array('type' => $type, 'domain' => xz_visit_stats_limit($host, 253), 'ai' => $ai, 'utm' => $utm);
}

function xz_visit_stats_ai_crawler($userAgent)
{
    $map = array('GPTBot' => 'GPTBot', 'OAI-SearchBot' => 'OAI-SearchBot', 'ClaudeBot' => 'ClaudeBot', 'Google-Extended' => 'Google-Extended', 'PerplexityBot' => 'PerplexityBot', 'Applebot-Extended' => 'Applebot-Extended', 'Bytespider' => 'Bytespider');
    foreach ($map as $needle => $name) {
        if (stripos((string) $userAgent, $needle) !== false) {
            return $name;
        }
    }
    return '';
}

function xz_visit_stats_page_context()
{
    $article = isset($GLOBALS['article']) && is_object($GLOBALS['article']) ? $GLOBALS['article'] : null;
    return array('title' => $article && isset($article->Title) ? xz_visit_stats_limit($article->Title, 512) : '', 'post_id' => $article && isset($article->ID) ? (int) $article->ID : 0);
}

function xz_visit_stats_geo_lookup($ip)
{
    // Geo databases are optional. Never call a remote API from the collector.
    if (function_exists('geoip_country_code_by_name') && filter_var($ip, FILTER_VALIDATE_IP) !== false) {
        $country = (string) @geoip_country_code_by_name($ip);
        return array('country' => xz_visit_stats_limit($country, 64), 'region' => '');
    }
    return array('country' => '', 'region' => '');
}
