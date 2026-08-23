<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_stats_filters($source = null)
{
    if ($source === null) {
        $source = $_GET;
    }

    $ranges = array('today', 'yesterday', '7d', '30d', 'custom');
    $range = xz_visit_stats_query_text(
        xz_visit_stats_query_value($source, 'range', 'today'),
        16
    );
    if (!in_array($range, $ranges, true)) {
        $range = 'today';
    }

    return array(
        'range' => $range,
        'start' => xz_visit_stats_query_text(
            xz_visit_stats_query_value($source, 'start', ''),
            16
        ),
        'end' => xz_visit_stats_query_text(
            xz_visit_stats_query_value($source, 'end', ''),
            16
        ),
    );
}

function xz_visit_stats_stats_range($filters, $now = null)
{
    if ($now === null) {
        $now = time();
    }

    $today = strtotime('today', $now);
    $tomorrow = strtotime('tomorrow', $now);
    $range = $filters['range'];
    $label = '今天';
    $compareLabel = '昨日';

    if ($range === 'yesterday') {
        $start = strtotime('-1 day', $today);
        $end = $today;
        $label = '昨天';
        $compareLabel = '前日';
    } elseif ($range === '7d') {
        $start = strtotime('-6 days', $today);
        $end = $tomorrow;
        $label = '最近 7 天';
        $compareLabel = '上一周期';
    } elseif ($range === '30d') {
        $start = strtotime('-29 days', $today);
        $end = $tomorrow;
        $label = '最近 30 天';
        $compareLabel = '上一周期';
    } elseif ($range === 'custom') {
        $start = xz_visit_stats_query_datetime($filters['start']);
        $end = xz_visit_stats_query_datetime($filters['end']);
        if ($start === null || $end === null || $end < $start) {
            $start = $today;
            $end = $tomorrow;
            $range = 'today';
            $label = '今天';
            $compareLabel = '昨日';
        } else {
            // datetime-local has minute precision; include the selected final minute.
            $end += 60;
            $label = '自定义范围';
            $compareLabel = '上一同长度时段';
        }
    } else {
        $start = $today;
        $end = $tomorrow;
    }

    return array(
        'range' => $range,
        'start' => (int) $start,
        'end' => (int) $end,
        'compare_start' => (int) ($start - ($end - $start)),
        'compare_end' => (int) $start,
        'label' => $label,
        'compare_label' => $compareLabel,
    );
}

function xz_visit_stats_stats_table()
{
    // Central data-source seam: Batch 8 can replace this with an aggregate table.
    return function_exists('xz_visit_stats_quoted_table')
        ? xz_visit_stats_quoted_table()
        : $GLOBALS['table']['xz_visit_stats_log'];
}

function xz_visit_stats_stats_row($sql)
{
    global $zbp;

    $rows = (array) $zbp->db->Query($sql);
    $row = reset($rows);

    return is_array($row) ? $row : array();
}

function xz_visit_stats_stats_number($row, $key, $float = false)
{
    if (!isset($row[$key]) || $row[$key] === null) {
        return $float ? 0.0 : 0;
    }

    return $float ? (float) $row[$key] : (int) $row[$key];
}

function xz_visit_stats_stats_summary($range)
{
    $current = 'vs_VisitedAt >= ' . (int) $range['start']
        . ' AND vs_VisitedAt < ' . (int) $range['end'];
    $previous = 'vs_VisitedAt >= ' . (int) $range['compare_start']
        . ' AND vs_VisitedAt < ' . (int) $range['compare_end'];
    $conditions = array(
        'bot' => 'vs_IsBot = 1',
        'status_2xx' => 'vs_StatusCode >= 200 AND vs_StatusCode < 300',
        'status_3xx' => 'vs_StatusCode >= 300 AND vs_StatusCode < 400',
        'status_4xx' => 'vs_StatusCode >= 400 AND vs_StatusCode < 500',
        'status_5xx' => 'vs_StatusCode >= 500 AND vs_StatusCode < 600',
        'not_found' => 'vs_StatusCode = 404',
    );
    $keys = array('pv', 'uv', 'ip', 'bot', 'status_2xx', 'status_3xx', 'status_4xx', 'status_5xx', 'not_found', 'avg_ms');
    $select = array(
        'SUM((' . $current . ')) AS current_pv',
        'COUNT(DISTINCT CASE WHEN ' . $current . ' THEN vs_VisitorHash END) AS current_uv',
        'COUNT(DISTINCT CASE WHEN ' . $current . ' THEN vs_IP END) AS current_ip',
        'AVG(CASE WHEN ' . $current . ' THEN vs_DurationMs END) AS current_avg_ms',
        'SUM((' . $previous . ')) AS previous_pv',
        'COUNT(DISTINCT CASE WHEN ' . $previous . ' THEN vs_VisitorHash END) AS previous_uv',
        'COUNT(DISTINCT CASE WHEN ' . $previous . ' THEN vs_IP END) AS previous_ip',
        'AVG(CASE WHEN ' . $previous . ' THEN vs_DurationMs END) AS previous_avg_ms',
    );
    foreach ($conditions as $key => $condition) {
        $select[] = 'SUM((' . $current . ') AND (' . $condition . ')) AS current_' . $key;
        $select[] = 'SUM((' . $previous . ') AND (' . $condition . ')) AS previous_' . $key;
    }

    // One bounded time-range scan returns current and comparison cards together.
    $sql = 'SELECT ' . implode(', ', $select) . ' FROM ' . xz_visit_stats_stats_table()
        . ' WHERE vs_VisitedAt >= ' . (int) $range['compare_start']
        . ' AND vs_VisitedAt < ' . (int) $range['end'];
    $row = xz_visit_stats_stats_row($sql);
    $current = array();
    $previous = array();
    foreach ($keys as $key) {
        $isFloat = $key === 'avg_ms';
        $current[$key] = xz_visit_stats_stats_number($row, 'current_' . $key, $isFloat);
        $previous[$key] = xz_visit_stats_stats_number($row, 'previous_' . $key, $isFloat);
    }

    return array('current' => $current, 'previous' => $previous);
}

