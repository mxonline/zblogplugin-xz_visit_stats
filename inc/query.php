<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_query_value($source, $name, $default = '')
{
    if (!is_array($source) || !array_key_exists($name, $source) || is_array($source[$name])) {
        return $default;
    }

    return $source[$name];
}

function xz_visit_stats_query_text($value, $maxLength)
{
    $value = trim(str_replace("\0", '', (string) $value));
    if (function_exists('mb_strcut')) {
        return mb_strcut($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

function xz_visit_stats_query_datetime($value)
{
    $value = xz_visit_stats_query_text($value, 16);
    if ($value === '') {
        return null;
    }

    $date = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    $errors = DateTime::getLastErrors();
    if ($date === false
        || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $date->format('Y-m-d\TH:i') !== $value
    ) {
        return null;
    }

    return $date->getTimestamp();
}

function xz_visit_stats_query_filters($source = null)
{
    if ($source === null) {
        $source = $_GET;
    }

    $ranges = array('all', 'today', 'yesterday', '7d', '30d', 'custom');
    $visitTypes = array('all', 'human', 'bot');
    $statusGroups = array('all', '2xx', '3xx', '4xx', '5xx');
    $ipModes = array('exact', 'prefix');
    $botNames = array(
        '', 'Baiduspider', 'Sogou', '360Spider', 'HaosouSpider', 'Bytespider',
        'PetalBot', 'Googlebot', 'bingbot', 'YandexBot', 'DuckDuckBot', 'Applebot',
    );
    $browsers = array('', 'Chrome', 'Edge', 'Firefox', 'Safari', 'Other');

    $range = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'range', 'all'), 16);
    if (!in_array($range, $ranges, true)) {
        $range = 'all';
    }
    $visitType = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'visit_type', 'all'), 16);
    if (!in_array($visitType, $visitTypes, true)) {
        $visitType = 'all';
    }
    $statusGroup = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'status_group', 'all'), 8);
    if (!in_array($statusGroup, $statusGroups, true)) {
        $statusGroup = 'all';
    }
    $ipMode = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'ip_mode', 'prefix'), 8);
    if (!in_array($ipMode, $ipModes, true)) {
        $ipMode = 'prefix';
    }

    $pageRaw = trim((string) xz_visit_stats_query_value($source, 'page', '1'));
    $page = preg_match('/^[0-9]+$/', $pageRaw) === 1 ? max(1, (int) $pageRaw) : 1;
    $pageSizeInput = trim((string) xz_visit_stats_query_value($source, 'page_size', '50'));
    $pageSizeRaw = preg_match('/^[0-9]+$/', $pageSizeInput) === 1 ? (int) $pageSizeInput : 50;
    if ($pageSizeRaw > 100) {
        $pageSize = 100;
    } elseif (in_array($pageSizeRaw, array(20, 50, 100), true)) {
        $pageSize = $pageSizeRaw;
    } else {
        $pageSize = 50;
    }

    $ip = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'ip', ''), 45);
    if ($ip !== '' && preg_match('/^[0-9a-f:.]+$/i', $ip) !== 1) {
        $ip = '';
    }
    if ($ipMode === 'exact' && $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) === false) {
        $ip = '';
    }

    $statusCodeInput = trim((string) xz_visit_stats_query_value($source, 'status_code', ''));
    $statusCode = preg_match('/^[1-5][0-9]{2}$/', $statusCodeInput) === 1
        ? $statusCodeInput
        : '';
    $botName = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'bot_name', ''), 64);
    if (!in_array($botName, $botNames, true)) {
        $botName = '';
    }
    $browser = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'browser', ''), 32);
    if (!in_array($browser, $browsers, true)) {
        $browser = '';
    }

    return array(
        'range' => $range,
        'start' => xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'start', ''), 16),
        'end' => xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'end', ''), 16),
        'ip' => $ip,
        'ip_mode' => $ipMode,
        'visit_type' => $visitType,
        'status_group' => $statusGroup,
        'status_code' => $statusCode,
        'bot_name' => $botName,
        'browser' => $browser,
        'url' => xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'url', ''), 200),
        'referer' => xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'referer', ''), 200),
        'page' => $page,
        'page_size' => $pageSize,
    );
}

