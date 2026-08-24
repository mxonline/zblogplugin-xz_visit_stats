<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_normalize_path($path)
{
    $path = trim((string) $path);
    if ($path === '') {
        return '/';
    }

    $parsed = parse_url($path, PHP_URL_PATH);
    if (is_string($parsed) && $parsed !== '') {
        $path = $parsed;
    }
    if ($path === '' || $path === '/') {
        return '/';
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    return rtrim($path, '/') ?: '/';
}

function xz_visit_stats_path_key($path)
{
    return hash('sha256', xz_visit_stats_normalize_path($path));
}

function xz_visit_stats_rollup_table()
{
    global $zbp;

    return $zbp->db->dbpre . 'xz_visit_stats_rollup_daily';
}

function xz_visit_stats_rollup_state_table()
{
    global $zbp;

    return $zbp->db->dbpre . 'xz_visit_stats_rollup_state';
}

function xz_visit_stats_rollup_timezone()
{
    global $zbp;

    $timezone = isset($zbp->option['ZC_TIME_ZONE']) ? (string) $zbp->option['ZC_TIME_ZONE'] : '';
    if ($timezone === '') {
        $timezone = date_default_timezone_get();
    }
    try {
        new DateTimeZone($timezone);
    } catch (Exception $exception) {
        $timezone = date_default_timezone_get();
    }

    return $timezone;
}

function xz_visit_stats_rollup_day_bounds($day, $timezone = '')
{
    $timezone = $timezone !== '' ? $timezone : xz_visit_stats_rollup_timezone();
    try {
        $zone = new DateTimeZone($timezone);
    } catch (Exception $exception) {
        $zone = new DateTimeZone(date_default_timezone_get());
    }
    $start = new DateTime($day . ' 00:00:00', $zone);
    $end = clone $start;
    $end->modify('+1 day');

    return array($start->getTimestamp(), $end->getTimestamp(), $start->format('Y-m-d'));
}

function xz_visit_stats_rollup_source_type($referer)
{
    $referer = trim((string) $referer);
    if ($referer === '') {
        return 'direct';
    }
    $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
    if ($host === '') {
        return 'other';
    }
    $siteHosts = array('localhost', '127.0.0.1', 'xinzhao.net', 'www.xinzhao.net');
    foreach ($siteHosts as $siteHost) {
        if ($host === $siteHost || substr($host, -strlen('.' . $siteHost)) === '.' . $siteHost) {
            return 'internal';
        }
    }
    foreach (array('baidu.', 'bing.com', 'google.', 'so.com', 'sogou.com', 'yandex.', 'sm.cn', 'duckduckgo.com') as $needle) {
        if (strpos($host, $needle) !== false) {
            return 'search';
        }
    }
    foreach (array('weixin.', 'wechat.com', 'weibo.com', 'douban.com', 'zhihu.com', 'twitter.com', 'x.com', 'facebook.com', 'youtube.com', 't.co') as $needle) {
        if (strpos($host, $needle) !== false) {
            return 'social';
        }
    }

    return 'external';
}

function xz_visit_stats_rollup_dimension_key($dimension, $row)
{
    if ($dimension === 'site') {
        return 'all';
    }
    if ($dimension === 'path') {
        return xz_visit_stats_normalize_path(isset($row['vs_Path']) ? $row['vs_Path'] : '/');
    }
    if ($dimension === 'source_type') {
        return xz_visit_stats_rollup_source_type(isset($row['vs_Referer']) ? $row['vs_Referer'] : '');
    }
    if ($dimension === 'source_domain') {
        $host = strtolower((string) parse_url(isset($row['vs_Referer']) ? $row['vs_Referer'] : '', PHP_URL_HOST));
        return $host !== '' ? substr($host, 0, 512) : '(direct)';
    }
    if ($dimension === 'bot') {
        return isset($row['vs_BotName']) && trim((string) $row['vs_BotName']) !== '' ? trim((string) $row['vs_BotName']) : 'Other Bot';
    }
    if ($dimension === 'status') {
        return (string) (int) (isset($row['vs_StatusCode']) ? $row['vs_StatusCode'] : 0);
    }

    return 'other';
}

function xz_visit_stats_rollup_add_row(&$groups, $dimension, $key, $row)
{
    $groupKey = $dimension . ':' . hash('sha256', $key);
    if (!isset($groups[$groupKey])) {
        $groups[$groupKey] = array(
            'day' => '', 'dimension' => $dimension, 'key_hash' => hash('sha256', $key), 'key' => $key,
            'visitor_pv' => 0, 'visitor_uv' => array(), 'visitor_ip' => array(), 'bot_pv' => 0,
            'error_4xx' => 0, 'error_5xx' => 0, 'duration_sum' => 0, 'duration_count' => 0,
            'last_visit_at' => 0,
        );
    }
    $group =& $groups[$groupKey];
    $isBot = (int) (isset($row['vs_IsBot']) ? $row['vs_IsBot'] : 0) === 1;
    if ($isBot) {
        $group['bot_pv']++;
    } else {
        $group['visitor_pv']++;
        $visitorHash = (string) (isset($row['vs_VisitorHash']) ? $row['vs_VisitorHash'] : '');
        $ip = (string) (isset($row['vs_IP']) ? $row['vs_IP'] : '');
        if ($visitorHash !== '') {
            $group['visitor_uv'][$visitorHash] = true;
        }
        if ($ip !== '') {
            $group['visitor_ip'][$ip] = true;
        }
    }
    $status = (int) (isset($row['vs_StatusCode']) ? $row['vs_StatusCode'] : 0);
    if ($status >= 400 && $status <= 499) {
        $group['error_4xx']++;
    }
    if ($status >= 500 && $status <= 599) {
        $group['error_5xx']++;
    }
    $group['duration_sum'] += max(0, (int) (isset($row['vs_DurationMs']) ? $row['vs_DurationMs'] : 0));
    $group['duration_count']++;
    $group['last_visit_at'] = max($group['last_visit_at'], (int) (isset($row['vs_VisitedAt']) ? $row['vs_VisitedAt'] : 0));
    unset($group);
}

function xz_visit_stats_rollup_escape($value)
{
    global $zbp;

    if (method_exists($zbp->db, 'EscapeString')) {
        return $zbp->db->EscapeString((string) $value);
    }
    return addslashes((string) $value);
}

function xz_visit_stats_rollup_state($name = 'daily')
{
    global $zbp;

    $table = xz_visit_stats_rollup_state_table();
    $safeName = xz_visit_stats_rollup_escape($name);
    $rows = (array) $zbp->db->Query("SELECT * FROM `{$table}` WHERE rs_Name='{$safeName}' LIMIT 1");
    return !empty($rows) ? $rows[0] : array();
}

function xz_visit_stats_rollup_save_state($state)
{
    global $zbp;

    $table = xz_visit_stats_rollup_state_table();
    $name = xz_visit_stats_rollup_escape(isset($state['rs_Name']) ? $state['rs_Name'] : 'daily');
    $fields = array(
        'rs_LastCompletedDay' => isset($state['rs_LastCompletedDay']) ? $state['rs_LastCompletedDay'] : '',
        'rs_BackfillDay' => isset($state['rs_BackfillDay']) ? $state['rs_BackfillDay'] : '',
        'rs_BackfillCursor' => (int) (isset($state['rs_BackfillCursor']) ? $state['rs_BackfillCursor'] : 0),
        'rs_Timezone' => isset($state['rs_Timezone']) ? $state['rs_Timezone'] : xz_visit_stats_rollup_timezone(),
        'rs_LastRunAt' => (int) (isset($state['rs_LastRunAt']) ? $state['rs_LastRunAt'] : time()),
        'rs_Status' => isset($state['rs_Status']) ? $state['rs_Status'] : 'idle',
        'rs_LastError' => isset($state['rs_LastError']) ? $state['rs_LastError'] : '',
        'rs_UpdatedAt' => time(),
    );
    $values = array();
    foreach ($fields as $value) {
        $values[] = is_int($value) ? (string) $value : "'" . xz_visit_stats_rollup_escape($value) . "'";
    }
    $columns = implode(',', array_keys($fields));
    $sql = "INSERT INTO `{$table}` (rs_Name,{$columns}) VALUES ('{$name}'," . implode(',', $values) . ")"
        . " ON DUPLICATE KEY UPDATE " . implode(',', array_map(function ($column) { return $column . '=VALUES(' . $column . ')'; }, array_keys($fields)));
    $zbp->db->Query($sql);
}

function xz_visit_stats_rollup_backfill_path_keys($limit = 200, $stopAfter = 0)
{
    global $zbp;

    $limit = max(1, min(1000, (int) $limit));
    $table = xz_visit_stats_physical_table();
    $state = xz_visit_stats_rollup_state('pathkey');
    $cursor = (int) (isset($state['rs_BackfillCursor']) ? $state['rs_BackfillCursor'] : 0);
    $processed = 0;
    while ($processed < $limit) {
        $take = min(50, $limit - $processed);
        $rows = (array) $zbp->db->Query("SELECT vs_ID,vs_Path,vs_PathKey FROM `{$table}` WHERE vs_ID>{$cursor} ORDER BY vs_ID ASC LIMIT {$take}");
        if (empty($rows)) {
            xz_visit_stats_rollup_save_state(array('rs_Name' => 'pathkey', 'rs_BackfillCursor' => $cursor, 'rs_Status' => 'complete', 'rs_LastError' => ''));
            return array('processed' => $processed, 'cursor' => $cursor, 'complete' => true);
        }
        foreach ($rows as $row) {
            $id = (int) $row['vs_ID'];
            $cursor = max($cursor, $id);
            if ((string) (isset($row['vs_PathKey']) ? $row['vs_PathKey'] : '') === '') {
                $key = xz_visit_stats_path_key(isset($row['vs_Path']) ? $row['vs_Path'] : '/');
                $zbp->db->Query("UPDATE `{$table}` SET vs_PathKey='{$key}' WHERE vs_ID={$id} AND (vs_PathKey='' OR vs_PathKey IS NULL)");
            }
            $processed++;
            if ($stopAfter > 0 && $processed >= $stopAfter) {
                xz_visit_stats_rollup_save_state(array('rs_Name' => 'pathkey', 'rs_BackfillCursor' => $cursor, 'rs_Status' => 'paused', 'rs_LastError' => ''));
                return array('processed' => $processed, 'cursor' => $cursor, 'complete' => false);
            }
        }
        xz_visit_stats_rollup_save_state(array('rs_Name' => 'pathkey', 'rs_BackfillCursor' => $cursor, 'rs_Status' => 'running', 'rs_LastError' => ''));
    }
    xz_visit_stats_rollup_save_state(array('rs_Name' => 'pathkey', 'rs_BackfillCursor' => $cursor, 'rs_Status' => 'paused', 'rs_LastError' => ''));
    return array('processed' => $processed, 'cursor' => $cursor, 'complete' => false);
}

function xz_visit_stats_rollup_build_day($day, $batchSize = 500)
{
    global $zbp;

    list($start, $end, $normalizedDay) = xz_visit_stats_rollup_day_bounds($day);
    $table = xz_visit_stats_physical_table();
    $groups = array();
    $lastId = 0;
    $batchSize = max(50, min(2000, (int) $batchSize));
    do {
        $rows = (array) $zbp->db->Query("SELECT * FROM `{$table}` WHERE vs_VisitedAt>={$start} AND vs_VisitedAt<{$end} AND vs_ID>{$lastId} ORDER BY vs_ID ASC LIMIT {$batchSize}");
        foreach ($rows as $row) {
            $lastId = max($lastId, (int) $row['vs_ID']);
            foreach (array('site', 'path', 'source_type', 'source_domain', 'status') as $dimension) {
                xz_visit_stats_rollup_add_row($groups, $dimension, xz_visit_stats_rollup_dimension_key($dimension, $row), $row);
            }
            if ((int) $row['vs_IsBot'] === 1) {
                xz_visit_stats_rollup_add_row($groups, 'bot', xz_visit_stats_rollup_dimension_key('bot', $row), $row);
            }
        }
    } while (!empty($rows));

    $rollupTable = xz_visit_stats_rollup_table();
    $zbp->db->Query("DELETE FROM `{$rollupTable}` WHERE rd_Day='" . xz_visit_stats_rollup_escape($normalizedDay) . "'");
    foreach ($groups as $group) {
        $values = array(
            "'" . xz_visit_stats_rollup_escape($normalizedDay) . "'",
            "'" . xz_visit_stats_rollup_escape($group['dimension']) . "'",
            "'" . xz_visit_stats_rollup_escape($group['key_hash']) . "'",
            "'" . xz_visit_stats_rollup_escape(substr($group['key'], 0, 512)) . "'",
            (string) $group['visitor_pv'], (string) count($group['visitor_uv']), (string) count($group['visitor_ip']),
            (string) $group['bot_pv'], (string) $group['error_4xx'], (string) $group['error_5xx'],
            (string) $group['duration_sum'], (string) $group['duration_count'], (string) $group['last_visit_at'], (string) time(),
        );
        $zbp->db->Query("INSERT INTO `{$rollupTable}` (rd_Day,rd_Dimension,rd_KeyHash,rd_Key,rd_VisitorPV,rd_VisitorUV,rd_VisitorIP,rd_BotPV,rd_Error4xx,rd_Error5xx,rd_DurationSum,rd_DurationCount,rd_LastVisitAt,rd_UpdatedAt) VALUES (" . implode(',', $values) . ")");
    }
    xz_visit_stats_rollup_save_state(array('rs_Name' => 'daily', 'rs_LastCompletedDay' => $normalizedDay, 'rs_Timezone' => xz_visit_stats_rollup_timezone(), 'rs_Status' => 'complete', 'rs_LastError' => ''));
    return array('day' => $normalizedDay, 'groups' => count($groups), 'rows' => array_sum(array_map(function ($group) { return $group['visitor_pv'] + $group['bot_pv']; }, $groups)));
}

function xz_visit_stats_rollup_rebuild($startDay, $endDay)
{
    $start = new DateTime($startDay);
    $end = new DateTime($endDay);
    $result = array();
    while ($start <= $end) {
        $result[] = xz_visit_stats_rollup_build_day($start->format('Y-m-d'));
        $start->modify('+1 day');
    }

    return $result;
}
