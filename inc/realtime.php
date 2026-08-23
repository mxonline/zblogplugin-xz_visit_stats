<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_realtime_filters($source = null)
{
    if ($source === null) {
        $source = $_GET;
    }
    $raw = trim((string) xz_visit_stats_query_value($source, 'limit', '50'));
    $limit = preg_match('/^[0-9]+$/', $raw) === 1 ? (int) $raw : 50;
    if (!in_array($limit, array(20, 50, 100), true)) {
        $limit = 50;
    }

    return array('limit' => $limit);
}

function xz_visit_stats_realtime_rows($limit)
{
    global $zbp;

    $safeLimit = in_array((int) $limit, array(20, 50, 100), true) ? (int) $limit : 50;
    $sql = 'SELECT vs_IP AS ip, vs_Path AS path, vs_IsBot AS is_bot, vs_Browser AS browser,'
        . ' vs_BotName AS bot_name, vs_StatusCode AS status_code, vs_VisitedAt AS visited_at'
        . ' FROM ' . xz_visit_stats_stats_table()
        . ' ORDER BY vs_VisitedAt DESC, vs_ID DESC LIMIT ' . $safeLimit;
    $rows = (array) $zbp->db->Query($sql);
    foreach ($rows as &$row) {
        $row['ip'] = isset($row['ip']) ? (string) $row['ip'] : '';
        $row['path'] = isset($row['path']) ? (string) $row['path'] : '';
        $row['browser'] = isset($row['browser']) ? (string) $row['browser'] : '';
        $row['bot_name'] = isset($row['bot_name']) ? (string) $row['bot_name'] : '';
        $row['is_bot'] = isset($row['is_bot']) ? (int) $row['is_bot'] : 0;
        $row['status_code'] = isset($row['status_code']) ? (int) $row['status_code'] : 0;
        $row['visited_at'] = isset($row['visited_at']) ? (int) $row['visited_at'] : 0;
    }
    unset($row);

    return $rows;
}

function xz_visit_stats_realtime_payload($filters)
{
    return array(
        'rows' => xz_visit_stats_realtime_rows($filters['limit']),
        'generated_at' => time(),
        'limit' => $filters['limit'],
    );
}
