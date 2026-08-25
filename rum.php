<?php

require '../../../zb_system/function/c_system_base.php';
$zbp->Load();
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/settings.php';
require_once __DIR__ . '/inc/rollup.php';
require_once __DIR__ . '/inc/ua.php';

header('Cache-Control: no-store');
http_response_code(204);

function xz_visit_stats_rum_reject()
{
    exit;
}

if (xz_visit_stats_server_value('REQUEST_METHOD') !== 'POST') xz_visit_stats_rum_reject();
if ((int) xz_visit_stats_server_value('CONTENT_LENGTH', '0') > 16384) xz_visit_stats_rum_reject();
$contentType = strtolower(xz_visit_stats_server_value('CONTENT_TYPE'));
if (strpos($contentType, 'application/json') !== 0) xz_visit_stats_rum_reject();

$origin = xz_visit_stats_server_value('HTTP_ORIGIN');
$host = xz_visit_stats_server_value('HTTP_HOST');
if ($origin !== '' && !xz_visit_stats_rum_same_origin($origin, $host)) xz_visit_stats_rum_reject();
$settings = xz_visit_stats_settings_values();
if ((int) $settings['beacon_enabled'] !== 1) xz_visit_stats_rum_reject();
$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);
if (!is_array($payload)) xz_visit_stats_rum_reject();

function xz_visit_stats_rum_number($payload, $key, $max, $decimals = 2)
{
    if (!isset($payload[$key]) || !is_numeric($payload[$key])) return null;
    $value = (float) $payload[$key];
    if ($value < 0 || $value > $max) return null;
    return round($value, $decimals);
}
function xz_visit_stats_rum_text($payload, $key, $length)
{
    return isset($payload[$key]) && !is_array($payload[$key]) ? xz_visit_stats_limit($payload[$key], $length) : '';
}

function xz_visit_stats_rum_same_origin($origin, $host)
{
    $originParts = parse_url($origin);
    if (!is_array($originParts) || empty($originParts['scheme']) || empty($originParts['host'])) return false;
    $requestParts = parse_url('http://' . ltrim((string) $host, '/'));
    if (!is_array($requestParts) || empty($requestParts['host'])) return false;
    $originScheme = strtolower((string) $originParts['scheme']);
    $requestScheme = strtolower(xz_visit_stats_server_value('HTTPS')) === 'on' || xz_visit_stats_server_value('SERVER_PORT') === '443' ? 'https' : 'http';
    if ($originScheme !== $requestScheme || strtolower((string) $originParts['host']) !== strtolower((string) $requestParts['host'])) return false;
    $originPort = isset($originParts['port']) ? (int) $originParts['port'] : ($originScheme === 'https' ? 443 : 80);
    $requestPort = isset($requestParts['port']) ? (int) $requestParts['port'] : ($requestScheme === 'https' ? 443 : 80);
    return $originPort === $requestPort;
}

$path = xz_visit_stats_normalize_path(xz_visit_stats_rum_text($payload, 'path', 2048));
$ua = xz_visit_stats_parse_ua(xz_visit_stats_server_value('HTTP_USER_AGENT'), false);
$data = array(
    'rum_VisitorHash' => xz_visit_stats_visitor_hash(xz_visit_stats_client_ip(), xz_visit_stats_server_value('HTTP_USER_AGENT')),
    'rum_Path' => $path, 'rum_PathKey' => xz_visit_stats_path_key($path),
    'rum_Browser' => xz_visit_stats_limit($ua['browser'], 64), 'rum_Os' => xz_visit_stats_limit($ua['os'], 64), 'rum_Device' => xz_visit_stats_limit($ua['device'], 32),
    'rum_Language' => xz_visit_stats_rum_text($payload, 'language', 32), 'rum_Screen' => xz_visit_stats_rum_text($payload, 'screen', 32), 'rum_Viewport' => xz_visit_stats_rum_text($payload, 'viewport', 32),
    'rum_VisitedAt' => time(),
);
foreach (array('lcp' => array('rum_LCP', 120000, 2), 'inp' => array('rum_INP', 120000, 2), 'cls' => array('rum_CLS', 10, 4), 'ttfb' => array('rum_TTFB', 120000, 2), 'fcp' => array('rum_FCP', 120000, 2)) as $key => $definition) {
    $value = xz_visit_stats_rum_number($payload, $key, $definition[1], $definition[2]);
    if ($value !== null) {
        $data[$definition[0]] = $value;
    }
}
try {
    $sql = $zbp->db->sql->Insert(xz_visit_stats_rum_table(), $data);
    $zbp->db->Query($sql);
} catch (Throwable $exception) {
    // RUM is best-effort and must never affect the page response.
}
