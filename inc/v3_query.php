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
    $baseFilters = $filters;
    $baseFilters['source_type'] = 'all';
    $baseFilters['source_domain'] = '';
    $where = xz_visit_stats_v2_where($baseFilters, $range);
    if (isset($filters['source_type']) && $filters['source_type'] !== 'all') $where[] = '(' . xz_visit_stats_v2_source_case() . ') = ' . xz_visit_stats_v2_quote($filters['source_type']);
    if (isset($filters['source_domain']) && $filters['source_domain'] !== '') $where[] = xz_visit_stats_v2_source_domain_expr() . ' = ' . xz_visit_stats_v2_quote($filters['source_domain']);
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
    $allowed = array('vs_Browser' => 'browser', 'vs_Os' => 'os', 'vs_Device' => 'device', 'vs_SourceType' => 'source_type', 'vs_SourceDomain' => 'source_domain', 'vs_AiSource' => 'ai_source', 'vs_AiCrawler' => 'ai_crawler', 'vs_UtmCampaign' => 'campaign', 'vs_GeoCountry' => 'country', 'vs_GeoRegion' => 'region');
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
    $sql = 'SELECT vs_IP AS ip, MAX(vs_GeoCountry) AS geo_country, MAX(vs_GeoRegion) AS geo_region, COUNT(*) AS visits, COUNT(DISTINCT vs_PathKey) AS pages, MIN(vs_VisitedAt) AS first_visit, MAX(vs_VisitedAt) AS last_visit, AVG(vs_DurationMs) AS avg_ms, SUM(vs_StatusCode=404) AS not_found, SUM(vs_StatusCode BETWEEN 400 AND 499) AS error_4xx, SUM(vs_StatusCode BETWEEN 500 AND 599) AS error_5xx FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where . ' AND vs_IP <> \'\' GROUP BY vs_IP ORDER BY visits DESC, last_visit DESC LIMIT ' . $limit;
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
    $result = array();
    foreach ((array) $zbp->db->Query($sql) as $row) {
        $key = (string) $row['hour'];
        $result[$key] = array('hour' => $key, 'human_pv' => (int) $row['human_pv'], 'bot_pv' => (int) $row['bot_pv'], 'error_4xx' => (int) $row['error_4xx'], 'error_5xx' => (int) $row['error_5xx']);
    }

    // The current hour may not have been materialized yet. Read only that
    // bounded slice from the raw fact table and overlay it on the rollup.
    $now = time();
    $current = new DateTime('@' . $now); $current->setTimezone($zone); $current->setTime((int) $current->format('H'), 0, 0);
    $currentStart = max($range['start'], $current->getTimestamp());
    $currentEnd = min($range['end'], $current->getTimestamp() + 3600);
    if ($currentStart < $currentEnd) {
        $raw = (array) $zbp->db->Query('SELECT vs_IsBot,vs_StatusCode,vs_VisitedAt FROM ' . xz_visit_stats_v2_table() . ' WHERE vs_VisitedAt>=' . (int) $currentStart . ' AND vs_VisitedAt<' . (int) $currentEnd);
        $key = $current->format('Y-m-d H:00');
        $result[$key] = array('hour' => $key, 'human_pv' => 0, 'bot_pv' => 0, 'error_4xx' => 0, 'error_5xx' => 0);
        foreach ($raw as $row) {
            if ((int) $row['vs_IsBot'] === 1) $result[$key]['bot_pv']++;
            else $result[$key]['human_pv']++;
            if ((int) $row['vs_StatusCode'] >= 400 && (int) $row['vs_StatusCode'] < 500) $result[$key]['error_4xx']++;
            if ((int) $row['vs_StatusCode'] >= 500 && (int) $row['vs_StatusCode'] < 600) $result[$key]['error_5xx']++;
        }
    }
    ksort($result);
    return array_slice(array_values($result), -max(1, min(168, (int) $limit)));
}

