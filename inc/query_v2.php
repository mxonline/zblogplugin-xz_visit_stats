<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_v2_filters($source = null)
{
    if ($source === null) {
        $source = $_GET;
    }
    $get = function ($name, $default = '') use ($source) {
        return xz_visit_stats_query_value($source, $name, $default);
    };
    $range = xz_visit_stats_query_text($get('range', 'today'), 16);
    if (!in_array($range, array('all', 'today', 'yesterday', '5m', '15m', '30m', '7d', '30d', 'custom'), true)) {
        $range = 'today';
    }
    $type = xz_visit_stats_query_text($get('visit_type', 'all'), 8);
    if (!in_array($type, array('all', 'human', 'bot'), true)) {
        $type = 'all';
    }
    $statusGroup = xz_visit_stats_query_text($get('status_group', 'all'), 8);
    if (!in_array($statusGroup, array('all', '2xx', '3xx', '4xx', '5xx'), true)) {
        $statusGroup = 'all';
    }
    $statusCode = xz_visit_stats_query_text($get('status_code', ''), 3);
    if (preg_match('/^[1-5][0-9]{2}$/', $statusCode) !== 1) {
        $statusCode = '';
    }
    $sourceType = xz_visit_stats_query_text($get('source_type', 'all'), 16);
    if (!in_array($sourceType, array('all', 'direct', 'search', 'ai', 'external', 'social', 'internal', 'campaign', 'other'), true)) {
        $sourceType = 'all';
    }
    $sourceDomain = strtolower(xz_visit_stats_query_text($get('domain', $get('source_domain', '')), 253));
    if ($sourceDomain !== '' && preg_match('/^[a-z0-9.:-]+$/', $sourceDomain) !== 1) {
        $sourceDomain = '';
    }
    $pathKey = strtolower(xz_visit_stats_query_text($get('path_key', ''), 64));
    if ($pathKey !== '' && preg_match('/^[a-f0-9]{64}$/', $pathKey) !== 1) {
        $pathKey = '';
    }
    $botName = xz_visit_stats_query_text($get('bot_name', $get('spider', '')), 64);
    if ($botName !== '' && preg_match('/^[A-Za-z0-9 _.-]{1,64}$/', $botName) !== 1) {
        $botName = '';
    }
    $slowMs = xz_visit_stats_query_text($get('slow_ms', ''), 8);
    if (preg_match('/^[0-9]{1,6}$/', $slowMs) !== 1) {
        $slowMs = '';
    }
    $page = (int) $get('page', 1);
    $pageSize = (int) $get('page_size', 50);

    return array(
        'range' => $range,
        'start' => xz_visit_stats_query_text($get('start', ''), 16),
        'end' => xz_visit_stats_query_text($get('end', ''), 16),
        'visit_type' => $type,
        'status_group' => $statusGroup,
        'status_code' => $statusCode,
        'source_type' => $sourceType,
        'source_domain' => $sourceDomain,
        'path_key' => $pathKey,
        'bot_name' => $botName,
        'ip' => xz_visit_stats_query_text($get('ip', ''), 45),
        'url' => xz_visit_stats_query_text($get('url', ''), 200),
        'referer' => xz_visit_stats_query_text($get('referer', ''), 200),
        'slow_ms' => $slowMs,
        'page' => max(1, min(100000, $page)),
        'page_size' => in_array($pageSize, array(20, 50, 100), true) ? $pageSize : 50,
    );
}

