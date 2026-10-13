<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

/**
 * T4 report composition stays deliberately thin: v3 owns traffic rollups and
 * v4 owns lifecycle metrics.  In particular, v3 vs_DurationMs never crosses
 * this boundary as visitor dwell.
 */
function xz_visit_stats_v4_report_filters_for_v3(array $filters): array
{
    $v3 = $filters;
    $v3['range'] = isset($filters['range']) ? $filters['range'] : 'today';
    return $v3;
}

function xz_visit_stats_v4_report_new_returning(array $filters, array $lifecycleAvailability): array
{
    $counts = function_exists('xz_visit_stats_v4_visitor_classification')
        ? xz_visit_stats_v4_visitor_classification($filters)
        : array('visitor_count' => 0, 'new_visitors' => 0, 'returning_visitors' => 0);
    $availability = array(
        'status' => $counts['visitor_count'] > 0 ? 'partial' : 'unavailable',
        'reason' => $counts['visitor_count'] > 0 ? 'pre_v4_history_not_backfilled' : 'no_v4_session_history',
    );
    $counts['availability'] = $availability;
    $counts['lifecycle_availability'] = $lifecycleAvailability;
    return $counts;
}

function xz_visit_stats_v4_overview(array $filters): array
{
    $filters = xz_visit_stats_v4_report_filters($filters);
    $v3Filters = xz_visit_stats_v4_report_filters_for_v3($filters);
    $traffic = xz_visit_stats_v2_summary($v3Filters);
    $sessions = xz_visit_stats_v4_session_summary($filters);

    $comparison = function_exists('xz_visit_stats_v3_comparison')
        ? xz_visit_stats_v3_comparison($v3Filters)
        : array('status' => 'unavailable');

    return array(
        'filters' => $filters,
        'kpis' => array(
            'pv' => (int) $traffic['visitor_pv'],
            'uv' => (int) $traffic['visitor_uv'],
            'ip' => (int) $traffic['visitor_ip'],
            'sessions' => (int) $sessions['session_count'],
            'bounce_rate' => $sessions['bounce_rate'],
            'average_visitor_dwell_ms' => $sessions['average_dwell_ms'],
        ),
        'availability' => $sessions['availability'],
        'traffic' => $traffic,
        'sessions' => $sessions,
        'new_returning' => xz_visit_stats_v4_report_new_returning($filters, $sessions['availability']),
        'sources' => xz_visit_stats_v2_source_summary($v3Filters, 10),
        'popular_pages' => xz_visit_stats_v2_path_summary($v3Filters, 10),
        'entry_pages' => function_exists('xz_visit_stats_v3_entry_rows') ? xz_visit_stats_v3_entry_rows($v3Filters, 10) : array(),
        'geo' => function_exists('xz_visit_stats_v3_dimension_rows') ? xz_visit_stats_v3_dimension_rows('vs_GeoCountry', $v3Filters, 10) : array(),
        'device' => function_exists('xz_visit_stats_v3_dimension_rows') ? xz_visit_stats_v3_dimension_rows('vs_Device', $v3Filters, 10) : array(),
        'browser' => function_exists('xz_visit_stats_v3_dimension_rows') ? xz_visit_stats_v3_dimension_rows('vs_Browser', $v3Filters, 10) : array(),
        'os' => function_exists('xz_visit_stats_v3_dimension_rows') ? xz_visit_stats_v3_dimension_rows('vs_Os', $v3Filters, 10) : array(),
        'comparison' => $comparison,
    );
}

function xz_visit_stats_v4_trend_grain($grain): string
{
    return in_array($grain, array('hour', 'day', 'week', 'month'), true) ? $grain : 'day';
}

function xz_visit_stats_v4_trend_bucket($label, string $grain): string
{
    if ($grain === 'hour') return (string) $label;
    $timestamp = strtotime((string) $label . ' 00:00:00');
    if ($timestamp === false) return (string) $label;
    if ($grain === 'week') return date('o-\\WW', $timestamp);
    if ($grain === 'month') return date('Y-m', $timestamp);
    return date('Y-m-d', $timestamp);
}

function xz_visit_stats_v4_trend_traffic(array $filters, string $grain): array
{
    global $zbp;
    $formats = array('hour' => '%Y-%m-%d %H:00', 'day' => '%Y-%m-%d', 'week' => '%x-W%v', 'month' => '%Y-%m');
    $range = xz_visit_stats_v2_range($filters);
    $where = implode(' AND ', xz_visit_stats_v3_where($filters, $range));
    $bucketExpression = 'DATE_FORMAT(FROM_UNIXTIME(vs_VisitedAt),' . xz_visit_stats_v2_quote($formats[$grain]) . ')';
    $sql = 'SELECT ' . $bucketExpression . ' AS trend_bucket,COUNT(*) AS pv,'
        . ' COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_VisitorHash END) AS uv,'
        . ' COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_IP END) AS ip,'
        . ' SUM(vs_IsBot=0) AS human_pv,SUM(vs_IsBot=1) AS bot_pv'
        . ' FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where
        . ' GROUP BY trend_bucket ORDER BY trend_bucket ASC LIMIT ' . ($grain === 'hour' ? 744 : 366);
    $rows = (array) $zbp->db->Query($sql);
    $result = array();
    foreach ($rows as $row) {
        $bucket = (string) (isset($row['trend_bucket']) ? $row['trend_bucket'] : '');
        if ($bucket === '') continue;
        $result[$bucket] = array(
            'bucket' => $bucket,
            'pv' => (int) (isset($row['pv']) ? $row['pv'] : 0),
            'uv' => (int) (isset($row['uv']) ? $row['uv'] : 0),
            'ip' => (int) (isset($row['ip']) ? $row['ip'] : 0),
            'session_count' => 0,
            'unique_visitors' => 0,
            'bounce_sessions' => 0,
            'average_visitor_dwell_ms' => null,
        );
    }
    return $result;
}

function xz_visit_stats_v4_trend_report(array $filters, string $grain): array
{
    $filters = xz_visit_stats_v4_report_filters($filters);
    $grain = xz_visit_stats_v4_trend_grain($grain);
    $traffic = xz_visit_stats_v4_trend_traffic(xz_visit_stats_v4_report_filters_for_v3($filters), $grain);
    $sessions = xz_visit_stats_v4_session_trend($filters, $grain);
    foreach ($sessions as $session) {
        $bucket = (string) $session['bucket'];
        if (!isset($traffic[$bucket])) {
            $traffic[$bucket] = array('bucket' => $bucket, 'pv' => 0, 'uv' => null, 'ip' => null, 'session_count' => 0, 'unique_visitors' => 0, 'bounce_sessions' => 0, 'average_visitor_dwell_ms' => null);
        }
        $traffic[$bucket]['session_count'] = (int) $session['session_count'];
        $traffic[$bucket]['unique_visitors'] = (int) $session['unique_visitors'];
        $traffic[$bucket]['bounce_sessions'] = (int) $session['bounce_sessions'];
        $traffic[$bucket]['average_visitor_dwell_ms'] = $session['average_dwell_ms'];
    }
    ksort($traffic, SORT_STRING);
    $summary = xz_visit_stats_v4_session_summary($filters);
    return array('filters' => $filters, 'grain' => $grain, 'rows' => array_values($traffic), 'availability' => $summary['availability']);
}
