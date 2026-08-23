<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_collect()
{
    global $zbp;

    static $collected = false;
    if ($collected || !xz_visit_stats_should_collect()) {
        return;
    }
    $collected = true;

    $ip = xz_visit_stats_client_ip();
    $userAgent = xz_visit_stats_limit(xz_visit_stats_server_value('HTTP_USER_AGENT'), 8192);
    $bot = xz_visit_stats_detect_bot($userAgent);
    $ua = xz_visit_stats_parse_ua($userAgent, $bot['is_bot']);

    $data = array(
        'vs_IP'          => $ip,
        'vs_VisitorHash' => xz_visit_stats_visitor_hash($ip, $userAgent),
        'vs_Url'         => xz_visit_stats_limit(xz_visit_stats_request_url(), 16384),
        'vs_Path'        => xz_visit_stats_request_path(),
        'vs_Referer'     => xz_visit_stats_limit(xz_visit_stats_server_value('HTTP_REFERER'), 16384),
        'vs_UserAgent'   => $userAgent,
        'vs_UaType'      => xz_visit_stats_limit($ua['type'], 32),
        'vs_Browser'     => xz_visit_stats_limit($ua['browser'], 64),
        'vs_Os'          => xz_visit_stats_limit($ua['os'], 64),
        'vs_Device'      => xz_visit_stats_limit($ua['device'], 32),
        'vs_IsBot'       => $bot['is_bot'] ? 1 : 0,
        'vs_BotName'     => xz_visit_stats_limit($bot['name'], 64),
        'vs_StatusCode'  => xz_visit_stats_response_status(),
        'vs_DurationMs'  => xz_visit_stats_duration_ms(),
        'vs_VisitedAt'   => time(),
    );

    try {
        $sql = $zbp->db->sql->Insert($GLOBALS['table']['xz_visit_stats_log'], $data);
        $zbp->db->Query($sql);
    } catch (Exception $exception) {
        // Statistics must never interrupt the frontend response.
    }
}
