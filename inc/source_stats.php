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
    $filters['referer'] = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'referer', ''), 200);
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

function xz_visit_stats_source_referer_details($referer)
{
    $referer = (string) $referer;
    $parsed = $referer !== '' ? parse_url($referer) : false;
    $host = is_array($parsed) && isset($parsed['host']) ? strtolower((string) $parsed['host']) : '';
    $host = preg_replace('/:\\d+$/', '', $host);
    $siteHost = xz_visit_stats_source_site_host();
    $type = 'other';
    $name = $host;
    $searchEngine = '';
    $keyword = '';

    if ($referer === '') {
        $type = 'direct';
        $name = '直接访问';
    } elseif ($host === '' || !isset($parsed['scheme']) || !in_array(strtolower((string) $parsed['scheme']), array('http', 'https'), true)) {
        $name = $host !== '' ? $host : '未知来源';
    } elseif ($host === $siteHost || in_array($host, array('localhost', '127.0.0.1'), true)) {
        $type = 'internal';
        $name = '站内来源';
    } else {
        $searchHosts = array(
            'baidu' => array('baidu.com', 'baidu.cn'), 'bing' => array('bing.com'),
            'google' => array('google.'), 'sogou' => array('sogou.com'), '360' => array('so.com', '360.cn'),
        );
        $searchEngineNames = array('baidu' => '百度', 'bing' => 'Bing', 'google' => 'Google', 'sogou' => '搜狗', '360' => '360 搜索');
        $queryKeys = array(
            'baidu' => array('wd', 'word'), 'bing' => array('q'), 'google' => array('q'),
            'sogou' => array('query', 'keyword'), '360' => array('q', 'keyword'),
        );
        foreach ($searchHosts as $engine => $domains) {
            foreach ($domains as $domain) {
                $matched = $engine === 'google'
                    ? preg_match('/(^|\\.)google\\.[a-z.]+$/', $host) === 1
                    : ($host === $domain || substr($host, -strlen('.' . $domain)) === '.' . $domain);
                if (!$matched) {
                    continue;
                }
                $type = 'search';
                $searchEngine = $searchEngineNames[$engine];
                $name = $searchEngine;
                if (isset($parsed['query'])) {
                    $query = array();
                    parse_str((string) $parsed['query'], $query);
                    foreach ($queryKeys[$engine] as $queryKey) {
                        if (isset($query[$queryKey]) && is_string($query[$queryKey]) && trim($query[$queryKey]) !== '') {
                            $keyword = trim($query[$queryKey]);
                            break;
                        }
                    }
                }
                break 2;
            }
        }
        if ($type !== 'search') {
            $sourceNames = array(
                'weixin.qq.com' => '微信', 'wechat.com' => '微信', 'weibo.com' => '微博', 'qq.com' => 'QQ',
                'douyin.com' => '抖音', 'xiaohongshu.com' => '小红书', 'zhihu.com' => '知乎', 'bilibili.com' => '哔哩哔哩',
            );
            foreach ($sourceNames as $domain => $sourceName) {
                if ($host === $domain || substr($host, -strlen('.' . $domain)) === '.' . $domain) {
                    $name = $sourceName;
                    $type = 'social';
                    break;
                }
            }
            if ($type === 'other') {
                $type = 'external';
            }
        }
    }

    $labels = xz_visit_stats_source_type_labels();
    return array(
        'referer' => $referer, 'domain' => $host, 'name' => $name !== '' ? $name : '未知来源',
        'type' => $type, 'type_label' => isset($labels[$type]) ? $labels[$type] : $labels['other'],
        'search_engine' => $searchEngine, 'keyword' => $keyword,
    );
}

function xz_visit_stats_source_referer_cell($referer)
{
    $details = xz_visit_stats_source_referer_details($referer);
    $escape = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };
    $keyword = $details['keyword'] !== '' ? $details['keyword'] : '未提供';
    $title = $details['referer'] !== '' ? $details['referer'] : '未提供';

    return '<span class="xz-referer-hover" tabindex="0"><span class="xz-referer-label" title="' . $escape($title) . '">' . $escape($details['name']) . '</span>'
        . '<span class="xz-referer-tooltip" role="tooltip"><strong>来源详情</strong>'
        . '<span><b>完整 Referer URL：</b>' . $escape($title) . '</span>'
        . '<span><b>来源域名：</b>' . $escape($details['domain'] !== '' ? $details['domain'] : '未提供') . '</span>'
        . '<span><b>来源类型：</b>' . $escape($details['type_label']) . '</span>'
        . ($details['search_engine'] !== '' ? '<span><b>搜索引擎：</b>' . $escape($details['search_engine']) . '</span><span><b>搜索关键词：</b>' . $escape($keyword) . '</span>' : '')
        . '</span></span>';
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
    if ($filters['referer'] !== '') {
        $where .= " AND vs_Referer LIKE '%" . str_replace("'", "''", $filters['referer']) . "%'";
    }
    if ($filters['ip'] !== '') {
        $ip = str_replace("'", "''", $filters['ip']);
        $where .= $filters['ip_mode'] === 'exact'
            ? " AND vs_IP = '" . $ip . "'"
            : " AND vs_IP LIKE '" . $ip . "%'";
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
    $where = xz_visit_stats_source_where($filters, $range) . " AND vs_Referer <> ''";
    $sql = 'SELECT COUNT(DISTINCT ' . xz_visit_stats_source_host_expression() . ') AS num FROM '
        . xz_visit_stats_stats_table() . ' WHERE ' . $where;

    return xz_visit_stats_stats_number(xz_visit_stats_stats_row($sql), 'num');
}

