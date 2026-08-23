<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_source_filters($source = null)
{
    $filters = xz_visit_stats_stats_filters($source);
    if ($source === null) {
        $source = $_GET;
    }
    $types = array('all', 'direct', 'search', 'social', 'internal', 'external', 'other');
    $type = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'source_type', 'all'), 16);
    if (!in_array($type, $types, true)) {
        $type = 'all';
    }
    $domain = strtolower(xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'domain', ''), 253));
    if ($domain !== '' && preg_match('/^[a-z0-9.-]+$/', $domain) !== 1) {
        $domain = '';
    }
    $pageRaw = trim((string) xz_visit_stats_query_value($source, 'page', '1'));
    $page = preg_match('/^[0-9]+$/', $pageRaw) === 1 ? max(1, (int) $pageRaw) : 1;
    $sizeRaw = trim((string) xz_visit_stats_query_value($source, 'page_size', '20'));
    $size = preg_match('/^[0-9]+$/', $sizeRaw) === 1 ? (int) $sizeRaw : 20;
    if ($size > 100) {
        $size = 100;
    } elseif (!in_array($size, array(20, 50, 100), true)) {
        $size = 20;
    }
    $filters['source_type'] = $type;
    $filters['domain'] = $domain;
    $filters['page'] = $page;
    $filters['page_size'] = $size;

    return $filters;
}

function xz_visit_stats_source_host_expression()
{
    return "LOWER(SUBSTRING_INDEX(SUBSTRING_INDEX(vs_Referer, '/', 3), '/', -1))";
}

function xz_visit_stats_source_site_host()
{
    global $zbp;

    $host = parse_url((string) $zbp->host, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        $host = 'localhost';
    }

    return strtolower($host);
}

function xz_visit_stats_source_type_labels()
{
    return array(
        'direct' => '直接访问', 'search' => '搜索引擎', 'social' => '社交媒体',
        'internal' => '站内来源', 'external' => '外部网站', 'other' => '其他来源',
    );
}

function xz_visit_stats_source_type_case()
{
    $host = xz_visit_stats_source_host_expression();
    $site = str_replace("'", "''", xz_visit_stats_source_site_host());

    return "CASE"
        . " WHEN vs_Referer = '' THEN '直接访问'"
        . " WHEN vs_Referer NOT REGEXP '^https?://' THEN '其他来源'"
        . " WHEN " . $host . " = '" . $site . "' OR " . $host . " IN ('localhost', '127.0.0.1') THEN '站内来源'"
        . " WHEN " . $host . " REGEXP '(^|\\\\.)(google\\\\.|baidu\\\\.(com|cn)$|bing\\\\.com$|sogou\\\\.com$|so\\\\.com$|360\\\\.cn$)' THEN '搜索引擎'"
        . " WHEN " . $host . " REGEXP '(^|\\\\.)(weixin\\\\.qq\\\\.com$|wechat\\\\.com$|weibo\\\\.com$|qq\\\\.com$|douyin\\\\.com$|xiaohongshu\\\\.com$|zhihu\\\\.com$|bilibili\\\\.com$)' THEN '社交媒体'"
        . " ELSE '外部网站' END";
}

function xz_visit_stats_source_search_name_case()
{
    $host = xz_visit_stats_source_host_expression();

    return "CASE"
        . " WHEN " . $host . " REGEXP '(^|\\\\.)google\\\\.' THEN 'Google'"
        . " WHEN " . $host . " REGEXP '(^|\\\\.)baidu\\\\.(com|cn)$' THEN '百度'"
        . " WHEN " . $host . " REGEXP '(^|\\\\.)bing\\\\.com$' THEN 'Bing'"
        . " WHEN " . $host . " REGEXP '(^|\\\\.)sogou\\\\.com$' THEN '搜狗'"
        . " WHEN " . $host . " REGEXP '(^|\\\\.)(so\\\\.com$|360\\\\.cn$)' THEN '360 搜索'"
        . " ELSE '其他搜索' END";
}

