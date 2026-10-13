<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_v4_session_query_table()
{
    return '`' . str_replace('`', '``', xz_visit_stats_v4_session_table()) . '`';
}

function xz_visit_stats_v4_session_pages_query_table()
{
    return '`' . str_replace('`', '``', xz_visit_stats_v4_session_pages_table()) . '`';
}

function xz_visit_stats_v4_query_mask_hash($value)
{
    $value = (string) $value;
    if ($value === '') return '';
    return strlen($value) <= 16 ? $value : substr($value, 0, 8) . '…' . substr($value, -8);
}

function xz_visit_stats_v4_session_query_range(array $filters)
{
    $filters = array_merge(array('range' => 'today', 'start' => '', 'end' => ''), $filters);
    return xz_visit_stats_v2_range($filters);
}

function xz_visit_stats_v4_session_where(array $filters, array $range)
{
    $where = array('se_StartedAt >= ' . (int) $range['start'], 'se_StartedAt < ' . (int) $range['end']);
    if (isset($filters['source_type']) && $filters['source_type'] !== '' && $filters['source_type'] !== 'all') {
        $where[] = 'se_SourceType = ' . xz_visit_stats_v2_quote($filters['source_type']);
    }
    if (isset($filters['source_domain']) && $filters['source_domain'] !== '') {
        $where[] = 'se_SourceDomain = ' . xz_visit_stats_v2_quote($filters['source_domain']);
    }
    if (isset($filters['path_key']) && preg_match('/^[a-f0-9]{64}$/', (string) $filters['path_key']) === 1) {
        $path = xz_visit_stats_v2_quote($filters['path_key']);
        $where[] = '(se_EntryPathKey = ' . $path . ' OR se_ExitPathKey = ' . $path . ')';
    }
    if (isset($filters['new_returning']) && in_array($filters['new_returning'], array('new', 'returning'), true)) {
        $prior = 'EXISTS (SELECT 1 FROM ' . xz_visit_stats_v4_session_query_table() . ' prior WHERE prior.se_VisitorHash=sessions.se_VisitorHash AND prior.se_StartedAt<' . (int) $range['start'] . ' LIMIT 1)';
        $where[] = $filters['new_returning'] === 'new' ? 'NOT ' . $prior : $prior;
    }
    if (!empty($filters['device']) || !empty($filters['country']) || !empty($filters['province']) || !empty($filters['browser']) || !empty($filters['os']) || !empty($filters['ai_source']) || !empty($filters['campaign'])) {
        $visitWhere = implode(' AND ', xz_visit_stats_v3_where($filters, $range));
        $where[] = 'EXISTS (SELECT 1 FROM ' . xz_visit_stats_v4_session_pages_query_table() . ' session_pages INNER JOIN ' . xz_visit_stats_v2_table() . ' visit_logs ON visit_logs.vs_ID=session_pages.sp_LogID WHERE session_pages.sp_SessionID=sessions.se_ID AND ' . $visitWhere . ')';
    }
    return $where;
}

function xz_visit_stats_v4_session_cursor($cursor)
{
    if (preg_match('/^([0-9]+):([0-9]+)$/', (string) $cursor, $matches) !== 1) return null;
    return array('started_at' => (int) $matches[1], 'id' => (int) $matches[2]);
}

function xz_visit_stats_v4_session_limit($limit)
{
    return max(1, min(100, (int) $limit));
}

function xz_visit_stats_v4_session_select()
{
    // se_SessionKey is deliberately excluded: it is an internal write-side key.
    return 'se_ID,se_VisitorHash,se_StartedAt,se_LastSeenAt,se_EntryPathKey,se_ExitPathKey,se_PageCount,se_DurationMs,se_IsBounce,se_SourceType,se_SourceDomain';
}

function xz_visit_stats_v4_session_public_row(array $row)
{
    return array(
        'id' => (int) (isset($row['se_ID']) ? $row['se_ID'] : 0),
        'visitor_hash' => xz_visit_stats_v4_query_mask_hash(isset($row['se_VisitorHash']) ? $row['se_VisitorHash'] : ''),
        'started_at' => (int) (isset($row['se_StartedAt']) ? $row['se_StartedAt'] : 0),
        'last_seen_at' => (int) (isset($row['se_LastSeenAt']) ? $row['se_LastSeenAt'] : 0),
        'entry_path_key' => (string) (isset($row['se_EntryPathKey']) ? $row['se_EntryPathKey'] : ''),
        'exit_path_key' => (string) (isset($row['se_ExitPathKey']) ? $row['se_ExitPathKey'] : ''),
        'page_count' => (int) (isset($row['se_PageCount']) ? $row['se_PageCount'] : 0),
        'duration_ms' => isset($row['se_DurationMs']) && $row['se_DurationMs'] !== null ? (int) $row['se_DurationMs'] : null,
        'is_bounce' => (int) (isset($row['se_IsBounce']) ? $row['se_IsBounce'] : 0),
        'source_type' => (string) (isset($row['se_SourceType']) ? $row['se_SourceType'] : ''),
        'source_domain' => (string) (isset($row['se_SourceDomain']) ? $row['se_SourceDomain'] : ''),
    );
}

