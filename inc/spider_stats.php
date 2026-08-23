<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_spider_names()
{
    return array(
        'Googlebot', 'Baiduspider', 'bingbot', 'Sogou', '360Spider',
        'HaosouSpider', 'Bytespider', 'PetalBot', 'YandexBot',
        'DuckDuckBot', 'Applebot',
    );
}

function xz_visit_stats_spider_filters($source = null)
{
    $filters = xz_visit_stats_stats_filters($source);
    if ($source === null) {
        $source = $_GET;
    }
    $allowed = array_merge(array('all', 'Other Bot'), xz_visit_stats_spider_names());
    $spider = xz_visit_stats_query_text(
        xz_visit_stats_query_value($source, 'spider', 'all'),
        64
    );
    if (!in_array($spider, $allowed, true)) {
        $spider = 'all';
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

    $filters['spider'] = $spider;
    $filters['page'] = $page;
    $filters['page_size'] = $size;

    return $filters;
}

function xz_visit_stats_spider_condition($filters)
{
    if ($filters['spider'] === 'all') {
        return 'vs_IsBot = 1';
    }
    if ($filters['spider'] === 'Other Bot') {
        return "vs_IsBot = 1 AND (vs_BotName = '' OR vs_BotName IS NULL)";
    }

    // The name is selected from the fixed whitelist above.
    return "vs_IsBot = 1 AND vs_BotName = '" . $filters['spider'] . "'";
}

function xz_visit_stats_spider_where($filters, $range)
{
    return xz_visit_stats_spider_condition($filters)
        . ' AND vs_VisitedAt >= ' . (int) $range['start']
        . ' AND vs_VisitedAt < ' . (int) $range['end'];
}

function xz_visit_stats_spider_summary($filters, $range)
{
    $where = xz_visit_stats_spider_where($filters, $range);
    $sql = 'SELECT COUNT(*) AS visits, COUNT(DISTINCT vs_IP) AS ips,'
        . ' COUNT(DISTINCT vs_Path) AS paths, SUM(vs_StatusCode >= 200 AND vs_StatusCode < 300) AS status_2xx,'
        . ' SUM(vs_StatusCode >= 300 AND vs_StatusCode < 400) AS status_3xx,'
        . ' SUM(vs_StatusCode >= 400 AND vs_StatusCode < 500) AS status_4xx,'
        . ' SUM(vs_StatusCode >= 500 AND vs_StatusCode < 600) AS status_5xx,'
        . ' SUM(vs_StatusCode = 404) AS not_found, AVG(vs_DurationMs) AS avg_ms,'
        . ' MAX(vs_VisitedAt) AS last_visit'
        . ' FROM ' . xz_visit_stats_stats_table() . ' WHERE ' . $where;
    $row = xz_visit_stats_stats_row($sql);
    $keys = array('visits', 'ips', 'paths', 'status_2xx', 'status_3xx', 'status_4xx', 'status_5xx', 'not_found', 'last_visit');
    $summary = array();
    foreach ($keys as $key) {
        $summary[$key] = xz_visit_stats_stats_number($row, $key);
    }
    $summary['avg_ms'] = xz_visit_stats_stats_number($row, 'avg_ms', true);

    return $summary;
}

function xz_visit_stats_spider_distribution($filters, $range)
{
    global $zbp;

    $sql = "SELECT CASE WHEN vs_BotName = '' OR vs_BotName IS NULL THEN 'Other Bot' ELSE vs_BotName END AS name,"
        . ' COUNT(*) AS visits FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_spider_where($filters, $range)
        . ' GROUP BY name ORDER BY visits DESC, name ASC';
    $rows = (array) $zbp->db->Query($sql);
    $total = 0;
    foreach ($rows as $row) {
        $total += (int) $row['visits'];
    }
    $items = array();
    foreach ($rows as $row) {
        $items[] = array(
            'name' => (string) $row['name'],
            'visits' => (int) $row['visits'],
            'percent' => $total > 0 ? ((int) $row['visits'] / $total) * 100 : 0,
        );
    }

    return $items;
}

function xz_visit_stats_spider_trend($filters, $range)
{
    global $zbp;

    $unit = xz_visit_stats_stats_unit($range);
    $format = $unit === 'hour' ? '%Y-%m-%d %H:00' : '%Y-%m-%d';
    $sql = "SELECT DATE_FORMAT(FROM_UNIXTIME(vs_VisitedAt), '" . $format . "') AS bucket, COUNT(*) AS visits"
        . ' FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_spider_where($filters, $range)
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

function xz_visit_stats_spider_hours($filters, $range)
{
    global $zbp;

    $sql = 'SELECT HOUR(FROM_UNIXTIME(vs_VisitedAt)) AS hour, COUNT(*) AS visits'
        . ' FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_spider_where($filters, $range)
        . ' GROUP BY hour ORDER BY hour ASC';
    $rows = (array) $zbp->db->Query($sql);
    $values = array();
    foreach ($rows as $row) {
        $values[(int) $row['hour']] = (int) $row['visits'];
    }
    $items = array();
    for ($hour = 0; $hour < 24; $hour++) {
        $items[] = array('label' => sprintf('%02d', $hour), 'visits' => isset($values[$hour]) ? $values[$hour] : 0);
    }

    return $items;
}

function xz_visit_stats_spider_url_count($filters, $range)
{
    $sql = 'SELECT COUNT(DISTINCT vs_Path) AS num FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_spider_where($filters, $range);
    $row = xz_visit_stats_stats_row($sql);

    return xz_visit_stats_stats_number($row, 'num');
}

function xz_visit_stats_spider_urls($filters, $range, $page, $pageSize)
{
    global $zbp;

    $offset = max(0, ($page - 1) * $pageSize);
    $sql = "SELECT vs_Path AS path, COUNT(*) AS visits,"
        . " GROUP_CONCAT(DISTINCT CASE WHEN vs_BotName = '' OR vs_BotName IS NULL THEN 'Other Bot' ELSE vs_BotName END ORDER BY vs_BotName SEPARATOR ', ') AS spiders,"
        . ' COUNT(DISTINCT vs_IP) AS ips, MAX(vs_VisitedAt) AS last_visit,'
        . ' SUM(vs_StatusCode >= 200 AND vs_StatusCode < 300) AS status_2xx,'
        . ' SUM(vs_StatusCode >= 300 AND vs_StatusCode < 400) AS status_3xx,'
        . ' SUM(vs_StatusCode >= 400 AND vs_StatusCode < 500) AS status_4xx,'
        . ' SUM(vs_StatusCode >= 500 AND vs_StatusCode < 600) AS status_5xx,'
        . ' SUM(vs_StatusCode = 404) AS not_found, AVG(vs_DurationMs) AS avg_ms'
        . ' FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_spider_where($filters, $range)
        . ' GROUP BY vs_Path ORDER BY visits DESC, last_visit DESC LIMIT ' . (int) $offset . ', ' . (int) $pageSize;
    $rows = (array) $zbp->db->Query($sql);
    foreach ($rows as &$row) {
        foreach (array('visits', 'ips', 'last_visit', 'status_2xx', 'status_3xx', 'status_4xx', 'status_5xx', 'not_found') as $key) {
            $row[$key] = isset($row[$key]) ? (int) $row[$key] : 0;
        }
        $row['avg_ms'] = isset($row['avg_ms']) ? (float) $row['avg_ms'] : 0.0;
    }
    unset($row);

    return $rows;
}

function xz_visit_stats_spider_recent_activity()
{
    global $zbp;

    $start = strtotime('-6 days', strtotime('today'));
    $end = strtotime('tomorrow');
    $sql = 'SELECT vs_BotName AS name, COUNT(*) AS visits FROM ' . xz_visit_stats_stats_table()
        . ' WHERE vs_IsBot = 1 AND vs_BotName IN (\'Googlebot\', \'Baiduspider\', \'bingbot\')'
        . ' AND vs_VisitedAt >= ' . (int) $start . ' AND vs_VisitedAt < ' . (int) $end
        . ' GROUP BY vs_BotName';
    $rows = (array) $zbp->db->Query($sql);
    $activity = array('Googlebot' => 0, 'Baiduspider' => 0, 'bingbot' => 0);
    foreach ($rows as $row) {
        $activity[(string) $row['name']] = (int) $row['visits'];
    }

    return $activity;
}

function xz_visit_stats_spider_report_thresholds()
{
    return array(
        'minimum_sample' => 5,
        'high_404_ratio' => 10.0,
        'high_avg_ms' => 1000.0,
        'hot_url_visits' => 5,
        'hot_url_404_ratio' => 50.0,
    );
}

function xz_visit_stats_spider_report($summary, $urls, $recentActivity)
{
    $thresholds = xz_visit_stats_spider_report_thresholds();
    $items = array();
    if ($summary['visits'] === 0) {
        return array('当前范围无蜘蛛抓取记录，暂无 SEO 抓取建议。');
    }
    $successRate = ($summary['status_2xx'] / $summary['visits']) * 100;
    $notFoundRate = ($summary['not_found'] / $summary['visits']) * 100;
    $items[] = '当前范围蜘蛛抓取 ' . $summary['visits'] . ' 次，成功抓取率 '
        . number_format($successRate, 1) . '%。';
    if ($summary['visits'] >= $thresholds['minimum_sample'] && $notFoundRate >= $thresholds['high_404_ratio']) {
        $items[] = '404 比例为 ' . number_format($notFoundRate, 1) . '%（阈值 '
            . $thresholds['high_404_ratio'] . '%），建议检查失效链接或重定向。';
    }
    if ($summary['visits'] >= $thresholds['minimum_sample'] && $summary['avg_ms'] >= $thresholds['high_avg_ms']) {
        $items[] = '平均响应时间为 ' . number_format($summary['avg_ms'], 1) . ' ms（阈值 '
            . $thresholds['high_avg_ms'] . ' ms），建议关注页面响应性能。';
    }
    foreach ($recentActivity as $name => $visits) {
        if ($visits === 0) {
            $items[] = $name . ' 最近 7 天无抓取记录。';
        }
    }
    foreach (array_slice($urls, 0, 3) as $url) {
        $ratio = $url['visits'] > 0 ? ($url['not_found'] / $url['visits']) * 100 : 0;
        if ($url['visits'] >= $thresholds['hot_url_visits'] && $ratio >= $thresholds['hot_url_404_ratio']) {
            $items[] = $url['path'] . ' 被蜘蛛抓取 ' . $url['visits'] . ' 次，其中 404 占 '
                . number_format($ratio, 1) . '%，建议优先处理。';
        }
    }

    return $items;
}

function xz_visit_stats_spider_build($filters)
{
    $range = xz_visit_stats_stats_range($filters);
    $summary = xz_visit_stats_spider_summary($filters, $range);
    $urlCount = xz_visit_stats_spider_url_count($filters, $range);
    $pageAll = max(1, (int) ceil($urlCount / $filters['page_size']));
    $page = min($filters['page'], $pageAll);
    $urls = xz_visit_stats_spider_urls($filters, $range, $page, $filters['page_size']);
    $distribution = xz_visit_stats_spider_distribution($filters, $range);
    $recent = xz_visit_stats_spider_recent_activity();

    return array(
        'range' => $range,
        'summary' => $summary,
        'distribution' => $distribution,
        'trend' => xz_visit_stats_spider_trend($filters, $range),
        'hours' => xz_visit_stats_spider_hours($filters, $range),
        'urls' => $urls,
        'url_count' => $urlCount,
        'page' => $page,
        'page_all' => $pageAll,
        'recent_activity' => $recent,
        'report' => xz_visit_stats_spider_report($summary, $urls, $recent),
    );
}