function xz_visit_stats_v2_range($filters, $now = null)
{
    $now = $now === null ? time() : (int) $now;
    $timezone = xz_visit_stats_rollup_timezone();
    $zone = new DateTimeZone($timezone);
    $today = new DateTime('@' . $now);
    $today->setTimezone($zone);
    $today->setTime(0, 0, 0);
    $start = clone $today;
    $end = clone $today;
    $label = '今天';
    if ($filters['range'] === '5m' || $filters['range'] === '15m' || $filters['range'] === '30m') {
        $minutes = (int) substr($filters['range'], 0, -1);
        $end = new DateTime('@' . $now); $end->setTimezone($zone);
        $start = new DateTime('@' . ($now - $minutes * 60)); $start->setTimezone($zone);
        $label = '最近 ' . $minutes . ' 分钟';
    } elseif ($filters['range'] === 'yesterday') {
        $start->modify('-1 day');
        $end = clone $today;
        $label = '昨天';
    } elseif ($filters['range'] === '7d') {
        $start->modify('-6 days');
        $end->modify('+1 day');
        $label = '最近 7 天';
    } elseif ($filters['range'] === '30d') {
        $start->modify('-29 days');
        $end->modify('+1 day');
        $label = '最近 30 天';
    } elseif ($filters['range'] === 'all') {
        $start = new DateTime('@0');
        $start->setTimezone($zone);
        $end->modify('+1 day');
        $label = '全部';
    } elseif ($filters['range'] === 'custom') {
        $startValue = xz_visit_stats_query_datetime($filters['start']);
        $endValue = xz_visit_stats_query_datetime($filters['end']);
        if ($startValue !== null && $endValue !== null && $endValue >= $startValue) {
            $start = new DateTime('@' . $startValue);
            $start->setTimezone($zone);
            $end = new DateTime('@' . ($endValue + 60));
            $end->setTimezone($zone);
            $label = '自定义范围';
        } else {
            $end->modify('+1 day');
            $filters['range'] = 'today';
        }
    } else {
        $end->modify('+1 day');
    }

    return array(
        'range' => $filters['range'], 'start' => $start->getTimestamp(), 'end' => $end->getTimestamp(),
        'timezone' => $timezone, 'today_start' => $today->getTimestamp(), 'label' => $label,
    );
}

function xz_visit_stats_v2_quote($value)
{
    global $zbp;

    return "'" . (method_exists($zbp->db, 'EscapeString') ? $zbp->db->EscapeString((string) $value) : addslashes((string) $value)) . "'";
}

function xz_visit_stats_v2_table()
{
    return xz_visit_stats_upgrade_quote_table(xz_visit_stats_physical_table());
}

function xz_visit_stats_v2_source_case()
{
    $host = "LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(vs_Referer, '/', 3), '/', -1))";
    global $zbp;
    $site = function_exists('xz_visit_stats_source_site_host')
        ? xz_visit_stats_source_site_host()
        : (is_object($zbp) && isset($zbp->host) ? (string) parse_url($zbp->host, PHP_URL_HOST) : 'localhost');
    $site = $site !== '' ? strtolower($site) : 'localhost';

    $fallback = "CASE WHEN vs_Referer = '' THEN 'direct'"
        . " WHEN vs_Referer NOT REGEXP '^https?://' THEN 'other'"
        . " WHEN {$host} = " . xz_visit_stats_v2_quote($site) . " OR {$host} IN ('localhost','127.0.0.1') THEN 'internal'"
        . " WHEN {$host} REGEXP '(^|\\.)(google\\.|baidu\\.(com|cn)$|bing\\.com$|sogou\\.com$|so\\.com$|360\\.cn$)' THEN 'search'"
        . " WHEN {$host} REGEXP '(^|\\.)(weixin\\.qq\\.com$|wechat\\.com$|weibo\\.com$|qq\\.com$|douyin\\.com$|xiaohongshu\\.com$|zhihu\\.com$|bilibili\\.com$)' THEN 'social'"
        . " ELSE 'external' END";
    return "CASE WHEN vs_SourceType <> '' THEN vs_SourceType ELSE ({$fallback}) END";
}

function xz_visit_stats_v2_source_domain_expr()
{
    return "COALESCE(NULLIF(vs_SourceDomain, ''), LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(vs_Referer, '/', 3), '/', -1)))";
}

