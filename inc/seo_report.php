<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_seo_report_filters($source = null)
{
    $filters = xz_visit_stats_stats_filters($source);
    $allowedRanges = array('today', '7d', '30d');
    if (!in_array($filters['range'], $allowedRanges, true)) {
        $filters['range'] = '7d';
        $filters['start'] = '';
        $filters['end'] = '';
    }
    if ($source === null) {
        $source = $_GET;
    }
    $pageRaw = trim((string) xz_visit_stats_query_value($source, 'page', '1'));
    $page = preg_match('/^[0-9]+$/', $pageRaw) === 1 ? max(1, (int) $pageRaw) : 1;
    $sizeRaw = trim((string) xz_visit_stats_query_value($source, 'page_size', '20'));
    $pageSize = preg_match('/^[0-9]+$/', $sizeRaw) === 1 ? (int) $sizeRaw : 20;
    if (!in_array($pageSize, array(20, 50, 100), true)) {
        $pageSize = 20;
    }
    $filters['page'] = $page;
    $filters['page_size'] = $pageSize;

    return $filters;
}

function xz_visit_stats_seo_report_engines()
{
    return array(
        'Googlebot' => 'Googlebot',
        'Baiduspider' => 'Baiduspider',
        'bingbot' => 'bingbot',
        'Sogou' => 'Sogou',
        '360Spider' => '360Spider',
        'Bytespider' => 'Bytespider',
        'YandexBot' => 'YandexBot',
        'Other Bot' => '其他蜘蛛',
    );
}

function xz_visit_stats_seo_report_known_engines()
{
    $engines = xz_visit_stats_seo_report_engines();
    unset($engines['Other Bot']);

    return array_keys($engines);
}

function xz_visit_stats_seo_report_where($range)
{
    return 'vs_IsBot = 1 AND vs_VisitedAt >= ' . (int) $range['start']
        . ' AND vs_VisitedAt < ' . (int) $range['end'];
}

function xz_visit_stats_seo_report_summary($range)
{
    $sql = 'SELECT COUNT(*) AS visits, COUNT(DISTINCT vs_IP) AS ips, COUNT(DISTINCT vs_Path) AS paths,'
        . ' SUM(vs_StatusCode >= 200 AND vs_StatusCode < 300) AS status_2xx,'
        . ' SUM(vs_StatusCode >= 400 AND vs_StatusCode < 500) AS status_4xx,'
        . ' SUM(vs_StatusCode = 404) AS not_found, SUM(vs_StatusCode >= 500 AND vs_StatusCode < 600) AS status_5xx,'
        . ' AVG(vs_DurationMs) AS avg_ms FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_seo_report_where($range);
    $row = xz_visit_stats_stats_row($sql);
    $summary = array();
    foreach (array('visits', 'ips', 'paths', 'status_2xx', 'status_4xx', 'not_found', 'status_5xx') as $key) {
        $summary[$key] = xz_visit_stats_stats_number($row, $key);
    }
    $summary['avg_ms'] = xz_visit_stats_stats_number($row, 'avg_ms', true);
    $summary['success_rate'] = $summary['visits'] > 0
        ? ($summary['status_2xx'] / $summary['visits']) * 100
        : 0.0;

    return $summary;
}

function xz_visit_stats_seo_report_engine_stats($range)
{
    global $zbp;

    $engines = xz_visit_stats_seo_report_engines();
    $quoted = array();
    foreach (xz_visit_stats_seo_report_known_engines() as $engine) {
        $quoted[] = "'" . $engine . "'";
    }
    $nameSql = 'CASE WHEN vs_BotName IN (' . implode(', ', $quoted)
        . ") THEN vs_BotName ELSE 'Other Bot' END";
    $sql = 'SELECT ' . $nameSql . ' AS name, COUNT(*) AS visits, COUNT(DISTINCT vs_IP) AS ips,'
        . ' SUM(vs_StatusCode >= 200 AND vs_StatusCode < 300) AS status_2xx,'
        . ' SUM(vs_StatusCode = 404) AS not_found FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_seo_report_where($range)
        . ' GROUP BY name';
    $rows = (array) $zbp->db->Query($sql);
    $mapped = array();
    foreach ($rows as $row) {
        $mapped[(string) $row['name']] = $row;
    }
    $items = array();
    $total = 0;
    foreach ($mapped as $row) {
        $total += xz_visit_stats_stats_number($row, 'visits');
    }
    foreach ($engines as $key => $label) {
        $row = isset($mapped[$key]) ? $mapped[$key] : array();
        $visits = xz_visit_stats_stats_number($row, 'visits');
        $items[] = array(
            'key' => $key,
            'name' => $label,
            'visits' => $visits,
            'ips' => xz_visit_stats_stats_number($row, 'ips'),
            'status_2xx' => xz_visit_stats_stats_number($row, 'status_2xx'),
            'not_found' => xz_visit_stats_stats_number($row, 'not_found'),
            'success_rate' => $visits > 0
                ? (xz_visit_stats_stats_number($row, 'status_2xx') / $visits) * 100
                : 0.0,
            'percent' => $total > 0 ? ($visits / $total) * 100 : 0.0,
        );
    }

    return $items;
}