function xz_visit_stats_v3_rum_summary($filters, $limit = 20)
{
    global $zbp;
    $table = xz_visit_stats_rum_table();
    if (!$zbp->db->ExistTable($table)) return array();
    $range = xz_visit_stats_v2_range($filters);
    $limit = max(1, min(100, (int) $limit));
    $where = 'rum_VisitedAt>=' . (int) $range['start'] . ' AND rum_VisitedAt<' . (int) $range['end'];
    $rows = (array) $zbp->db->Query('SELECT COUNT(*) AS samples, rum_PathKey AS path_key, MAX(rum_Path) AS path, AVG(rum_LCP) AS lcp, AVG(rum_INP) AS inp, AVG(rum_CLS) AS cls, AVG(rum_TTFB) AS ttfb, AVG(rum_FCP) AS fcp FROM `' . $table . '` WHERE ' . $where . ' GROUP BY rum_PathKey ORDER BY samples DESC LIMIT ' . $limit);
    foreach ($rows as &$row) {
        foreach (array('lcp', 'inp', 'cls', 'ttfb', 'fcp') as $metric) $row[$metric . '_p75'] = xz_visit_stats_v3_rum_percentile($table, $where, $metric, 0.75, isset($row['path_key']) ? $row['path_key'] : '');
    }
    unset($row);
    return $rows;
}

function xz_visit_stats_v3_rum_percentile($table, $where, $metric, $quantile, $pathKey = '')
{
    global $zbp;
    if (!in_array($metric, array('rum_LCP', 'rum_INP', 'rum_CLS', 'rum_TTFB', 'rum_FCP'), true)) return null;
    $scope = $where . ' AND ' . $metric . '>0';
    if ($pathKey !== '') $scope .= ' AND rum_PathKey=' . xz_visit_stats_v2_quote($pathKey);
    $countRows = (array) $zbp->db->Query('SELECT COUNT(*) AS n FROM `' . $table . '` WHERE ' . $scope);
    $count = !empty($countRows) ? (int) $countRows[0]['n'] : 0;
    if ($count <= 0) return null;
    $offset = max(0, (int) ceil($count * (float) $quantile) - 1);
    $rows = (array) $zbp->db->Query('SELECT ' . $metric . ' AS value FROM `' . $table . '` WHERE ' . $scope . ' ORDER BY ' . $metric . ' ASC LIMIT ' . $offset . ',1');
    return !empty($rows) ? (float) $rows[0]['value'] : null;
}

