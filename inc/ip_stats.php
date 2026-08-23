<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_ip_filters($source = null)
{
    $filters = xz_visit_stats_stats_filters($source);
    if ($source === null) {
        $source = $_GET;
    }

    $ip = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'ip', ''), 45);
    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) === false) {
        $ip = '';
    }
    $pageRaw = trim((string) xz_visit_stats_query_value($source, 'page', '1'));
    $page = preg_match('/^[0-9]+$/', $pageRaw) === 1 ? max(1, (int) $pageRaw) : 1;
    $sizeRaw = trim((string) xz_visit_stats_query_value($source, 'page_size', '20'));
    $pageSize = preg_match('/^[0-9]+$/', $sizeRaw) === 1 ? (int) $sizeRaw : 20;
    if (!in_array($pageSize, array(20, 50, 100), true)) {
        $pageSize = 20;
    }

    $filters['ip'] = $ip;
    $filters['page'] = $page;
    $filters['page_size'] = $pageSize;

    return $filters;
}

function xz_visit_stats_ip_thresholds()
{
    return array(
        'high_frequency_per_minute' => 10,
        'minimum_404_requests' => 5,
        'high_404_ratio' => 10.0,
        'scan_404_paths' => 5,
        'anomaly_limit' => 20,
        'detail_limit' => 50,
    );
}

function xz_visit_stats_ip_where($range, $ip = '')
{
    $where = 'vs_IP <> \'\' AND vs_VisitedAt >= ' . (int) $range['start']
        . ' AND vs_VisitedAt < ' . (int) $range['end'];
    if ($ip !== '') {
        // The input has passed FILTER_VALIDATE_IP, covering IPv4 and IPv6 only.
        $where .= " AND vs_IP = '" . str_replace("'", "''", $ip) . "'";
    }

    return $where;
}

function xz_visit_stats_ip_summary($range)
{
    $where = xz_visit_stats_ip_where($range);
    $thresholds = xz_visit_stats_ip_thresholds();
    $sql = 'SELECT COUNT(DISTINCT vs_IP) AS ips, COUNT(*) AS visits, AVG(vs_DurationMs) AS avg_ms'
        . ' FROM ' . xz_visit_stats_stats_table() . ' WHERE ' . $where;
    $summary = xz_visit_stats_stats_row($sql);
    $ips = xz_visit_stats_stats_number($summary, 'ips');
    $visits = xz_visit_stats_stats_number($summary, 'visits');

    $groups = xz_visit_stats_ip_group_sql($range);
    $abnormalSql = 'SELECT SUM(max_per_minute >= ' . (int) $thresholds['high_frequency_per_minute'] . ') AS high_frequency_ips,'
        . ' SUM(visits >= ' . (int) $thresholds['minimum_404_requests']
        . ' AND not_found * 100.0 / visits >= ' . (float) $thresholds['high_404_ratio'] . ') AS error_ips'
        . ' FROM (' . $groups . ') AS ip_groups';
    $abnormal = xz_visit_stats_stats_row($abnormalSql);

    return array(
        'ips' => $ips,
        'visits' => $visits,
        'avg_per_ip' => $ips > 0 ? $visits / $ips : 0.0,
        'high_frequency_ips' => xz_visit_stats_stats_number($abnormal, 'high_frequency_ips'),
        'error_ips' => xz_visit_stats_stats_number($abnormal, 'error_ips'),
        'avg_ms' => xz_visit_stats_stats_number($summary, 'avg_ms', true),
    );
}

function xz_visit_stats_ip_group_sql($range)
{
    $where = xz_visit_stats_ip_where($range);
    $minuteWhere = xz_visit_stats_ip_where($range);
    $minuteSql = 'SELECT vs_IP, FLOOR(vs_VisitedAt / 60) AS minute_bucket, COUNT(*) AS minute_visits'
        . ' FROM ' . xz_visit_stats_stats_table() . ' WHERE ' . $minuteWhere
        . ' GROUP BY vs_IP, minute_bucket';
    $frequencySql = 'SELECT vs_IP, MAX(minute_visits) AS max_per_minute FROM (' . $minuteSql
        . ') AS minute_groups GROUP BY vs_IP';
    $scannerPattern = '(sqlmap|nikto|masscan|nmap|zgrab|acunetix|dirbuster|gobuster|ffuf|wpscan)';

    return 'SELECT logs.vs_IP AS ip, COUNT(*) AS visits, COUNT(DISTINCT logs.vs_Path) AS paths,'
        . ' MIN(logs.vs_VisitedAt) AS first_visit, MAX(logs.vs_VisitedAt) AS last_visit,'
        . ' SUM(logs.vs_StatusCode >= 200 AND logs.vs_StatusCode < 300) AS status_2xx,'
        . ' SUM(logs.vs_StatusCode >= 300 AND logs.vs_StatusCode < 400) AS status_3xx,'
        . ' SUM(logs.vs_StatusCode >= 400 AND logs.vs_StatusCode < 500) AS status_4xx,'
        . ' SUM(logs.vs_StatusCode >= 500 AND logs.vs_StatusCode < 600) AS status_5xx,'
        . ' SUM(logs.vs_StatusCode = 404) AS not_found,'
        . ' COUNT(DISTINCT CASE WHEN logs.vs_StatusCode = 404 THEN logs.vs_Path END) AS not_found_paths,'
        . " SUM(logs.vs_UserAgent REGEXP '" . $scannerPattern . "') AS suspicious_ua,"
        . ' AVG(logs.vs_DurationMs) AS avg_ms, COALESCE(freq.max_per_minute, 0) AS max_per_minute'
        . ' FROM ' . xz_visit_stats_stats_table() . ' AS logs'
        . ' LEFT JOIN (' . $frequencySql . ') AS freq ON freq.vs_IP = logs.vs_IP'
        . ' WHERE ' . str_replace('vs_', 'logs.vs_', $where)
        . ' GROUP BY logs.vs_IP';
}