function xz_visit_stats_query_where($filters, $now = null)
{
    if ($now === null) {
        $now = time();
    }

    $where = array();
    $today = strtotime('today', $now);
    if ($filters['range'] === 'today') {
        $where[] = array('>=', 'vs_VisitedAt', $today);
    } elseif ($filters['range'] === 'yesterday') {
        $where[] = array('>=', 'vs_VisitedAt', strtotime('-1 day', $today));
        $where[] = array('<', 'vs_VisitedAt', $today);
    } elseif ($filters['range'] === '7d') {
        $where[] = array('>=', 'vs_VisitedAt', $now - 7 * 86400);
    } elseif ($filters['range'] === '30d') {
        $where[] = array('>=', 'vs_VisitedAt', $now - 30 * 86400);
    } elseif ($filters['range'] === 'custom') {
        $start = xz_visit_stats_query_datetime($filters['start']);
        $end = xz_visit_stats_query_datetime($filters['end']);
        if ($start !== null) {
            $where[] = array('>=', 'vs_VisitedAt', $start);
        }
        if ($end !== null) {
            $where[] = array('<=', 'vs_VisitedAt', $end);
        }
    }

    if ($filters['ip'] !== '') {
        $where[] = $filters['ip_mode'] === 'exact'
            ? array('=', 'vs_IP', $filters['ip'])
            : array('LIKE', 'vs_IP', $filters['ip'] . '%');
    }
    if ($filters['visit_type'] === 'human') {
        $where[] = array('=', 'vs_IsBot', 0);
    } elseif ($filters['visit_type'] === 'bot') {
        $where[] = array('=', 'vs_IsBot', 1);
    }

    if ($filters['status_code'] !== '') {
        $where[] = array('=', 'vs_StatusCode', (int) $filters['status_code']);
    } elseif ($filters['status_group'] !== 'all') {
        $base = (int) substr($filters['status_group'], 0, 1) * 100;
        $where[] = array('>=', 'vs_StatusCode', $base);
        $where[] = array('<', 'vs_StatusCode', $base + 100);
    }

    if ($filters['bot_name'] !== '') {
        $where[] = array('=', 'vs_BotName', $filters['bot_name']);
    }
    if ($filters['browser'] === 'Other') {
        $where[] = array(
            'AND',
            array('<>', 'vs_Browser', 'Chrome'),
            array('<>', 'vs_Browser', 'Edge'),
            array('<>', 'vs_Browser', 'Firefox'),
            array('<>', 'vs_Browser', 'Safari')
        );
    } elseif ($filters['browser'] !== '') {
        $where[] = array('=', 'vs_Browser', $filters['browser']);
    }
    if ($filters['url'] !== '') {
        $where[] = array('SEARCH', 'vs_Path', $filters['url']);
    }
    if ($filters['referer'] !== '') {
        $where[] = array('SEARCH', 'vs_Referer', $filters['referer']);
    }

    return $where;
}

function xz_visit_stats_query_count($filters)
{
    global $zbp;

    $sql = $zbp->db->sql->Count(
        $GLOBALS['table']['xz_visit_stats_log'],
        array(array('COUNT', '*', 'num')),
        xz_visit_stats_query_where($filters)
    );

    return (int) GetValueInArrayByCurrent($zbp->db->Query($sql), 'num');
}

function xz_visit_stats_query_list($filters, $page, $pageSize)
{
    global $zbp;

    $columns = array(
        'vs_ID', 'vs_IP', 'vs_VisitorHash', 'vs_Url', 'vs_Path', 'vs_Referer',
        'vs_UserAgent', 'vs_UaType', 'vs_Browser', 'vs_Os', 'vs_Device',
        'vs_IsBot', 'vs_BotName', 'vs_StatusCode', 'vs_DurationMs', 'vs_VisitedAt',
    );
    $offset = max(0, ($page - 1) * $pageSize);
    $sql = $zbp->db->sql->Select(
        $GLOBALS['table']['xz_visit_stats_log'],
        $columns,
        xz_visit_stats_query_where($filters),
        array('vs_VisitedAt' => 'DESC'),
        array($offset, $pageSize)
    );

    return (array) $zbp->db->Query($sql);
}

function xz_visit_stats_query_url_params($filters)
{
    $keys = array(
        'range', 'start', 'end', 'ip', 'ip_mode', 'visit_type', 'status_group',
        'status_code', 'bot_name', 'browser', 'url', 'referer', 'page_size',
    );
    $params = array('view' => 'records');
    foreach ($keys as $key) {
        if ($filters[$key] !== '' && $filters[$key] !== 'all') {
            $params[$key] = $filters[$key];
        }
    }

    return $params;
}

function xz_visit_stats_query_page($filters)
{
    global $zbp;

    $count = xz_visit_stats_query_count($filters);
    $pageSize = $filters['page_size'];
    $pageAll = max(1, (int) ceil($count / $pageSize));
    $page = min(max(1, $filters['page']), $pageAll);
    $rows = xz_visit_stats_query_list($filters, $page, $pageSize);

    $params = xz_visit_stats_query_url_params($filters);
    $url = $zbp->host . 'zb_users/plugin/xz_visit_stats/main.php?'
        . http_build_query($params, '', '&') . '{&page=%page%}';
    $pageBar = new PageBar($url, false);
    $pageBar->Count = $count;
    $pageBar->PageCount = $pageSize;
    $pageBar->PageNow = $page;
    $pageBar->PageBarCount = $zbp->pagebarcount;
    $pageBar->Make();

    return array(
        'rows' => $rows,
        'count' => $count,
        'page' => $page,
        'page_size' => $pageSize,
        'page_all' => $pageAll,
        'pagebar' => $pageBar,
    );
}