function xz_visit_stats_source_where($filters, $range)
{
    $where = 'vs_IsBot = 0 AND vs_VisitedAt >= ' . (int) $range['start']
        . ' AND vs_VisitedAt < ' . (int) $range['end'];
    if ($filters['source_type'] !== 'all') {
        $labels = xz_visit_stats_source_type_labels();
        $where .= " AND (" . xz_visit_stats_source_type_case() . ") = '"
            . $labels[$filters['source_type']] . "'";
    }
    if ($filters['domain'] !== '') {
        $where .= " AND " . xz_visit_stats_source_host_expression() . " = '"
            . str_replace("'", "''", $filters['domain']) . "'";
    }

    return $where;
}

function xz_visit_stats_source_summary($filters, $range)
{
    $type = xz_visit_stats_source_type_case();
    $where = xz_visit_stats_source_where($filters, $range);
    $sql = "SELECT COUNT(*) AS visits, COUNT(DISTINCT vs_VisitorHash) AS uv,"
        . " COUNT(DISTINCT " . xz_visit_stats_source_host_expression() . ") AS domains,"
        . " SUM((" . $type . ") = '直接访问') AS direct,"
        . " SUM((" . $type . ") = '搜索引擎') AS search,"
        . " SUM((" . $type . ") IN ('外部网站', '社交媒体')) AS external,"
        . " AVG(vs_DurationMs) AS avg_ms"
        . ' FROM ' . xz_visit_stats_stats_table() . ' WHERE ' . $where;
    $row = xz_visit_stats_stats_row($sql);
    $summary = array();
    foreach (array('visits', 'uv', 'domains', 'direct', 'search', 'external') as $key) {
        $summary[$key] = xz_visit_stats_stats_number($row, $key);
    }
    $summary['avg_ms'] = xz_visit_stats_stats_number($row, 'avg_ms', true);

    return $summary;
}

function xz_visit_stats_source_type_distribution($filters, $range)
{
    global $zbp;

    $sql = 'SELECT ' . xz_visit_stats_source_type_case() . ' AS type, COUNT(*) AS visits'
        . ' FROM ' . xz_visit_stats_stats_table() . ' WHERE ' . xz_visit_stats_source_where($filters, $range)
        . ' GROUP BY type ORDER BY visits DESC, type ASC';
    $rows = (array) $zbp->db->Query($sql);
    $total = 0;
    foreach ($rows as $row) {
        $total += (int) $row['visits'];
    }
    foreach ($rows as &$row) {
        $row['visits'] = (int) $row['visits'];
        $row['percent'] = $total > 0 ? ($row['visits'] / $total) * 100 : 0;
    }
    unset($row);

    return $rows;
}

function xz_visit_stats_source_search_distribution($filters, $range)
{
    global $zbp;

    $where = xz_visit_stats_source_where($filters, $range)
        . " AND (" . xz_visit_stats_source_type_case() . ") = '搜索引擎'";
    $sql = 'SELECT ' . xz_visit_stats_source_search_name_case() . ' AS name, COUNT(*) AS visits'
        . ' FROM ' . xz_visit_stats_stats_table() . ' WHERE ' . $where
        . ' GROUP BY name ORDER BY visits DESC, name ASC';
    $rows = (array) $zbp->db->Query($sql);
    $total = 0;
    foreach ($rows as $row) {
        $total += (int) $row['visits'];
    }
    foreach ($rows as &$row) {
        $row['visits'] = (int) $row['visits'];
        $row['percent'] = $total > 0 ? ($row['visits'] / $total) * 100 : 0;
    }
    unset($row);

    return $rows;
}