function xz_visit_stats_v4_session_summary(array $filters): array
{
    global $zbp;
    $range = xz_visit_stats_v4_session_query_range($filters);
    $where = implode(' AND ', xz_visit_stats_v4_session_where($filters, $range));
    $completedAt = max(0, time() - xz_visit_stats_v4_session_timeout());
    $sql = 'SELECT COUNT(*) AS session_count,COUNT(DISTINCT se_VisitorHash) AS unique_visitors,AVG(se_PageCount) AS average_page_depth,'
        . 'SUM(se_LastSeenAt<' . $completedAt . ') AS completed_sessions,'
        . 'SUM(se_LastSeenAt<' . $completedAt . ' AND se_IsBounce=1) AS bounce_sessions,'
        . 'COUNT(se_DurationMs) AS dwell_samples,COALESCE(SUM(se_DurationMs),0) AS dwell_sum_ms'
        . ' FROM ' . xz_visit_stats_v4_session_query_table() . ' sessions WHERE ' . $where;
    $rows = (array) $zbp->db->Query($sql);
    $row = !empty($rows) ? $rows[0] : array();
    $result = array(
        'session_count' => (int) (isset($row['session_count']) ? $row['session_count'] : 0),
        'unique_visitors' => (int) (isset($row['unique_visitors']) ? $row['unique_visitors'] : 0),
        'average_page_depth' => isset($row['average_page_depth']) && $row['average_page_depth'] !== null ? round((float) $row['average_page_depth'], 2) : null,
        'completed_sessions' => (int) (isset($row['completed_sessions']) ? $row['completed_sessions'] : 0),
        'bounce_sessions' => (int) (isset($row['bounce_sessions']) ? $row['bounce_sessions'] : 0),
        'dwell_samples' => (int) (isset($row['dwell_samples']) ? $row['dwell_samples'] : 0),
        'dwell_sum_ms' => (int) (isset($row['dwell_sum_ms']) ? $row['dwell_sum_ms'] : 0),
    );
    $result['bounce_rate'] = $result['completed_sessions'] > 0 ? round(($result['bounce_sessions'] * 100) / $result['completed_sessions'], 2) : null;
    $result['average_dwell_ms'] = $result['dwell_samples'] > 0 ? round($result['dwell_sum_ms'] / $result['dwell_samples'], 2) : null;
    $coverage = array(
        'total_sessions' => $result['session_count'],
        'lifecycle_sessions' => $result['completed_sessions'],
        // There is no trustworthy marker for pre-v4 history in the schema.
        // Callers may assert a known fully-v4 window explicitly; otherwise be conservative.
        'range_complete' => isset($filters['lifecycle_range_complete']) && $filters['lifecycle_range_complete'] === true,
    );
    $result['availability'] = function_exists('xz_visit_stats_v4_report_availability')
        ? xz_visit_stats_v4_report_availability($filters, $coverage)
        : array('status' => $result['completed_sessions'] > 0 ? 'partial' : 'unavailable', 'total_sessions' => $result['session_count'], 'lifecycle_sessions' => $result['completed_sessions'], 'coverage_ratio' => $result['session_count'] > 0 ? $result['completed_sessions'] / $result['session_count'] : null, 'reason' => 'historical_coverage');
    return $result;
}

function xz_visit_stats_v4_session_trend(array $filters, string $grain): array
{
    global $zbp;
    $grain = in_array($grain, array('hour', 'day', 'week', 'month'), true) ? $grain : 'day';
    $formats = array('hour' => '%Y-%m-%d %H:00', 'day' => '%Y-%m-%d', 'week' => '%x-W%v', 'month' => '%Y-%m');
    $range = xz_visit_stats_v4_session_query_range($filters);
    $where = implode(' AND ', xz_visit_stats_v4_session_where($filters, $range));
    $completedAt = max(0, time() - xz_visit_stats_v4_session_timeout());
    $sql = 'SELECT DATE_FORMAT(FROM_UNIXTIME(se_StartedAt),' . xz_visit_stats_v2_quote($formats[$grain]) . ') AS trend_bucket,'
        . 'COUNT(*) AS session_count,COUNT(DISTINCT se_VisitorHash) AS unique_visitors,'
        . 'SUM(se_LastSeenAt<' . $completedAt . ' AND se_IsBounce=1) AS bounce_sessions,'
        . 'COUNT(se_DurationMs) AS dwell_samples,COALESCE(SUM(se_DurationMs),0) AS dwell_sum_ms'
        . ' FROM ' . xz_visit_stats_v4_session_query_table() . ' sessions WHERE ' . $where
        . ' GROUP BY trend_bucket ORDER BY trend_bucket ASC LIMIT ' . ($grain === 'hour' ? 744 : 366);
    $result = array();
    foreach ((array) $zbp->db->Query($sql) as $row) {
        $samples = (int) (isset($row['dwell_samples']) ? $row['dwell_samples'] : 0);
        $sum = (int) (isset($row['dwell_sum_ms']) ? $row['dwell_sum_ms'] : 0);
        $result[] = array('bucket' => (string) (isset($row['trend_bucket']) ? $row['trend_bucket'] : ''), 'session_count' => (int) (isset($row['session_count']) ? $row['session_count'] : 0), 'unique_visitors' => (int) (isset($row['unique_visitors']) ? $row['unique_visitors'] : 0), 'bounce_sessions' => (int) (isset($row['bounce_sessions']) ? $row['bounce_sessions'] : 0), 'dwell_samples' => $samples, 'average_dwell_ms' => $samples > 0 ? round($sum / $samples, 2) : null);
    }
    return $result;
}