function xz_visit_stats_v2_where($filters, $range, $alias = '')
{
    $prefix = $alias === '' ? '' : $alias . '.';
    $where = array($prefix . 'vs_VisitedAt >= ' . (int) $range['start'], $prefix . 'vs_VisitedAt < ' . (int) $range['end']);
    if ($filters['visit_type'] === 'human') $where[] = $prefix . 'vs_IsBot = 0';
    if ($filters['visit_type'] === 'bot') $where[] = $prefix . 'vs_IsBot = 1';
    if ($filters['status_code'] !== '') $where[] = $prefix . 'vs_StatusCode = ' . (int) $filters['status_code'];
    elseif ($filters['status_group'] !== 'all') { $base = (int) $filters['status_group'][0] * 100; $where[] = $prefix . 'vs_StatusCode >= ' . $base; $where[] = $prefix . 'vs_StatusCode < ' . ($base + 100); }
    if ($filters['bot_name'] !== '') { $where[] = $prefix . 'vs_BotName = ' . xz_visit_stats_v2_quote($filters['bot_name']); $where[] = $prefix . 'vs_IsBot = 1'; }
    if ($filters['path_key'] !== '') $where[] = $prefix . 'vs_PathKey = ' . xz_visit_stats_v2_quote($filters['path_key']);
    if ($filters['source_type'] !== 'all') $where[] = '(' . xz_visit_stats_v2_source_case() . ') = ' . xz_visit_stats_v2_quote($filters['source_type']);
    if ($filters['source_domain'] !== '') $where[] = "COALESCE(NULLIF({$prefix}vs_SourceDomain, ''), LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX({$prefix}vs_Referer, '/', 3), '/', -1))) = " . xz_visit_stats_v2_quote($filters['source_domain']);
    if ($filters['ip'] !== '') $where[] = $prefix . 'vs_IP LIKE ' . xz_visit_stats_v2_quote($filters['ip'] . '%');
    if ($filters['url'] !== '') $where[] = $prefix . 'vs_Path LIKE ' . xz_visit_stats_v2_quote('%' . $filters['url'] . '%');
    if ($filters['referer'] !== '') $where[] = $prefix . 'vs_Referer LIKE ' . xz_visit_stats_v2_quote('%' . $filters['referer'] . '%');
    if ($filters['slow_ms'] !== '') $where[] = $prefix . 'vs_DurationMs >= ' . (int) $filters['slow_ms'];

    return $where;
}

function xz_visit_stats_v2_exact_summary($filters, $range)
{
    global $zbp;

    $where = implode(' AND ', xz_visit_stats_v2_where($filters, $range));
    $sql = 'SELECT COUNT(*) AS total_pv, SUM(vs_IsBot=0) AS visitor_pv, COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_VisitorHash END) AS visitor_uv,'
        . ' COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_IP END) AS visitor_ip, SUM(vs_IsBot=1) AS bot_pv,'
        . ' SUM(vs_StatusCode BETWEEN 400 AND 499) AS error_4xx, SUM(vs_StatusCode BETWEEN 500 AND 599) AS error_5xx, SUM(vs_StatusCode=404) AS not_found,'
        . ' COALESCE(SUM(vs_DurationMs),0) AS duration_sum, COUNT(vs_DurationMs) AS duration_count, AVG(vs_DurationMs) AS avg_ms FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where;
    $rows = (array) $zbp->db->Query($sql);
    $row = !empty($rows) ? $rows[0] : array();
    foreach (array('total_pv','visitor_pv','visitor_uv','visitor_ip','bot_pv','error_4xx','error_5xx','not_found','duration_sum','duration_count') as $key) $row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
    $row['avg_ms'] = isset($row['avg_ms']) ? (float) $row['avg_ms'] : 0.0;
    return $row;
}

function xz_visit_stats_v2_rollup_days($range)
{
    $zone = new DateTimeZone($range['timezone']);
    $start = new DateTime('@' . $range['start']);
    $end = new DateTime('@' . $range['end']);
    $start->setTimezone($zone);
    $end->setTimezone($zone);
    if ($start->format('H:i:s') !== '00:00:00' || $end->format('H:i:s') !== '00:00:00') {
        return array();
    }
    $today = new DateTime('today', $zone);
    $endDay = clone $end;
    if ($endDay > $today) $endDay = clone $today;
    $days = array();
    while ($start < $endDay) {
        $days[] = $start->format('Y-m-d');
        $start->modify('+1 day');
    }
    return $days;
}

function xz_visit_stats_v2_rollup_site_totals($days)
{
    global $zbp;

    if (empty($days)) return array();
    $quoted = array_map('xz_visit_stats_v2_quote', $days);
    $sql = 'SELECT SUM(rd_VisitorPV) visitor_pv,SUM(rd_VisitorUV) visitor_uv,SUM(rd_VisitorIP) visitor_ip,SUM(rd_BotPV) bot_pv,SUM(rd_Error4xx) error_4xx,SUM(rd_Error5xx) error_5xx,SUM(rd_DurationSum) duration_sum,SUM(rd_DurationCount) duration_count FROM ' . xz_visit_stats_upgrade_quote_table(xz_visit_stats_rollup_table()) . " WHERE rd_Dimension='site' AND rd_Key='all' AND rd_Day IN (" . implode(',', $quoted) . ')';
    $rows = (array) $zbp->db->Query($sql);
    return !empty($rows) ? $rows[0] : array();
}