function xz_visit_stats_seo_report_url_count($range)
{
    $row = xz_visit_stats_stats_row(
        'SELECT COUNT(DISTINCT vs_Path) AS num FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_seo_report_where($range)
    );

    return xz_visit_stats_stats_number($row, 'num');
}

function xz_visit_stats_seo_report_urls($range, $page, $pageSize)
{
    global $zbp;

    $offset = max(0, ($page - 1) * $pageSize);
    $sql = 'SELECT vs_Path AS path, COUNT(*) AS visits, MAX(vs_VisitedAt) AS last_visit,'
        . ' SUM(vs_StatusCode >= 200 AND vs_StatusCode < 300) AS status_2xx,'
        . ' SUM(vs_StatusCode >= 300 AND vs_StatusCode < 400) AS status_3xx,'
        . ' SUM(vs_StatusCode >= 400 AND vs_StatusCode < 500) AS status_4xx,'
        . ' SUM(vs_StatusCode >= 500 AND vs_StatusCode < 600) AS status_5xx'
        . ' FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_seo_report_where($range)
        . ' GROUP BY vs_Path ORDER BY visits DESC, last_visit DESC LIMIT ' . (int) $offset . ', ' . (int) $pageSize;
    $rows = (array) $zbp->db->Query($sql);
    foreach ($rows as &$row) {
        foreach (array('visits', 'last_visit', 'status_2xx', 'status_3xx', 'status_4xx', 'status_5xx') as $key) {
            $row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
        }
        $row['path'] = isset($row['path']) ? (string) $row['path'] : '';
    }
    unset($row);

    return $rows;
}

function xz_visit_stats_seo_report_rank_urls($range, $type, $limit = 10)
{
    global $zbp;

    $limit = max(1, min(20, (int) $limit));
    if ($type === 'success') {
        $having = 'status_2xx > 0';
        $order = 'status_2xx DESC, visits DESC, last_visit DESC';
    } elseif ($type === 'not_found') {
        $having = 'not_found > 0';
        $order = 'not_found DESC, visits DESC, last_visit DESC';
    } else {
        return array();
    }

    $sql = 'SELECT vs_Path AS path, COUNT(*) AS visits, MAX(vs_VisitedAt) AS last_visit,'
        . ' SUM(vs_StatusCode >= 200 AND vs_StatusCode < 300) AS status_2xx,'
        . ' SUM(vs_StatusCode >= 300 AND vs_StatusCode < 400) AS status_3xx,'
        . ' SUM(vs_StatusCode >= 400 AND vs_StatusCode < 500) AS status_4xx,'
        . ' SUM(vs_StatusCode >= 500 AND vs_StatusCode < 600) AS status_5xx,'
        . ' SUM(vs_StatusCode = 404) AS not_found'
        . ' FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_seo_report_where($range)
        . ' GROUP BY vs_Path HAVING ' . $having
        . ' ORDER BY ' . $order . ' LIMIT ' . $limit;
    $rows = (array) $zbp->db->Query($sql);
    foreach ($rows as &$row) {
        foreach (array('visits', 'last_visit', 'status_2xx', 'status_3xx', 'status_4xx', 'status_5xx', 'not_found') as $key) {
            $row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
        }
        $row['path'] = isset($row['path']) ? (string) $row['path'] : '';
    }
    unset($row);

    return $rows;
}