function xz_visit_stats_ip_count($range)
{
    $sql = 'SELECT COUNT(DISTINCT vs_IP) AS num FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_ip_where($range);
    $row = xz_visit_stats_stats_row($sql);

    return xz_visit_stats_stats_number($row, 'num');
}

function xz_visit_stats_ip_rows($range, $page, $pageSize)
{
    global $zbp;

    $offset = max(0, ($page - 1) * $pageSize);
    $sql = 'SELECT * FROM (' . xz_visit_stats_ip_group_sql($range) . ') AS ip_groups'
        . ' ORDER BY visits DESC, last_visit DESC LIMIT ' . (int) $offset . ', ' . (int) $pageSize;
    $rows = (array) $zbp->db->Query($sql);

    return xz_visit_stats_ip_normalize_rows($rows);
}

function xz_visit_stats_ip_normalize_rows($rows)
{
    foreach ($rows as &$row) {
        foreach (array('visits', 'paths', 'first_visit', 'last_visit', 'status_2xx', 'status_3xx', 'status_4xx', 'status_5xx', 'not_found', 'not_found_paths', 'suspicious_ua', 'max_per_minute') as $key) {
            $row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
        }
        $row['avg_ms'] = isset($row['avg_ms']) ? (float) $row['avg_ms'] : 0.0;
        $row['ip'] = isset($row['ip']) ? (string) $row['ip'] : '';
    }
    unset($row);

    return $rows;
}

function xz_visit_stats_ip_anomalies($range)
{
    global $zbp;

    $thresholds = xz_visit_stats_ip_thresholds();
    $sql = 'SELECT * FROM (' . xz_visit_stats_ip_group_sql($range) . ') AS ip_groups WHERE '
        . 'max_per_minute >= ' . (int) $thresholds['high_frequency_per_minute']
        . ' OR (visits >= ' . (int) $thresholds['minimum_404_requests']
        . ' AND not_found * 100.0 / visits >= ' . (float) $thresholds['high_404_ratio'] . ')'
        . ' OR suspicious_ua > 0'
        . ' OR not_found_paths >= ' . (int) $thresholds['scan_404_paths']
        . ' ORDER BY max_per_minute DESC, not_found_paths DESC, visits DESC LIMIT ' . (int) $thresholds['anomaly_limit'];
    $rows = xz_visit_stats_ip_normalize_rows((array) $zbp->db->Query($sql));
    foreach ($rows as &$row) {
        $row['reasons'] = xz_visit_stats_ip_anomaly_reasons($row, $thresholds);
    }
    unset($row);

    return $rows;
}

function xz_visit_stats_ip_anomaly_reasons($row, $thresholds = null)
{
    if ($thresholds === null) {
        $thresholds = xz_visit_stats_ip_thresholds();
    }
    $reasons = array();
    if ($row['max_per_minute'] >= $thresholds['high_frequency_per_minute']) {
        $reasons[] = '访问频率较高（单分钟 ' . $row['max_per_minute'] . ' 次）';
    }
    $ratio = $row['visits'] > 0 ? ($row['not_found'] / $row['visits']) * 100 : 0;
    if ($row['visits'] >= $thresholds['minimum_404_requests'] && $ratio >= $thresholds['high_404_ratio']) {
        $reasons[] = '404 请求比例较高（' . number_format($ratio, 1) . '%）';
    }
    if ($row['suspicious_ua'] > 0) {
        $reasons[] = '检测到扫描工具特征 User-Agent';
    }
    if ($row['not_found_paths'] >= $thresholds['scan_404_paths']) {
        $reasons[] = '不存在路径扫描较多（' . $row['not_found_paths'] . ' 个路径）';
    }

    return $reasons;
}

function xz_visit_stats_ip_detail_rows($range, $ip)
{
    global $zbp;

    if ($ip === '') {
        return array();
    }
    $columns = 'vs_Path AS path, vs_Referer AS referer, vs_UserAgent AS user_agent,'
        . ' vs_StatusCode AS status_code, vs_DurationMs AS duration_ms, vs_VisitedAt AS visited_at';
    $sql = 'SELECT ' . $columns . ' FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_ip_where($range, $ip)
        . ' ORDER BY vs_VisitedAt DESC LIMIT ' . (int) xz_visit_stats_ip_thresholds()['detail_limit'];
    $rows = (array) $zbp->db->Query($sql);
    foreach ($rows as &$row) {
        foreach (array('status_code', 'duration_ms', 'visited_at') as $key) {
            $row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
        }
    }
    unset($row);

    return $rows;
}

function xz_visit_stats_ip_build($filters)
{
    $range = xz_visit_stats_stats_range($filters);
    $count = xz_visit_stats_ip_count($range);
    $pageAll = max(1, (int) ceil($count / $filters['page_size']));
    $page = min($filters['page'], $pageAll);

    return array(
        'range' => $range,
        'summary' => xz_visit_stats_ip_summary($range),
        'rows' => xz_visit_stats_ip_rows($range, $page, $filters['page_size']),
        'count' => $count,
        'page' => $page,
        'page_all' => $pageAll,
        'anomalies' => xz_visit_stats_ip_anomalies($range),
        'detail_rows' => xz_visit_stats_ip_detail_rows($range, $filters['ip']),
        'thresholds' => xz_visit_stats_ip_thresholds(),
    );
}
