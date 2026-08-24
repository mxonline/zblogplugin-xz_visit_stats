<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_v3_filters($source = null)
{
    $filters = xz_visit_stats_v2_filters($source);
    if ($source === null) $source = $_GET;
    foreach (array('ai_source', 'campaign', 'browser', 'os', 'device', 'cursor') as $key) {
        $value = xz_visit_stats_query_text(xz_visit_stats_query_value($source, $key, ''), $key === 'cursor' ? 20 : 128);
        $filters[$key] = $value;
    }
    return $filters;
}

function xz_visit_stats_v3_where($filters, $range)
{
    $where = xz_visit_stats_v2_where($filters, $range);
    foreach (array('ai_source' => 'vs_AiSource', 'campaign' => 'vs_UtmCampaign', 'browser' => 'vs_Browser', 'os' => 'vs_Os', 'device' => 'vs_Device') as $key => $column) {
        if (isset($filters[$key]) && $filters[$key] !== '') {
            $where[] = $column . ' = ' . xz_visit_stats_v2_quote($filters[$key]);
        }
    }
    return $where;
}

function xz_visit_stats_v3_dimension_rows($field, $filters, $limit = 20)
{
    global $zbp;
    $allowed = array('vs_Browser' => 'browser', 'vs_Os' => 'os', 'vs_Device' => 'device', 'vs_SourceType' => 'source_type', 'vs_SourceDomain' => 'source_domain', 'vs_AiSource' => 'ai_source', 'vs_UtmCampaign' => 'campaign', 'vs_GeoCountry' => 'country', 'vs_GeoRegion' => 'region');
    if (!isset($allowed[$field])) return array();
    $range = xz_visit_stats_v2_range($filters);
    $where = implode(' AND ', xz_visit_stats_v3_where($filters, $range));
    $limit = max(1, min(100, (int) $limit));
    $sql = 'SELECT ' . $field . ' AS name, COUNT(*) AS visits, SUM(vs_IsBot=0) AS human_pv, SUM(vs_IsBot=1) AS bot_pv, COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_VisitorHash END) AS human_uv FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where . ' AND ' . $field . " <> '' GROUP BY " . $field . ' ORDER BY visits DESC, name ASC LIMIT ' . $limit;
    return (array) $zbp->db->Query($sql);
}

function xz_visit_stats_v3_campaign_rows($filters, $limit = 50)
{
    return xz_visit_stats_v3_dimension_rows('vs_UtmCampaign', $filters, $limit);
}

function xz_visit_stats_v3_ip_rows($filters, $limit = 50)
{
    global $zbp;
    $range = xz_visit_stats_v2_range($filters);
    $where = implode(' AND ', xz_visit_stats_v3_where($filters, $range));
    $limit = max(1, min(100, (int) $limit));
    $sql = 'SELECT vs_IP AS ip, COUNT(*) AS visits, COUNT(DISTINCT vs_PathKey) AS pages, MIN(vs_VisitedAt) AS first_visit, MAX(vs_VisitedAt) AS last_visit, AVG(vs_DurationMs) AS avg_ms, SUM(vs_StatusCode=404) AS not_found, SUM(vs_StatusCode BETWEEN 400 AND 499) AS error_4xx, SUM(vs_StatusCode BETWEEN 500 AND 599) AS error_5xx FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where . ' AND vs_IP <> \'\' GROUP BY vs_IP ORDER BY visits DESC, last_visit DESC LIMIT ' . $limit;
    return (array) $zbp->db->Query($sql);
}

function xz_visit_stats_v3_active_summary($window = 5)
{
    global $zbp;
    $window = in_array((int) $window, array(5, 10, 15, 30), true) ? (int) $window : 5;
    $start = time() - $window * 60;
    $table = xz_visit_stats_v2_table();
    $rows = (array) $zbp->db->Query('SELECT COUNT(*) AS pv, COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_VisitorHash END) AS uv, COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_IP END) AS ip, SUM(vs_IsBot=1) AS bot_pv FROM ' . $table . ' WHERE vs_VisitedAt>=' . $start);
    $row = !empty($rows) ? $rows[0] : array();
    foreach (array('pv', 'uv', 'ip', 'bot_pv') as $key) $row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
    $row['window'] = $window;
    return $row;
}

function xz_visit_stats_v3_hour_rows($filters, $limit = 48)
{
    global $zbp;
    $range = xz_visit_stats_v2_range($filters);
    $table = xz_visit_stats_rollup_hourly_table();
    if (!$zbp->db->ExistTable($table)) return array();
    $zone = new DateTimeZone($range['timezone']);
    $startDate = new DateTime('@' . $range['start']); $startDate->setTimezone($zone);
    $endDate = new DateTime('@' . $range['end']); $endDate->setTimezone($zone);
    $start = $startDate->format('Y-m-d H:00');
    $end = $endDate->format('Y-m-d H:00');
    $sql = "SELECT rh_Hour AS hour, SUM(rh_VisitorPV) AS human_pv, SUM(rh_BotPV) AS bot_pv, SUM(rh_Error4xx) AS error_4xx, SUM(rh_Error5xx) AS error_5xx FROM `{$table}` WHERE rh_Dimension='site/all' AND rh_Hour>=" . xz_visit_stats_v2_quote($start) . " AND rh_Hour<" . xz_visit_stats_v2_quote($end) . " GROUP BY rh_Hour ORDER BY rh_Hour ASC LIMIT " . max(1, min(168, (int) $limit));
    return (array) $zbp->db->Query($sql);
}

