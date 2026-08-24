<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}


function xz_visit_stats_collect()
{
    global $zbp;

    static $collected = false;
    $settings = xz_visit_stats_settings_values();
    if ($collected || $settings['enabled'] !== 1 || !xz_visit_stats_should_collect()) {
        return;
    }
    if ($settings['exclude_admin'] === 1 && xz_visit_stats_settings_is_admin_visitor()) {
        return;
    }
    $collected = true;

    $ip = xz_visit_stats_client_ip();
    $userAgent = xz_visit_stats_limit(xz_visit_stats_server_value('HTTP_USER_AGENT'), 8192);
    $bot = xz_visit_stats_detect_bot($userAgent);
    if ($bot['is_bot'] && !xz_visit_stats_settings_record_bot($bot['name'], $settings)) {
        return;
    }
    $ua = xz_visit_stats_parse_ua($userAgent, $bot['is_bot']);
    $recordedIp = $settings['ip_mode'] === 'masked' ? xz_visit_stats_settings_mask_ip($ip) : $ip;
    $path = xz_visit_stats_normalize_path(xz_visit_stats_request_path());
    $source = xz_visit_stats_source_dimensions($settings['record_referer'] === 1 ? xz_visit_stats_server_value('HTTP_REFERER') : '');
    $page = xz_visit_stats_page_context();
    $geo = xz_visit_stats_geo_lookup($ip);

    $data = array(
        'vs_IP' => $recordedIp,
        'vs_VisitorHash' => xz_visit_stats_visitor_hash($ip, $userAgent),
        'vs_Url' => xz_visit_stats_limit(xz_visit_stats_request_url(), 16384),
        'vs_Path' => $path,
        'vs_PathKey' => xz_visit_stats_path_key($path),
        'vs_Referer' => $settings['record_referer'] === 1 ? xz_visit_stats_limit(xz_visit_stats_server_value('HTTP_REFERER'), 16384) : '',
        'vs_UserAgent' => $settings['record_user_agent'] === 1 ? $userAgent : '',
        'vs_UaType' => $settings['record_user_agent'] === 1 ? xz_visit_stats_limit($ua['type'], 32) : '',
        'vs_Browser' => $settings['record_user_agent'] === 1 ? xz_visit_stats_limit($ua['browser'], 64) : '',
        'vs_Os' => $settings['record_user_agent'] === 1 ? xz_visit_stats_limit($ua['os'], 64) : '',
        'vs_Device' => $settings['record_user_agent'] === 1 ? xz_visit_stats_limit($ua['device'], 32) : '',
        'vs_IsBot' => $bot['is_bot'] ? 1 : 0,
        'vs_BotName' => xz_visit_stats_limit($bot['name'], 64),
        'vs_StatusCode' => xz_visit_stats_response_status(),
        'vs_DurationMs' => xz_visit_stats_duration_ms(),
        'vs_VisitedAt' => time(),
        'vs_SourceType' => $source['type'],
        'vs_SourceDomain' => $source['domain'],
        'vs_AiSource' => $source['ai'],
        'vs_UtmSource' => $source['utm']['source'],
        'vs_UtmMedium' => $source['utm']['medium'],
        'vs_UtmCampaign' => $source['utm']['campaign'],
        'vs_UtmContent' => $source['utm']['content'],
        'vs_UtmTerm' => $source['utm']['term'],
        'vs_PageTitle' => $page['title'],
        'vs_PostID' => $page['post_id'],
        'vs_AiCrawler' => xz_visit_stats_ai_crawler($userAgent),
        'vs_GeoCountry' => $geo['country'],
        'vs_GeoRegion' => $geo['region'],
    );

    try {
        $sql = $zbp->db->sql->Insert($GLOBALS['table']['xz_visit_stats_log'], $data);
        $zbp->db->Query($sql);

    } catch (Exception $exception) {
    }
}