function xz_visit_stats_v2_summary($filters, $now = null)
{
    // Exact DISTINCT is intentionally calculated over the bounded raw range; daily UV/IP are never summed.
    $range = xz_visit_stats_v2_range($filters, $now);
    $summary = xz_visit_stats_v2_exact_summary($filters, $range);
    $canMix = $filters['visit_type'] === 'all' && $filters['status_group'] === 'all' && $filters['status_code'] === ''
        && $filters['source_type'] === 'all' && $filters['source_domain'] === '' && $filters['path_key'] === ''
        && $filters['bot_name'] === '' && $filters['ip'] === '' && $filters['url'] === '' && $filters['referer'] === '' && $filters['slow_ms'] === '';
    $days = $canMix ? xz_visit_stats_v2_rollup_days($range) : array();
    if (!empty($days)) {
        $rollup = xz_visit_stats_v2_rollup_site_totals($days);
        $currentRange = $range;
        $currentRange['start'] = max($range['start'], $range['today_start']);
        $current = $currentRange['start'] < $currentRange['end'] ? xz_visit_stats_v2_exact_summary($filters, $currentRange) : array();
        foreach (array('visitor_pv','bot_pv','error_4xx','error_5xx','duration_sum','duration_count') as $key) {
            $summary[$key] = (int) (isset($rollup[$key]) ? $rollup[$key] : 0) + (int) (isset($current[$key]) ? $current[$key] : 0);
        }
        $summary['total_pv'] = $summary['visitor_pv'] + $summary['bot_pv'];
        $summary['avg_ms'] = $summary['duration_count'] > 0 ? $summary['duration_sum'] / $summary['duration_count'] : 0.0;
        $summary['source'] = 'rollup+raw_exact_uv_ip';
    }
    $summary['range'] = $range;
    if (!isset($summary['source'])) $summary['source'] = 'raw_exact';
    return $summary;
}