function xz_visit_stats_v3_daily_trend($filters)
{
    global $zbp;
    $range = xz_visit_stats_v2_range($filters);
    $zone = new DateTimeZone($range['timezone']);
    $day = new DateTime('@' . $range['start']); $day->setTimezone($zone); $day->setTime(0, 0, 0);
    $end = new DateTime('@' . $range['end']); $end->setTimezone($zone); $rows = array();
    while ($day < $end) {
        $next = clone $day; $next->modify('+1 day');
        $dayFilters = $filters; $dayFilters['range'] = 'custom'; $dayFilters['start'] = $day->format('Y-m-d H:i'); $dayFilters['end'] = $next->format('Y-m-d H:i');
        $summary = xz_visit_stats_v2_exact_summary($dayFilters, xz_visit_stats_v2_range($dayFilters));
        $rows[] = array('label' => $day->format('Y-m-d'), 'pv' => (int) $summary['total_pv'], 'uv' => (int) $summary['visitor_uv'], 'ip' => (int) $summary['visitor_ip'], 'human_pv' => (int) $summary['visitor_pv'], 'bot_pv' => (int) $summary['bot_pv'], 'error_4xx' => (int) $summary['error_4xx'], 'error_5xx' => (int) $summary['error_5xx']);
        $day = $next;
    }
    return $rows;
}

function xz_visit_stats_v3_comparison($filters)
{
    $range = xz_visit_stats_v2_range($filters);
    $duration = max(1, $range['end'] - $range['start']);
    $previous = $filters; $previous['range'] = 'custom';
    $zone = new DateTimeZone($range['timezone']);
    $start = new DateTime('@' . ($range['start'] - $duration)); $start->setTimezone($zone);
    $end = new DateTime('@' . $range['start']); $end->setTimezone($zone);
    $previous['start'] = $start->format('Y-m-d H:i'); $previous['end'] = $end->format('Y-m-d H:i');
    return array('current' => xz_visit_stats_v2_exact_summary($filters, $range), 'previous' => xz_visit_stats_v2_exact_summary($previous, xz_visit_stats_v2_range($previous)));
}

function xz_visit_stats_v3_keyset_records($filters, $cursor = '', $limit = 50)
{
    global $zbp;
    $range = xz_visit_stats_v2_range($filters);
    $where = xz_visit_stats_v3_where($filters, $range);
    if (preg_match('/^[0-9]+$/', (string) $cursor) === 1) $where[] = 'vs_ID < ' . (int) $cursor;
    $limit = max(1, min(100, (int) $limit));
    $sql = 'SELECT * FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY vs_ID DESC LIMIT ' . $limit;
    return (array) $zbp->db->Query($sql);
}

function xz_visit_stats_v3_csv_safe($value)
{
    $value = (string) $value;
    return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
}

function xz_visit_stats_v3_export_rows($filters, $cursor = '', $limit = 5000)
{
    return xz_visit_stats_v3_keyset_records($filters, $cursor, max(1, min(5000, (int) $limit)));
}

function xz_visit_stats_v3_saved_filters($view)
{
    global $zbp;
    $userId = isset($zbp->user->ID) ? (int) $zbp->user->ID : 0;
    $table = xz_visit_stats_saved_filters_table();
    return (array) $zbp->db->Query("SELECT sf_ID,sf_Name,sf_View,sf_Filters,sf_UpdatedAt FROM `{$table}` WHERE sf_UserID={$userId} AND sf_View=" . xz_visit_stats_v2_quote($view) . ' ORDER BY sf_UpdatedAt DESC LIMIT 50');
}

function xz_visit_stats_v3_save_filter($view, $name, $filters)
{
    global $zbp;
    $userId = isset($zbp->user->ID) ? (int) $zbp->user->ID : 0;
    $name = xz_visit_stats_limit(trim($name), 128);
    if ($userId <= 0 || $name === '') return false;
    $table = xz_visit_stats_saved_filters_table();
    $json = function_exists('wp_json_encode') ? wp_json_encode($filters) : json_encode($filters);
    $now = time();
    $sql = "INSERT INTO `{$table}` (sf_UserID,sf_Name,sf_View,sf_Filters,sf_CreatedAt,sf_UpdatedAt) VALUES ({$userId}," . xz_visit_stats_v2_quote($name) . ',' . xz_visit_stats_v2_quote($view) . ',' . xz_visit_stats_v2_quote($json) . ",{$now},{$now})";
    $zbp->db->Query($sql);
    return true;
}