function xz_visit_stats_v3_daily_trend($filters)
{
    global $zbp;
    $range = xz_visit_stats_v2_range($filters);
    $zone = new DateTimeZone($range['timezone']);
    $day = new DateTime('@' . $range['start']); $day->setTimezone($zone); $day->setTime(0, 0, 0);
    $end = new DateTime('@' . $range['end']); $end->setTimezone($zone); $rows = array();
    $simple = $filters['visit_type'] === 'all' && $filters['status_group'] === 'all' && $filters['status_code'] === ''
        && $filters['source_type'] === 'all' && $filters['source_domain'] === '' && $filters['path_key'] === ''
        && $filters['bot_name'] === '' && $filters['ip'] === '' && $filters['url'] === '' && $filters['referer'] === '' && $filters['slow_ms'] === '';
    if ($simple) {
        $days = xz_visit_stats_v2_rollup_days($range);
        if (!empty($days)) {
            $quoted = array_map('xz_visit_stats_v2_quote', $days);
            $rollupTable = xz_visit_stats_upgrade_quote_table(xz_visit_stats_rollup_table());
            foreach ((array) $zbp->db->Query("SELECT rd_Day AS day,rd_VisitorPV AS human_pv,rd_VisitorUV AS human_uv,rd_VisitorIP AS human_ip,rd_BotPV AS bot_pv,rd_Error4xx AS error_4xx,rd_Error5xx AS error_5xx FROM {$rollupTable} WHERE rd_Dimension='site' AND rd_Key='all' AND rd_Day IN (" . implode(',', $quoted) . ')') as $row) {
                $rows[(string) $row['day']] = array('label' => (string) $row['day'], 'pv' => (int) $row['human_pv'] + (int) $row['bot_pv'], 'uv' => (int) $row['human_uv'], 'ip' => (int) $row['human_ip'], 'human_pv' => (int) $row['human_pv'], 'bot_pv' => (int) $row['bot_pv'], 'error_4xx' => (int) $row['error_4xx'], 'error_5xx' => (int) $row['error_5xx']);
            }
            $currentStart = max($range['start'], $range['today_start']);
            if ($currentStart < $range['end']) {
                $current = $filters; $current['range'] = 'custom'; $current['start'] = $day->format('Y-m-d H:i');
                $today = new DateTime('@' . $range['today_start']); $today->setTimezone($zone); $current['start'] = $today->format('Y-m-d H:i');
                $endCurrent = new DateTime('@' . $range['end']); $endCurrent->setTimezone($zone); $current['end'] = $endCurrent->format('Y-m-d H:i');
                $summary = xz_visit_stats_v2_exact_summary($current, xz_visit_stats_v2_range($current));
                $key = $today->format('Y-m-d');
                $rows[$key] = array('label' => $key, 'pv' => (int) $summary['total_pv'], 'uv' => (int) $summary['visitor_uv'], 'ip' => (int) $summary['visitor_ip'], 'human_pv' => (int) $summary['visitor_pv'], 'bot_pv' => (int) $summary['bot_pv'], 'error_4xx' => (int) $summary['error_4xx'], 'error_5xx' => (int) $summary['error_5xx']);
            }
        }
    }
    if (!empty($rows)) {
        while ($day < $end) { $key = $day->format('Y-m-d'); if (!isset($rows[$key])) $rows[$key] = array('label' => $key, 'pv' => 0, 'uv' => 0, 'ip' => 0, 'human_pv' => 0, 'bot_pv' => 0, 'error_4xx' => 0, 'error_5xx' => 0); $day->modify('+1 day'); }
        ksort($rows);
        return array_values($rows);
    }
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

function xz_visit_stats_v3_page_context_rows($filters, $limit = 100)
{
    global $zbp;
    $range = xz_visit_stats_v2_range($filters);
    $where = implode(' AND ', xz_visit_stats_v3_where($filters, $range));
    $limit = max(1, min(100, (int) $limit));
    $sql = 'SELECT vs_PathKey AS path_key, MAX(vs_Path) AS path, MAX(vs_PageTitle) AS page_title, MAX(vs_PostID) AS post_id, COUNT(*) AS visits, MAX(vs_VisitedAt) AS last_visit FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where . ' GROUP BY vs_PathKey ORDER BY visits DESC LIMIT ' . $limit;
    return (array) $zbp->db->Query($sql);
}

function xz_visit_stats_v3_entry_rows($filters, $limit = 50)
{
    global $zbp;
    $range = xz_visit_stats_v2_range($filters);
    $where = implode(' AND ', xz_visit_stats_v3_where($filters, $range));
    $table = xz_visit_stats_v2_table();
    $limit = max(1, min(100, (int) $limit));
    $sql = 'SELECT l.vs_PathKey AS path_key,MAX(l.vs_Path) AS path,MAX(l.vs_PageTitle) AS page_title,COUNT(*) AS entries FROM ' . $table . ' l INNER JOIN (SELECT vs_VisitorHash,MIN(vs_VisitedAt) AS first_at FROM ' . $table . ' WHERE ' . $where . " AND vs_IsBot=0 AND vs_VisitorHash<>'' GROUP BY vs_VisitorHash) e ON e.vs_VisitorHash=l.vs_VisitorHash AND e.first_at=l.vs_VisitedAt WHERE " . $where . ' AND l.vs_IsBot=0 GROUP BY l.vs_PathKey ORDER BY entries DESC LIMIT ' . $limit;
    return (array) $zbp->db->Query($sql);
}

function xz_visit_stats_v3_ai_crawler_rows($filters, $limit = 50)
{
    return xz_visit_stats_v3_dimension_rows('vs_AiCrawler', array_merge($filters, array('visit_type' => 'bot')), $limit);
}

function xz_visit_stats_v3_error_associations($filters, $limit = 30)
{
    global $zbp;
    $range = xz_visit_stats_v2_range($filters);
    $where = xz_visit_stats_v3_where($filters, $range);
    $where[] = 'vs_StatusCode>=400';
    $where = implode(' AND ', $where);
    $limit = max(1, min(100, (int) $limit));
    $sql = 'SELECT vs_PathKey AS path_key,MAX(vs_Path) AS path,MAX(vs_SourceDomain) AS source_domain,MAX(vs_BotName) AS bot_name,MAX(vs_AiCrawler) AS ai_crawler,SUM(vs_StatusCode=404) AS not_found,COUNT(*) AS errors FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where . ' GROUP BY vs_PathKey,vs_SourceDomain,vs_BotName,vs_AiCrawler ORDER BY errors DESC LIMIT ' . $limit;
    return (array) $zbp->db->Query($sql);
}

function xz_visit_stats_v3_duration_summary($filters)
{
    global $zbp;
    $range = xz_visit_stats_v2_range($filters);
    $where = implode(' AND ', xz_visit_stats_v3_where($filters, $range)) . ' AND vs_DurationMs>=0';
    $table = xz_visit_stats_v2_table();
    $row = (array) $zbp->db->Query('SELECT COUNT(*) AS samples,AVG(vs_DurationMs) AS average,SUM(vs_DurationMs>=1000) AS slow FROM ' . $table . ' WHERE ' . $where);
    $result = !empty($row) ? $row[0] : array('samples' => 0, 'average' => 0, 'slow' => 0);
    foreach (array(50,75,95) as $p) $result['p' . $p] = xz_visit_stats_v3_duration_percentile($where, $p / 100);
    return $result;
}

function xz_visit_stats_v3_duration_percentile($where, $quantile)
{
    global $zbp;
    $table = xz_visit_stats_v2_table();
    $rows = (array) $zbp->db->Query('SELECT COUNT(*) AS n FROM ' . $table . ' WHERE ' . $where);
    $count = empty($rows) ? 0 : (int) $rows[0]['n'];
    if ($count === 0) return null;
    $offset = max(0, (int) ceil($count * $quantile) - 1);
    $rows = (array) $zbp->db->Query('SELECT vs_DurationMs AS value FROM ' . $table . ' WHERE ' . $where . ' ORDER BY vs_DurationMs ASC LIMIT ' . $offset . ',1');
    return empty($rows) ? null : (float) $rows[0]['value'];
}

function xz_visit_stats_v3_rum_dimension_rows($filters, $field, $limit = 50)
{
    global $zbp;
    $allowed = array('rum_Language' => 'language', 'rum_Screen' => 'screen', 'rum_Viewport' => 'viewport');
    if (!isset($allowed[$field])) return array();
    $table = xz_visit_stats_rum_table();
    if (!$zbp->db->ExistTable($table)) return array();
    $range = xz_visit_stats_v2_range($filters);
    $limit = max(1, min(100, (int) $limit));
    $sql = 'SELECT ' . $field . ' AS name,COUNT(*) AS samples FROM `' . $table . '` WHERE rum_VisitedAt>=' . (int) $range['start'] . ' AND rum_VisitedAt<' . (int) $range['end'] . ' AND ' . $field . "<>'' GROUP BY " . $field . ' ORDER BY samples DESC,name ASC LIMIT ' . $limit;
    return (array) $zbp->db->Query($sql);
}
