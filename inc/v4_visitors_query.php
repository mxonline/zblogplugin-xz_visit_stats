<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_v4_visitor_classification(array $filters): array
{
    global $zbp;
    $range = xz_visit_stats_v4_session_query_range($filters);
    $where = implode(' AND ', xz_visit_stats_v4_session_where($filters, $range));
    $table = xz_visit_stats_v4_session_query_table();
    $start = (int) $range['start'];
    $sql = 'SELECT COUNT(*) AS visitor_count,'
        . 'COALESCE(SUM(NOT EXISTS (SELECT 1 FROM ' . $table . ' prior WHERE prior.se_VisitorHash=current.se_VisitorHash AND prior.se_StartedAt<' . $start . ' LIMIT 1)),0) AS new_visitors,'
        . 'COALESCE(SUM(EXISTS (SELECT 1 FROM ' . $table . ' prior WHERE prior.se_VisitorHash=current.se_VisitorHash AND prior.se_StartedAt<' . $start . ' LIMIT 1)),0) AS returning_visitors'
        . ' FROM (SELECT sessions.se_VisitorHash FROM ' . $table . ' sessions WHERE ' . $where . " AND sessions.se_VisitorHash<>'' GROUP BY sessions.se_VisitorHash) current";
    $rows = (array) $zbp->db->Query($sql);
    $row = !empty($rows) ? $rows[0] : array();
    return array(
        'visitor_count' => (int) (isset($row['visitor_count']) ? $row['visitor_count'] : 0),
        'new_visitors' => (int) (isset($row['new_visitors']) ? $row['new_visitors'] : 0),
        'returning_visitors' => (int) (isset($row['returning_visitors']) ? $row['returning_visitors'] : 0),
    );
}

function xz_visit_stats_v4_visitor_depth(array $filters): array
{
    global $zbp;
    $range = xz_visit_stats_v4_session_query_range($filters);
    $where = implode(' AND ', xz_visit_stats_v4_session_where($filters, $range));
    $bucket = 'CASE WHEN sessions.se_PageCount >= 20 THEN 20 ELSE sessions.se_PageCount END';
    $sql = 'SELECT ' . $bucket . ' AS page_count,COUNT(*) AS sessions,COUNT(DISTINCT sessions.se_VisitorHash) AS visitors'
        . ' FROM ' . xz_visit_stats_v4_session_query_table() . ' sessions WHERE ' . $where
        . ' GROUP BY page_count ORDER BY page_count ASC LIMIT 20';
    $rows = array();
    foreach ((array) $zbp->db->Query($sql) as $row) {
        $rows[] = array('page_count' => (int) (isset($row['page_count']) ? $row['page_count'] : 0), 'sessions' => (int) (isset($row['sessions']) ? $row['sessions'] : 0), 'visitors' => (int) (isset($row['visitors']) ? $row['visitors'] : 0));
    }
    return $rows;
}

function xz_visit_stats_v4_mask_visitor_ip_rows(array $rows, string $mode): array
{
    if ($mode !== 'masked' || !function_exists('xz_visit_stats_settings_mask_ip')) return $rows;
    $masked = array();
    foreach ($rows as $row) {
        $ip = xz_visit_stats_settings_mask_ip(isset($row['ip']) ? $row['ip'] : '');
        if ($ip === '') continue;
        if (!isset($masked[$ip])) {
            $masked[$ip] = $row;
            $masked[$ip]['ip'] = $ip;
            $masked[$ip]['visits'] = 0;
            $masked[$ip]['pages'] = 0;
            $masked[$ip]['server_duration_sum'] = 0.0;
            $masked[$ip]['server_duration_samples'] = 0;
            $masked[$ip]['geo_country'] = '';
            $masked[$ip]['geo_region'] = '';
        }
        $visits = (int) (isset($row['visits']) ? $row['visits'] : 0);
        $masked[$ip]['visits'] += $visits;
        $masked[$ip]['pages'] += (int) (isset($row['pages']) ? $row['pages'] : 0);
        if (isset($row['avg_ms'])) {
            $masked[$ip]['server_duration_sum'] += (float) $row['avg_ms'] * $visits;
            $masked[$ip]['server_duration_samples'] += $visits;
        }
        $masked[$ip]['first_visit'] = min((int) $masked[$ip]['first_visit'], (int) $row['first_visit']);
        $masked[$ip]['last_visit'] = max((int) $masked[$ip]['last_visit'], (int) $row['last_visit']);
        foreach (array('not_found', 'error_4xx', 'error_5xx') as $metric) $masked[$ip][$metric] += (int) (isset($row[$metric]) ? $row[$metric] : 0);
        $masked[$ip]['avg_ms'] = $masked[$ip]['server_duration_samples'] > 0 ? $masked[$ip]['server_duration_sum'] / $masked[$ip]['server_duration_samples'] : null;
    }
    return array_values($masked);
}

function xz_visit_stats_v4_visitor_report(array $filters): array
{
    $filters = xz_visit_stats_v4_report_filters($filters);
    $summary = xz_visit_stats_v4_session_summary($filters);
    $settings = function_exists('xz_visit_stats_settings_values') ? xz_visit_stats_settings_values() : array('ip_mode' => 'full');
    $ipMode = isset($settings['ip_mode']) && $settings['ip_mode'] === 'masked' ? 'masked' : 'full';
    $dimensions = array(
        'device' => xz_visit_stats_v3_dimension_rows('vs_Device', $filters, 50),
        'os' => xz_visit_stats_v3_dimension_rows('vs_Os', $filters, 50),
        'browser' => xz_visit_stats_v3_dimension_rows('vs_Browser', $filters, 50),
        'country' => $ipMode === 'masked' ? array() : xz_visit_stats_v3_dimension_rows('vs_GeoCountry', $filters, 50),
        'region' => $ipMode === 'masked' ? array() : xz_visit_stats_v3_dimension_rows('vs_GeoRegion', $filters, 50),
        'screen' => xz_visit_stats_v3_rum_dimension_rows($filters, 'rum_Screen', 50),
        'viewport' => xz_visit_stats_v3_rum_dimension_rows($filters, 'rum_Viewport', 50),
        'language' => xz_visit_stats_v3_rum_dimension_rows($filters, 'rum_Language', 50),
    );
    $ips = xz_visit_stats_v4_mask_visitor_ip_rows(xz_visit_stats_v3_ip_rows($filters, 100), $ipMode);
    $classification = xz_visit_stats_v4_visitor_classification($filters);
    $depth = xz_visit_stats_v4_visitor_depth($filters);
    $classification['availability'] = $summary['availability'];

    return array(
        'session_count' => $summary['session_count'],
        'unique_visitors' => $summary['unique_visitors'],
        'average_page_depth' => $summary['average_page_depth'],
        'average_visitor_dwell_ms' => $summary['average_dwell_ms'],
        'lifecycle_availability' => $summary['availability'],
        'new_returning' => $classification,
        'depth' => $depth,
        'dimensions' => $dimensions,
        'ips' => $ips,
        'ip_mode' => $ipMode,
        'carrier' => array(),
        'carrier_availability' => 'unavailable',
    );
}