function xz_visit_stats_seo_report_trend($range)
{
    global $zbp;

    $unit = xz_visit_stats_stats_unit($range);
    $format = $unit === 'hour' ? '%Y-%m-%d %H:00' : '%Y-%m-%d';
    $sql = "SELECT DATE_FORMAT(FROM_UNIXTIME(vs_VisitedAt), '" . $format . "') AS bucket, COUNT(*) AS visits"
        . ' FROM ' . xz_visit_stats_stats_table() . ' WHERE ' . xz_visit_stats_seo_report_where($range)
        . ' GROUP BY bucket ORDER BY bucket ASC';
    $rows = (array) $zbp->db->Query($sql);
    $values = array();
    foreach ($rows as $row) {
        $values[(string) $row['bucket']] = (int) $row['visits'];
    }
    $step = $unit === 'hour' ? 3600 : 86400;
    $cursor = $unit === 'hour'
        ? strtotime(date('Y-m-d H:00:00', $range['start']))
        : strtotime(date('Y-m-d 00:00:00', $range['start']));
    $items = array();
    while ($cursor < $range['end']) {
        $key = date($unit === 'hour' ? 'Y-m-d H:00' : 'Y-m-d', $cursor);
        $items[] = array(
            'label' => date($unit === 'hour' ? 'H:00' : 'm-d', $cursor),
            'visits' => isset($values[$key]) ? $values[$key] : 0,
        );
        $cursor += $step;
    }

    return array('unit' => $unit, 'items' => $items);
}

function xz_visit_stats_seo_report_hours($range)
{
    global $zbp;

    $sql = 'SELECT HOUR(FROM_UNIXTIME(vs_VisitedAt)) AS hour, COUNT(*) AS visits'
        . ' FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_seo_report_where($range)
        . ' GROUP BY hour ORDER BY hour ASC';
    $rows = (array) $zbp->db->Query($sql);
    $values = array();
    foreach ($rows as $row) {
        $values[(int) $row['hour']] = (int) $row['visits'];
    }
    $items = array();
    for ($hour = 0; $hour < 24; $hour++) {
        $items[] = array(
            'label' => sprintf('%02d', $hour),
            'visits' => isset($values[$hour]) ? $values[$hour] : 0,
        );
    }

    return $items;
}

function xz_visit_stats_seo_report_thresholds()
{
    return array('minimum_sample' => 5, 'high_404_ratio' => 10.0, 'high_avg_ms' => 1000.0);
}

function xz_visit_stats_seo_report_anomalies($summary)
{
    $thresholds = xz_visit_stats_seo_report_thresholds();
    $items = array();
    if ($summary['visits'] === 0) {
        return array();
    }
    $notFoundRate = ($summary['not_found'] / $summary['visits']) * 100;
    if ($summary['visits'] >= $thresholds['minimum_sample'] && $notFoundRate >= $thresholds['high_404_ratio']) {
        $items[] = array('item' => '蜘蛛 404 比例较高', 'value' => number_format($notFoundRate, 1) . '%', 'threshold' => '≥ ' . $thresholds['high_404_ratio'] . '%');
    }
    $failed = $summary['status_4xx'] + $summary['status_5xx'];
    if ($failed > 0) {
        $items[] = array(
            'item' => '蜘蛛访问失败（4xx / 5xx）',
            'value' => $failed . ' 次（4xx ' . $summary['status_4xx'] . '，5xx ' . $summary['status_5xx'] . '）',
            'threshold' => '> 0 次',
        );
    }
    if ($summary['visits'] >= $thresholds['minimum_sample'] && $summary['avg_ms'] >= $thresholds['high_avg_ms']) {
        $items[] = array('item' => '响应时间异常', 'value' => number_format($summary['avg_ms'], 1) . ' ms', 'threshold' => '≥ ' . $thresholds['high_avg_ms'] . ' ms');
    }

    return $items;
}

function xz_visit_stats_seo_report_build($filters)
{
    $range = xz_visit_stats_stats_range($filters);
    $summary = xz_visit_stats_seo_report_summary($range);
    $count = xz_visit_stats_seo_report_url_count($range);
    $pageAll = max(1, (int) ceil($count / $filters['page_size']));
    $page = min($filters['page'], $pageAll);

    return array(
        'range' => $range,
        'summary' => $summary,
        'engines' => xz_visit_stats_seo_report_engine_stats($range),
        'urls' => xz_visit_stats_seo_report_urls($range, $page, $filters['page_size']),
        'success_urls' => xz_visit_stats_seo_report_rank_urls($range, 'success'),
        'not_found_urls' => xz_visit_stats_seo_report_rank_urls($range, 'not_found'),
        'url_count' => $count,
        'page' => $page,
        'page_all' => $pageAll,
        'trend' => xz_visit_stats_seo_report_trend($range),
        'hours' => xz_visit_stats_seo_report_hours($range),
        'anomalies' => xz_visit_stats_seo_report_anomalies($summary),
    );
}