function xz_visit_stats_v2_records($filters, $page = 1, $pageSize = 50)
{
    global $zbp;

    $range = xz_visit_stats_v2_range($filters);
    $page = max(1, (int) $page); $pageSize = in_array((int) $pageSize, array(20,50,100), true) ? (int) $pageSize : 50;
    $where = implode(' AND ', xz_visit_stats_v2_where($filters, $range));
    $offset = ($page - 1) * $pageSize;
    return (array) $zbp->db->Query('SELECT vs_ID,vs_IP,vs_VisitorHash,vs_Url,vs_Path,vs_PathKey,vs_Referer,vs_UserAgent,vs_Browser,vs_Os,vs_Device,vs_IsBot,vs_BotName,vs_StatusCode,vs_DurationMs,vs_VisitedAt FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where . ' ORDER BY vs_VisitedAt DESC,vs_ID DESC LIMIT ' . $offset . ',' . $pageSize);
}

function xz_visit_stats_v2_path_summary($filters, $limit = 20)
{
    global $zbp;

    $range = xz_visit_stats_v2_range($filters); $limit = max(1, min(100, (int) $limit));
    $where = implode(' AND ', xz_visit_stats_v2_where($filters, $range));
    $sql = 'SELECT vs_PathKey AS path_key,MIN(vs_Path) AS path,SUM(vs_IsBot=0) AS visitor_pv,COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_VisitorHash END) AS visitor_uv,COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_IP END) AS visitor_ip,SUM(vs_IsBot=1) AS bot_pv,SUM(vs_StatusCode BETWEEN 400 AND 499) AS error_4xx,SUM(vs_StatusCode BETWEEN 500 AND 599) AS error_5xx,AVG(vs_DurationMs) AS avg_ms,MAX(vs_VisitedAt) AS last_visit FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where . ' GROUP BY vs_PathKey ORDER BY COUNT(*) DESC,last_visit DESC LIMIT ' . $limit;
    $rows = (array) $zbp->db->Query($sql);
    foreach ($rows as &$row) { foreach (array('visitor_pv','visitor_uv','visitor_ip','bot_pv','error_4xx','error_5xx','last_visit') as $key) $row[$key] = isset($row[$key]) ? (int) $row[$key] : 0; $row['avg_ms'] = isset($row['avg_ms']) ? (float) $row['avg_ms'] : 0.0; }
    unset($row);
    return $rows;
}

function xz_visit_stats_v2_source_summary($filters, $limit = 50)
{
    global $zbp;

    $range = xz_visit_stats_v2_range($filters); $limit = max(1, min(100, (int) $limit));
    $where = implode(' AND ', xz_visit_stats_v2_where($filters, $range)); $source = xz_visit_stats_v2_source_case();
    $rows = (array) $zbp->db->Query('SELECT ' . $source . ' AS source_type,COUNT(*) AS total_pv,SUM(vs_IsBot=0) AS visitor_pv,SUM(vs_IsBot=1) AS bot_pv,COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_VisitorHash END) AS visitor_uv,COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_IP END) AS visitor_ip,MAX(vs_VisitedAt) AS last_visit FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where . ' GROUP BY source_type ORDER BY total_pv DESC LIMIT ' . $limit);
    foreach ($rows as &$row) {
        foreach (array('total_pv', 'visitor_pv', 'bot_pv', 'visitor_uv', 'visitor_ip', 'last_visit') as $key) $row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
    }
    unset($row);
    return $rows;
}

function xz_visit_stats_v2_spider_summary($filters, $limit = 50)
{
    global $zbp;

    $filters['visit_type'] = 'bot'; $range = xz_visit_stats_v2_range($filters); $limit = max(1, min(100, (int) $limit));
    $where = implode(' AND ', xz_visit_stats_v2_where($filters, $range));
    $rows = (array) $zbp->db->Query("SELECT CASE WHEN vs_BotName='' OR vs_BotName IS NULL THEN 'Other Bot' ELSE vs_BotName END AS bot_name,COUNT(*) AS bot_pv,COUNT(DISTINCT vs_IP) AS bot_ip,COUNT(DISTINCT vs_PathKey) AS paths,SUM(vs_StatusCode BETWEEN 400 AND 499) AS error_4xx,SUM(vs_StatusCode BETWEEN 500 AND 599) AS error_5xx,AVG(vs_DurationMs) AS avg_ms,MAX(vs_VisitedAt) AS last_visit FROM " . xz_visit_stats_v2_table() . " WHERE {$where} GROUP BY bot_name ORDER BY bot_pv DESC LIMIT {$limit}");
    foreach ($rows as &$row) {
        foreach (array('bot_pv', 'bot_ip', 'paths', 'error_4xx', 'error_5xx', 'last_visit') as $key) $row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
        $row['avg_ms'] = isset($row['avg_ms']) ? (float) $row['avg_ms'] : 0.0;
    }
    unset($row);
    return $rows;
}

function xz_visit_stats_v2_error_summary($filters, $limit = 100)
{
    global $zbp;

    $filters['status_group'] = 'all'; $range = xz_visit_stats_v2_range($filters); $limit = max(1, min(100, (int) $limit));
    $where = implode(' AND ', xz_visit_stats_v2_where($filters, $range));
    $rows = (array) $zbp->db->Query('SELECT vs_PathKey AS path_key,MIN(vs_Path) AS path,vs_StatusCode AS status_code,COUNT(*) AS visits,MIN(vs_VisitedAt) AS first_visit,MAX(vs_VisitedAt) AS last_visit,SUM(vs_IsBot=1) AS bot_pv FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where . ' AND vs_StatusCode >= 400 GROUP BY vs_PathKey,vs_StatusCode ORDER BY visits DESC,last_visit DESC LIMIT ' . $limit);
    foreach ($rows as &$row) {
        foreach (array('status_code', 'visits', 'first_visit', 'last_visit', 'bot_pv') as $key) $row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
    }
    unset($row);
    return $rows;
}

function xz_visit_stats_v2_drilldown_params($filters, $extra = array())
{
    $keys = array('range','start','end','visit_type','status_group','status_code','source_type','source_domain','bot_name','path_key','ip','url','referer','slow_ms');
    $params = array('view' => 'records');
    foreach ($keys as $key) if (isset($filters[$key]) && $filters[$key] !== '' && $filters[$key] !== 'all') $params[$key] = $filters[$key];
    foreach ($extra as $key => $value) if ($value !== '' && $value !== null) $params[$key] = $value;
    return $params;
}

function xz_visit_stats_v2_count($filters)
{
    global $zbp;

    $range = xz_visit_stats_v2_range($filters);
    $where = implode(' AND ', xz_visit_stats_v2_where($filters, $range));
    $rows = (array) $zbp->db->Query('SELECT COUNT(*) AS total FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where);
    return !empty($rows) ? (int) $rows[0]['total'] : 0;
}

function xz_visit_stats_v2_trend($filters)
{
    global $zbp;

    $range = xz_visit_stats_v2_range($filters);
    $days = xz_visit_stats_v2_rollup_days($range);
    $items = array();
    if ($filters['visit_type'] === 'all' && $filters['status_group'] === 'all' && $filters['status_code'] === ''
        && $filters['source_type'] === 'all' && $filters['source_domain'] === '' && $filters['path_key'] === ''
        && $filters['bot_name'] === '' && $filters['ip'] === '' && $filters['url'] === '' && $filters['referer'] === '' && $filters['slow_ms'] === ''
        && !empty($days)) {
        $quoted = array_map('xz_visit_stats_v2_quote', $days);
        $sql = 'SELECT rd_Day day,rd_VisitorPV human_pv,rd_BotPV bot_pv,rd_Error4xx error_4xx,rd_Error5xx error_5xx FROM '
            . xz_visit_stats_upgrade_quote_table(xz_visit_stats_rollup_table())
            . " WHERE rd_Dimension='site' AND rd_Key='all' AND rd_Day IN (" . implode(',', $quoted) . ') ORDER BY rd_Day ASC';
        $rows = (array) $zbp->db->Query($sql);
        foreach ($rows as $row) $items[(string) $row['day']] = array(
            'label' => (string) $row['day'], 'human_pv' => (int) $row['human_pv'], 'bot_pv' => (int) $row['bot_pv'],
            'error_4xx' => (int) $row['error_4xx'], 'error_5xx' => (int) $row['error_5xx'], 'not_found' => 0,
        );
        $currentStart = max($range['start'], $range['today_start']);
        if ($currentStart < $range['end']) {
            $currentWhere = implode(' AND ', xz_visit_stats_v2_where($filters, array('start' => $currentStart, 'end' => $range['end'])));
            $currentRows = (array) $zbp->db->Query('SELECT DATE_FORMAT(FROM_UNIXTIME(vs_VisitedAt), \'%Y-%m-%d\') AS day, SUM(vs_IsBot=0) human_pv, SUM(vs_IsBot=1) bot_pv, SUM(vs_StatusCode BETWEEN 400 AND 499) error_4xx, SUM(vs_StatusCode BETWEEN 500 AND 599) error_5xx FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $currentWhere . ' GROUP BY day');
            foreach ($currentRows as $row) $items[(string) $row['day']] = array(
                'label' => (string) $row['day'], 'human_pv' => (int) $row['human_pv'], 'bot_pv' => (int) $row['bot_pv'],
                'error_4xx' => (int) $row['error_4xx'], 'error_5xx' => (int) $row['error_5xx'], 'not_found' => 0,
            );
        }
        $notFoundRows = (array) $zbp->db->Query('SELECT DATE_FORMAT(FROM_UNIXTIME(vs_VisitedAt), \'%Y-%m-%d\') AS day, COUNT(*) not_found FROM ' . xz_visit_stats_v2_table() . ' WHERE vs_VisitedAt >= ' . (int) $range['start'] . ' AND vs_VisitedAt < ' . (int) $range['end'] . ' AND vs_StatusCode=404 GROUP BY day');
        foreach ($notFoundRows as $row) { $key = (string) $row['day']; if (!isset($items[$key])) $items[$key] = array('label' => $key, 'human_pv' => 0, 'bot_pv' => 0, 'error_4xx' => 0, 'error_5xx' => 0); $items[$key]['not_found'] = (int) $row['not_found']; }
    } else {
        $where = implode(' AND ', xz_visit_stats_v2_where($filters, $range));
        $rows = (array) $zbp->db->Query('SELECT DATE_FORMAT(FROM_UNIXTIME(vs_VisitedAt), \'%Y-%m-%d\') AS day, SUM(vs_IsBot=0) human_pv, SUM(vs_IsBot=1) bot_pv, SUM(vs_StatusCode BETWEEN 400 AND 499) error_4xx, SUM(vs_StatusCode BETWEEN 500 AND 599) error_5xx FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where . ' GROUP BY day ORDER BY day ASC');
        foreach ($rows as $row) $items[(string) $row['day']] = array(
            'label' => (string) $row['day'], 'human_pv' => (int) $row['human_pv'], 'bot_pv' => (int) $row['bot_pv'],
            'error_4xx' => (int) $row['error_4xx'], 'error_5xx' => (int) $row['error_5xx'], 'not_found' => 0,
        );
    }
    $cursor = new DateTime('@' . $range['start']);
    $end = new DateTime('@' . $range['end']);
    $zone = new DateTimeZone($range['timezone']);
    $cursor->setTimezone($zone); $end->setTimezone($zone); $cursor->setTime(0, 0, 0);
    while ($cursor < $end) {
        $key = $cursor->format('Y-m-d');
        if (!isset($items[$key])) $items[$key] = array('label' => $key, 'human_pv' => 0, 'bot_pv' => 0, 'error_4xx' => 0, 'error_5xx' => 0, 'not_found' => 0);
        $cursor->modify('+1 day');
    }
    ksort($items);
    return array_values($items);
}

function xz_visit_stats_v2_status_distribution($filters)
{
    global $zbp;

    $range = xz_visit_stats_v2_range($filters);
    $where = implode(' AND ', xz_visit_stats_v2_where($filters, $range));
    $rows = (array) $zbp->db->Query('SELECT FLOOR(vs_StatusCode / 100) AS code_group, COUNT(*) AS visits, SUM(vs_StatusCode=404) AS not_found FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where . ' GROUP BY code_group ORDER BY code_group');
    $result = array();
    foreach (array(2, 3, 4, 5) as $group) $result[$group . 'xx'] = array('visits' => 0, 'not_found' => 0);
    foreach ($rows as $row) { $key = ((int) $row['code_group']) . 'xx'; if (isset($result[$key])) { $result[$key]['visits'] = (int) $row['visits']; $result[$key]['not_found'] = (int) $row['not_found']; } }
    return $result;
}

function xz_visit_stats_v2_state_snapshot()
{
    global $zbp;

    $state = array('last_rollup' => 0, 'status' => '未运行', 'message' => '', 'pathkey_status' => '未运行');
    if ($zbp->db->ExistTable(xz_visit_stats_rollup_state_table())) {
        $table = xz_visit_stats_upgrade_quote_table(xz_visit_stats_rollup_state_table());
        $rows = (array) $zbp->db->Query("SELECT rs_Name,rs_Status,rs_LastRunAt,rs_LastError FROM {$table} ORDER BY rs_LastRunAt DESC");
        foreach ($rows as $row) {
            if ($row['rs_Name'] === 'daily' && $state['last_rollup'] === 0) { $state['last_rollup'] = (int) $row['rs_LastRunAt']; $state['status'] = (string) $row['rs_Status']; $state['message'] = (string) $row['rs_LastError']; }
            if ($row['rs_Name'] === 'pathkey') $state['pathkey_status'] = (string) $row['rs_Status'];
        }
    }
    return $state;
}

function xz_visit_stats_v2_source_domains($filters, $limit = 20)
{
    global $zbp;

    $range = xz_visit_stats_v2_range($filters); $where = implode(' AND ', xz_visit_stats_v2_where($filters, $range));
    $source = xz_visit_stats_v2_source_case();
    $domain = xz_visit_stats_v2_source_domain_expr();
    $sql = 'SELECT ' . $domain . ' AS domain, COUNT(*) visits, SUM(vs_IsBot=0) human_pv FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where . " AND vs_Referer <> '' AND ({$source}) IN ('external','search','social','ai') GROUP BY domain ORDER BY visits DESC LIMIT " . max(1, min(100, (int) $limit));
    $rows = (array) $zbp->db->Query($sql);
    foreach ($rows as &$row) { $row['visits'] = (int) $row['visits']; $row['human_pv'] = (int) $row['human_pv']; }
    unset($row);
    return $rows;
}

function xz_visit_stats_v2_referers($filters, $limit = 20)
{
    global $zbp;

    $range = xz_visit_stats_v2_range($filters); $where = implode(' AND ', xz_visit_stats_v2_where($filters, $range));
    $sql = 'SELECT SUBSTRING(vs_Referer,1,512) AS referer, COUNT(*) visits, SUM(vs_IsBot=0) human_pv FROM ' . xz_visit_stats_v2_table() . ' WHERE ' . $where . " AND vs_Referer <> '' GROUP BY referer ORDER BY visits DESC LIMIT " . max(1, min(100, (int) $limit));
    $rows = (array) $zbp->db->Query($sql);
    foreach ($rows as &$row) { $row['visits'] = (int) $row['visits']; $row['human_pv'] = (int) $row['human_pv']; }
    unset($row);
    return $rows;
}