function xz_visit_stats_source_domains($filters, $range, $page, $pageSize)
{
    global $zbp;

    $type = xz_visit_stats_source_type_case();
    $where = xz_visit_stats_source_where($filters, $range) . " AND vs_Referer <> ''";
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

function xz_visit_stats_source_links($filters, $range, $limit = 100)
{
    global $zbp;

    $limit = max(1, min(100, (int) $limit));
    $where = xz_visit_stats_source_where($filters, $range) . " AND vs_Referer <> ''";
    $sql = 'SELECT vs_Referer AS referer, COUNT(*) AS visits, MAX(vs_VisitedAt) AS last_visit'
        . ' FROM ' . xz_visit_stats_stats_table() . ' WHERE ' . $where
        . ' GROUP BY vs_Referer ORDER BY visits DESC, last_visit DESC LIMIT ' . $limit;
    $rows = (array) $zbp->db->Query($sql);
    foreach ($rows as &$row) {
        $row['referer'] = isset($row['referer']) ? (string) $row['referer'] : '';
        $row['visits'] = isset($row['visits']) ? (int) $row['visits'] : 0;
        $row['last_visit'] = isset($row['last_visit']) ? (int) $row['last_visit'] : 0;
    }
    unset($row);

    return $rows;
}

function xz_visit_stats_source_record_count($filters, $range)
{
    $row = xz_visit_stats_stats_row('SELECT COUNT(*) AS num FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_source_where($filters, $range) . " AND vs_Referer <> ''");

    return xz_visit_stats_stats_number($row, 'num');
}

function xz_visit_stats_source_records($filters, $range, $page, $pageSize)
{
    global $zbp;

    $offset = max(0, ($page - 1) * $pageSize);
    $host = xz_visit_stats_source_host_expression();
    $sql = 'SELECT vs_ID AS id, ' . $host . ' AS domain, vs_Referer AS referer, vs_IP AS ip, vs_Url AS url, vs_Path AS path, vs_VisitedAt AS visited_at'
        . ' FROM ' . xz_visit_stats_stats_table() . ' WHERE ' . xz_visit_stats_source_where($filters, $range) . " AND vs_Referer <> ''"
        . ' ORDER BY vs_VisitedAt DESC, vs_ID DESC LIMIT ' . $offset . ', ' . (int) $pageSize;
    $rows = (array) $zbp->db->Query($sql);
    foreach ($rows as &$row) {
        foreach (array('id', 'visited_at') as $key) {
            $row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
        }
        foreach (array('domain', 'referer', 'ip', 'url', 'path') as $key) {
            $row[$key] = isset($row[$key]) ? (string) $row[$key] : '';
        }
    }
    unset($row);

    return $rows;
}

function xz_visit_stats_source_build($filters)
{
    $range = xz_visit_stats_stats_range($filters);
    $summary = xz_visit_stats_source_summary($filters, $range);
    $domainCount = xz_visit_stats_source_domain_count($filters, $range);
    $recordCount = xz_visit_stats_source_record_count($filters, $range);
    $pageAll = max(1, (int) ceil($domainCount / $filters['page_size']));
    $page = min($filters['page'], $pageAll);

    return array(
        'range' => $range,
        'summary' => $summary,
        'types' => xz_visit_stats_source_type_distribution($filters, $range),
        'searches' => xz_visit_stats_source_search_distribution($filters, $range),
        'trend' => xz_visit_stats_source_trend($filters, $range),
        'domains' => xz_visit_stats_source_domains($filters, $range, $page, $filters['page_size']),
        'links' => xz_visit_stats_source_links($filters, $range),
        'records' => xz_visit_stats_source_records($filters, $range, $page, $filters['page_size']),
        'domain_count' => $domainCount,
        'record_count' => $recordCount,
        'page' => $page,
        'page_all' => $pageAll,
    );
}
