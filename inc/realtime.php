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

function xz_visit_stats_v4_realtime_session_rows(int $minutes = 15, int $limit = 50, ?int $now = null, string $ipMode = 'full'): array
{
    global $zbp;

    $minutes = in_array($minutes, array(5, 15, 30), true) ? $minutes : 15;
    $limit = function_exists('xz_visit_stats_v4_session_limit')
        ? xz_visit_stats_v4_session_limit($limit)
        : max(1, min(100, $limit));
    $now = $now === null ? time() : max(0, $now);
    $cutoff = max(0, $now - ($minutes * 60));
    $sessions = '`' . str_replace('`', '``', xz_visit_stats_v4_session_table()) . '`';
    $pages = '`' . str_replace('`', '``', xz_visit_stats_v4_session_pages_table()) . '`';
    $visits = xz_visit_stats_stats_table();
    $sql = 'SELECT s.se_ID AS session_id,s.se_VisitorHash AS visitor_hash,'
        . 's.se_StartedAt AS started_at,s.se_LastSeenAt AS last_seen_at,s.se_PageCount AS page_count,'
        . 's.se_DurationMs AS visitor_dwell_ms,s.se_IsBounce AS is_bounce,'
        . 's.se_SourceType AS source_type,s.se_SourceDomain AS source_domain,'
        . 'entry.sp_Path AS entry_path,latest.sp_Path AS latest_path,'
        . "COALESCE(v.vs_Browser,'') AS browser,COALESCE(v.vs_Device,'') AS device,"
        . "COALESCE(v.vs_GeoCountry,'') AS country,COALESCE(v.vs_GeoRegion,'') AS region"
        . ' FROM ' . $sessions . ' s'
        . ' LEFT JOIN ' . $pages . ' entry ON entry.sp_SessionID=s.se_ID AND entry.sp_Sequence=1'
        . ' LEFT JOIN ' . $pages . ' latest ON latest.sp_SessionID=s.se_ID AND latest.sp_Sequence=s.se_PageCount'
        . ' LEFT JOIN ' . $visits . ' v ON v.vs_ID=latest.sp_LogID'
        . ' WHERE s.se_LastSeenAt >= ' . $cutoff . ' AND s.se_LastSeenAt <= ' . $now
        . ' ORDER BY s.se_LastSeenAt DESC,s.se_ID DESC LIMIT ' . $limit;

    $rows = array();
    foreach ((array) $zbp->db->Query($sql) as $row) {
        $country = $ipMode === 'masked' ? '' : trim((string) (isset($row['country']) ? $row['country'] : ''));
        $region = $ipMode === 'masked' ? '' : trim((string) (isset($row['region']) ? $row['region'] : ''));
        $rows[] = array(
            'id' => (int) (isset($row['session_id']) ? $row['session_id'] : 0),
            'visitor_hash' => function_exists('xz_visit_stats_v4_query_mask_hash')
                ? xz_visit_stats_v4_query_mask_hash(isset($row['visitor_hash']) ? $row['visitor_hash'] : '')
                : '',
            'started_at' => (int) (isset($row['started_at']) ? $row['started_at'] : 0),
            'last_seen_at' => (int) (isset($row['last_seen_at']) ? $row['last_seen_at'] : 0),
            'page_count' => (int) (isset($row['page_count']) ? $row['page_count'] : 0),
            'visitor_dwell_ms' => isset($row['visitor_dwell_ms']) && $row['visitor_dwell_ms'] !== null ? (int) $row['visitor_dwell_ms'] : null,
            'is_bounce' => (int) (isset($row['is_bounce']) ? $row['is_bounce'] : 0),
            'source_type' => (string) (isset($row['source_type']) ? $row['source_type'] : ''),
            'source_domain' => (string) (isset($row['source_domain']) ? $row['source_domain'] : ''),
            'entry_path' => (string) (isset($row['entry_path']) ? $row['entry_path'] : ''),
            'latest_path' => (string) (isset($row['latest_path']) ? $row['latest_path'] : ''),
            'browser' => (string) (isset($row['browser']) ? $row['browser'] : ''),
            'device' => (string) (isset($row['device']) ? $row['device'] : ''),
            'country' => $country,
            'region' => $region,
        );
    }

    return array('window_minutes' => $minutes, 'rows' => $rows, 'limit' => $limit, 'generated_at' => $now);
}