function xz_visit_stats_source_trend($filters, $range)
{
    global $zbp;

    $unit = xz_visit_stats_stats_unit($range);
    $format = $unit === 'hour' ? '%Y-%m-%d %H:00' : '%Y-%m-%d';
    $type = xz_visit_stats_source_type_case();
    $sql = "SELECT DATE_FORMAT(FROM_UNIXTIME(vs_VisitedAt), '" . $format . "') AS bucket, COUNT(*) AS visits,"
        . " SUM((" . $type . ") = '直接访问') AS direct, SUM((" . $type . ") = '搜索引擎') AS search,"
        . " SUM((" . $type . ") IN ('外部网站', '社交媒体')) AS external"
        . ' FROM ' . xz_visit_stats_stats_table() . ' WHERE ' . xz_visit_stats_source_where($filters, $range)
        . ' GROUP BY bucket ORDER BY bucket ASC';
    $rows = (array) $zbp->db->Query($sql);
    $values = array();
    foreach ($rows as $row) {
        $values[(string) $row['bucket']] = array('visits' => (int) $row['visits'], 'direct' => (int) $row['direct'], 'search' => (int) $row['search'], 'external' => (int) $row['external']);
    }
    $step = $unit === 'hour' ? 3600 : 86400;
    $cursor = $unit === 'hour' ? strtotime(date('Y-m-d H:00:00', $range['start'])) : strtotime(date('Y-m-d 00:00:00', $range['start']));
    $items = array();
    while ($cursor < $range['end']) {
        $key = date($unit === 'hour' ? 'Y-m-d H:00' : 'Y-m-d', $cursor);
        $item = isset($values[$key]) ? $values[$key] : array('visits' => 0, 'direct' => 0, 'search' => 0, 'external' => 0);
        $item['label'] = date($unit === 'hour' ? 'H:00' : 'm-d', $cursor);
        $items[] = $item;
        $cursor += $step;
    }

    return array('unit' => $unit, 'items' => $items);
}

function xz_visit_stats_source_domain_count($filters, $range)
{
    $type = xz_visit_stats_source_type_case();
    $where = xz_visit_stats_source_where($filters, $range)
        . " AND (" . $type . ") IN ('外部网站', '社交媒体')";
    $sql = 'SELECT COUNT(DISTINCT ' . xz_visit_stats_source_host_expression() . ') AS num FROM '
        . xz_visit_stats_stats_table() . ' WHERE ' . $where;

    return xz_visit_stats_stats_number(xz_visit_stats_stats_row($sql), 'num');
}

function xz_visit_stats_source_domains($filters, $range, $page, $pageSize)
{
    global $zbp;

    $type = xz_visit_stats_source_type_case();
    $where = xz_visit_stats_source_where($filters, $range)
        . " AND (" . $type . ") IN ('外部网站', '社交媒体')";
    $sql = 'SELECT ' . xz_visit_stats_source_host_expression() . ' AS domain, ' . $type . ' AS type,'
        . ' COUNT(*) AS visits, COUNT(DISTINCT vs_VisitorHash) AS uv, MAX(vs_VisitedAt) AS last_visit,'
        . ' AVG(vs_DurationMs) AS avg_ms FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . $where . ' GROUP BY domain, type ORDER BY visits DESC, last_visit DESC'
        . ' LIMIT ' . (int) max(0, ($page - 1) * $pageSize) . ', ' . (int) $pageSize;
    $rows = (array) $zbp->db->Query($sql);
    foreach ($rows as &$row) {
        foreach (array('visits', 'uv', 'last_visit') as $key) {
            $row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
        }
        $row['avg_ms'] = isset($row['avg_ms']) ? (float) $row['avg_ms'] : 0.0;
    }
    unset($row);

    return $rows;
}

function xz_visit_stats_source_build($filters)
{
    $range = xz_visit_stats_stats_range($filters);
    $summary = xz_visit_stats_source_summary($filters, $range);
    $domainCount = xz_visit_stats_source_domain_count($filters, $range);
    $pageAll = max(1, (int) ceil($domainCount / $filters['page_size']));
    $page = min($filters['page'], $pageAll);

    return array(
        'range' => $range,
        'summary' => $summary,
        'types' => xz_visit_stats_source_type_distribution($filters, $range),
        'searches' => xz_visit_stats_source_search_distribution($filters, $range),
        'trend' => xz_visit_stats_source_trend($filters, $range),
        'domains' => xz_visit_stats_source_domains($filters, $range, $page, $filters['page_size']),
        'domain_count' => $domainCount,
        'page' => $page,
        'page_all' => $pageAll,
    );
}
