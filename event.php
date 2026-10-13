<?php

require '../../../zb_system/function/c_system_base.php';
$zbp->Load();
require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/settings.php';
require_once __DIR__ . '/inc/events.php';
require_once __DIR__ . '/inc/ip_filter.php';

header('Cache-Control: no-store');
http_response_code(204);
if (xz_visit_stats_server_value('REQUEST_METHOD') !== 'POST' || (int) xz_visit_stats_server_value('CONTENT_LENGTH', '0') > 4096 || strpos(strtolower(xz_visit_stats_server_value('CONTENT_TYPE')), 'application/json') !== 0) exit;
$origin = xz_visit_stats_server_value('HTTP_ORIGIN');
$host = xz_visit_stats_server_value('HTTP_HOST');
$scheme = strtolower(xz_visit_stats_server_value('HTTPS')) === 'on' ? 'https' : 'http';
if ($origin !== '' && !xz_visit_stats_v4_event_same_origin($origin, $host, $scheme)) exit;
$settings = xz_visit_stats_settings_values();
if ((int) $settings['beacon_enabled'] !== 1 || xz_visit_stats_v4_ip_is_filtered(xz_visit_stats_client_ip())) exit;
$payload = json_decode((string) file_get_contents('php://input'), true);
$payload['path_key'] = is_array($payload) ? xz_visit_stats_path_key(xz_visit_stats_normalize_path(isset($payload['path']) ? $payload['path'] : '')) : '';
$event = xz_visit_stats_v4_event_normalize($payload);
if ($event === null) exit;
$visitor = xz_visit_stats_visitor_hash(xz_visit_stats_client_ip(), xz_visit_stats_server_value('HTTP_USER_AGENT'));
$sessionId = 0;
if ($event['session_key'] !== '') {
    $rows = (array) $zbp->db->Query("SELECT se_ID FROM `{$zbp->db->dbpre}xz_visit_stats_sessions` WHERE se_SessionKey='" . str_replace("'", "''", $event['session_key']) . "' LIMIT 1");
    if (!empty($rows)) $sessionId = (int) $rows[0]['se_ID'];
}
try {
    $params = json_encode($event['params']);
    $zbp->db->Query("INSERT INTO `{$zbp->db->dbpre}xz_visit_stats_events` (ev_SessionID,ev_VisitorHash,ev_Name,ev_Params,ev_PathKey,ev_TriggeredAt,ev_UpdatedAt) VALUES (" . ($sessionId ?: 'NULL') . ",'" . str_replace("'", "''", $visitor) . "','" . str_replace("'", "''", $event['name']) . "','" . str_replace("'", "''", $params) . "','" . str_replace("'", "''", $event['path_key']) . "'," . (int) $event['triggered_at'] . ',' . time() . ')');
} catch (Throwable $exception) { }