function xz_visit_stats_v4_session_rows(array $filters, string $cursor, int $limit): array
{
    global $zbp;
    $range = xz_visit_stats_v4_session_query_range($filters);
    $where = xz_visit_stats_v4_session_where($filters, $range);
    $decoded = xz_visit_stats_v4_session_cursor($cursor);
    if ($decoded !== null) $where[] = '(se_StartedAt < ' . $decoded['started_at'] . ' OR (se_StartedAt = ' . $decoded['started_at'] . ' AND se_ID < ' . $decoded['id'] . '))';
    $limit = xz_visit_stats_v4_session_limit($limit);
    $sql = 'SELECT ' . xz_visit_stats_v4_session_select() . ' FROM ' . xz_visit_stats_v4_session_query_table() . ' sessions WHERE ' . implode(' AND ', $where) . ' ORDER BY se_StartedAt DESC,se_ID DESC LIMIT ' . $limit;
    $rows = array_map('xz_visit_stats_v4_session_public_row', (array) $zbp->db->Query($sql));
    $last = !empty($rows) ? $rows[count($rows) - 1] : null;
    return array('rows' => $rows, 'limit' => $limit, 'next_cursor' => $last === null ? '' : $last['started_at'] . ':' . $last['id']);
}

function xz_visit_stats_v4_session_detail(int $sessionId): ?array
{
    global $zbp;
    if ($sessionId <= 0) return null;
    $rows = (array) $zbp->db->Query('SELECT ' . xz_visit_stats_v4_session_select() . ' FROM ' . xz_visit_stats_v4_session_query_table() . ' WHERE se_ID=' . $sessionId . ' LIMIT 1');
    if (empty($rows)) return null;
    $detail = xz_visit_stats_v4_session_public_row($rows[0]);
    $pages = array();
    foreach ((array) $zbp->db->Query('SELECT sp_ID,sp_Sequence,sp_PathKey,sp_Path,sp_EnteredAt,sp_LeftAt,sp_DurationMs,sp_ExitReason FROM ' . xz_visit_stats_v4_session_pages_query_table() . ' WHERE sp_SessionID=' . $sessionId . ' ORDER BY sp_Sequence ASC,sp_ID ASC') as $page) {
        $pages[] = array('id' => (int) (isset($page['sp_ID']) ? $page['sp_ID'] : 0), 'sequence' => (int) (isset($page['sp_Sequence']) ? $page['sp_Sequence'] : 0), 'path_key' => (string) (isset($page['sp_PathKey']) ? $page['sp_PathKey'] : ''), 'path' => (string) (isset($page['sp_Path']) ? $page['sp_Path'] : ''), 'entered_at' => (int) (isset($page['sp_EnteredAt']) ? $page['sp_EnteredAt'] : 0), 'left_at' => isset($page['sp_LeftAt']) && $page['sp_LeftAt'] !== null ? (int) $page['sp_LeftAt'] : null, 'duration_ms' => isset($page['sp_DurationMs']) && $page['sp_DurationMs'] !== null ? (int) $page['sp_DurationMs'] : null, 'exit_reason' => (string) (isset($page['sp_ExitReason']) ? $page['sp_ExitReason'] : ''));
    }
    $detail['pages'] = $pages;
    return $detail;
}

function xz_visit_stats_v4_session_explain(array $filters): array
{
    global $zbp;
    $range = xz_visit_stats_v4_session_query_range($filters);
    $where = implode(' AND ', xz_visit_stats_v4_session_where($filters, $range));
    return (array) $zbp->db->Query('EXPLAIN SELECT se_ID FROM ' . xz_visit_stats_v4_session_query_table() . ' sessions WHERE ' . $where . ' ORDER BY se_StartedAt DESC,se_ID DESC LIMIT 100');
}