function xz_visit_stats_stats_unit($range)
{
    if ($range['range'] === 'today' || $range['range'] === 'yesterday') {
        return 'hour';
    }
    if ($range['range'] === '7d' || $range['range'] === '30d') {
        return 'day';
    }

    return ($range['end'] - $range['start']) <= 2 * 86400 ? 'hour' : 'day';
}

function xz_visit_stats_stats_trend($range)
{
    global $zbp;

    $unit = xz_visit_stats_stats_unit($range);
    $format = $unit === 'hour' ? '%Y-%m-%d %H:00' : '%Y-%m-%d';
    $sql = "SELECT DATE_FORMAT(FROM_UNIXTIME(vs_VisitedAt), '" . $format . "') AS bucket,"
        . ' COUNT(*) AS pv, COUNT(DISTINCT vs_VisitorHash) AS uv, COUNT(DISTINCT vs_IP) AS ip'
        . ' FROM ' . xz_visit_stats_stats_table()
        . ' WHERE vs_VisitedAt >= ' . (int) $range['start']
        . ' AND vs_VisitedAt < ' . (int) $range['end']
        . ' GROUP BY bucket ORDER BY bucket ASC';
    $rows = (array) $zbp->db->Query($sql);
    $values = array();
    foreach ($rows as $row) {
        $values[(string) $row['bucket']] = array(
            'pv' => (int) $row['pv'],
            'uv' => (int) $row['uv'],
            'ip' => (int) $row['ip'],
        );
    }

    $step = $unit === 'hour' ? 3600 : 86400;
    $cursor = $unit === 'hour'
        ? strtotime(date('Y-m-d H:00:00', $range['start']))
        : strtotime(date('Y-m-d 00:00:00', $range['start']));
    $items = array();
    while ($cursor < $range['end']) {
        $key = date($unit === 'hour' ? 'Y-m-d H:00' : 'Y-m-d', $cursor);
        $item = isset($values[$key]) ? $values[$key] : array('pv' => 0, 'uv' => 0, 'ip' => 0);
        $item['label'] = date($unit === 'hour' ? 'H:00' : 'm-d', $cursor);
        $items[] = $item;
        $cursor += $step;
    }

    return array('unit' => $unit, 'items' => $items);
}

function xz_visit_stats_stats_hours($range)
{
    global $zbp;

    $sql = 'SELECT HOUR(FROM_UNIXTIME(vs_VisitedAt)) AS hour, COUNT(*) AS pv,'
        . ' SUM(vs_IsBot = 1) AS bot FROM ' . xz_visit_stats_stats_table()
        . ' WHERE vs_VisitedAt >= ' . (int) $range['start']
        . ' AND vs_VisitedAt < ' . (int) $range['end']
        . ' GROUP BY hour ORDER BY hour ASC';
    $rows = (array) $zbp->db->Query($sql);
    $values = array();
    foreach ($rows as $row) {
        $values[(int) $row['hour']] = array('pv' => (int) $row['pv'], 'bot' => (int) $row['bot']);
    }
    $items = array();
    for ($hour = 0; $hour < 24; $hour++) {
        $item = isset($values[$hour]) ? $values[$hour] : array('pv' => 0, 'bot' => 0);
        $item['label'] = sprintf('%02d', $hour);
        $items[] = $item;
    }

    return $items;
}

function xz_visit_stats_stats_delta($current, $previous)
{
    $delta = $current - $previous;

    return array(
        'value' => $delta,
        'percent' => $previous == 0 ? null : ($delta / $previous) * 100,
    );
}

function xz_visit_stats_stats_build($filters)
{
    $range = xz_visit_stats_stats_range($filters);
    $summary = xz_visit_stats_stats_summary($range);

    return array(
        'range' => $range,
        'summary' => $summary,
        'trend' => xz_visit_stats_stats_trend($range),
        'hours' => xz_visit_stats_stats_hours($range),
        'types' => array(
            array('label' => '普通访客', 'value' => max(0, $summary['current']['pv'] - $summary['current']['bot'])),
            array('label' => '蜘蛛', 'value' => $summary['current']['bot']),
        ),
        'statuses' => array(
            array('label' => '2xx', 'value' => $summary['current']['status_2xx']),
            array('label' => '3xx', 'value' => $summary['current']['status_3xx']),
            array('label' => '4xx', 'value' => $summary['current']['status_4xx'], 'not_found' => $summary['current']['not_found']),
            array('label' => '5xx', 'value' => $summary['current']['status_5xx']),
        ),
    );
}
