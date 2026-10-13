<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_v4_events_query_table()
{
    global $zbp;
    return '`' . str_replace('`', '``', $zbp->db->dbpre . 'xz_visit_stats_events') . '`';
}

function xz_visit_stats_v4_event_query_range(array $filters)
{
    $filters = array_merge(array('range' => 'today', 'start' => '', 'end' => ''), $filters);
    return xz_visit_stats_v2_range($filters);
}

function xz_visit_stats_v4_event_name_filter(array $filters)
{
    $name = isset($filters['event_name']) ? (string) $filters['event_name'] : (isset($filters['name']) ? (string) $filters['name'] : '');
    return preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/', $name) === 1 ? $name : '';
}

function xz_visit_stats_v4_event_where(array $filters, array $range)
{
    $where = array('ev_TriggeredAt >= ' . (int) $range['start'], 'ev_TriggeredAt < ' . (int) $range['end']);
    $name = xz_visit_stats_v4_event_name_filter($filters);
    if ($name !== '') $where[] = 'ev_Name = ' . xz_visit_stats_v2_quote($name);
    if (isset($filters['session_id']) && (int) $filters['session_id'] > 0) $where[] = 'ev_SessionID = ' . (int) $filters['session_id'];
    if (isset($filters['visitor_hash']) && preg_match('/^[a-f0-9]{64}$/', (string) $filters['visitor_hash']) === 1) $where[] = 'ev_VisitorHash = ' . xz_visit_stats_v2_quote($filters['visitor_hash']);
    if (isset($filters['path_key']) && preg_match('/^[a-f0-9]{64}$/', (string) $filters['path_key']) === 1) $where[] = 'ev_PathKey = ' . xz_visit_stats_v2_quote($filters['path_key']);
    return $where;
}

function xz_visit_stats_v4_event_cursor($cursor)
{
    if (preg_match('/^([0-9]+):([0-9]+)$/', (string) $cursor, $matches) !== 1) return null;
    return array('triggered_at' => (int) $matches[1], 'id' => (int) $matches[2]);
}

function xz_visit_stats_v4_event_params($value)
{
    $decoded = is_string($value) ? json_decode($value, true) : $value;
    if (!is_array($decoded)) return array();
    $allowed = array('category' => true, 'action' => true, 'label' => true, 'method' => true, 'plan' => true, 'value' => true);
    $result = array();
    foreach ($decoded as $key => $item) {
        if (!isset($allowed[$key]) || is_array($item) || is_object($item)) continue;
        $result[$key] = is_numeric($item) ? 0 + $item : substr((string) $item, 0, 128);
    }
    return $result;
}

function xz_visit_stats_v4_event_public_row(array $row)
{
    $hash = isset($row['ev_VisitorHash']) ? (string) $row['ev_VisitorHash'] : '';
    return array('id' => (int) (isset($row['ev_ID']) ? $row['ev_ID'] : 0), 'session_id' => isset($row['ev_SessionID']) && $row['ev_SessionID'] !== null ? (int) $row['ev_SessionID'] : null, 'visitor_hash' => function_exists('xz_visit_stats_v4_query_mask_hash') ? xz_visit_stats_v4_query_mask_hash($hash) : (strlen($hash) > 16 ? substr($hash, 0, 8) . '…' . substr($hash, -8) : $hash), 'name' => (string) (isset($row['ev_Name']) ? $row['ev_Name'] : ''), 'params' => xz_visit_stats_v4_event_params(isset($row['ev_Params']) ? $row['ev_Params'] : ''), 'path_key' => (string) (isset($row['ev_PathKey']) ? $row['ev_PathKey'] : ''), 'triggered_at' => (int) (isset($row['ev_TriggeredAt']) ? $row['ev_TriggeredAt'] : 0));
}

function xz_visit_stats_v4_event_summary(array $filters, int $limit = 50): array
{
    global $zbp;
    $range = xz_visit_stats_v4_event_query_range($filters);
    $where = implode(' AND ', xz_visit_stats_v4_event_where($filters, $range));
    $table = xz_visit_stats_v4_events_query_table();
    $rows = (array) $zbp->db->Query('SELECT COUNT(*) AS event_total,COUNT(DISTINCT ev_VisitorHash) AS unique_visitors FROM ' . $table . ' WHERE ' . $where);
    $row = !empty($rows) ? $rows[0] : array();
    $total = (int) (isset($row['event_total']) ? $row['event_total'] : 0);
    $visitors = (int) (isset($row['unique_visitors']) ? $row['unique_visitors'] : 0);
    $limit = max(1, min(100, $limit));
    $byName = array();
    foreach ((array) $zbp->db->Query('SELECT ev_Name AS name,COUNT(*) AS triggers,COUNT(DISTINCT ev_VisitorHash) AS unique_visitors FROM ' . $table . ' WHERE ' . $where . ' GROUP BY ev_Name ORDER BY triggers DESC,name ASC LIMIT ' . $limit) as $item) {
        $byName[] = array('name' => (string) (isset($item['name']) ? $item['name'] : ''), 'triggers' => (int) (isset($item['triggers']) ? $item['triggers'] : 0), 'unique_visitors' => (int) (isset($item['unique_visitors']) ? $item['unique_visitors'] : 0));
    }
    return array('event_total' => $total, 'unique_visitors' => $visitors, 'average_per_visitor' => $visitors > 0 ? round($total / $visitors, 2) : null, 'by_name' => $byName);
}

function xz_visit_stats_v4_event_rows(array $filters, string $cursor, int $limit): array
{
    global $zbp;
    $range = xz_visit_stats_v4_event_query_range($filters);
    $where = xz_visit_stats_v4_event_where($filters, $range);
    $decoded = xz_visit_stats_v4_event_cursor($cursor);
    if ($decoded !== null) $where[] = '(ev_TriggeredAt < ' . $decoded['triggered_at'] . ' OR (ev_TriggeredAt = ' . $decoded['triggered_at'] . ' AND ev_ID < ' . $decoded['id'] . '))';
    $limit = max(1, min(100, $limit));
    $sql = 'SELECT ev_ID,ev_SessionID,ev_VisitorHash,ev_Name,ev_Params,ev_PathKey,ev_TriggeredAt FROM ' . xz_visit_stats_v4_events_query_table() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ev_TriggeredAt DESC,ev_ID DESC LIMIT ' . $limit;
    $rows = array_map('xz_visit_stats_v4_event_public_row', (array) $zbp->db->Query($sql));
    $last = !empty($rows) ? $rows[count($rows) - 1] : null;
    return array('rows' => $rows, 'limit' => $limit, 'next_cursor' => $last === null ? '' : $last['triggered_at'] . ':' . $last['id']);
}

function xz_visit_stats_v4_event_detail(int $eventId): ?array
{
    global $zbp;
    if ($eventId <= 0) return null;
    $rows = (array) $zbp->db->Query('SELECT ev_ID,ev_SessionID,ev_VisitorHash,ev_Name,ev_Params,ev_PathKey,ev_TriggeredAt FROM ' . xz_visit_stats_v4_events_query_table() . ' WHERE ev_ID=' . $eventId . ' LIMIT 1');
    return empty($rows) ? null : xz_visit_stats_v4_event_public_row($rows[0]);
}

function xz_visit_stats_v4_event_explain(array $filters): array
{
    global $zbp;
    $range = xz_visit_stats_v4_event_query_range($filters);
    $where = implode(' AND ', xz_visit_stats_v4_event_where($filters, $range));
    return (array) $zbp->db->Query('EXPLAIN SELECT ev_ID FROM ' . xz_visit_stats_v4_events_query_table() . ' WHERE ' . $where . ' ORDER BY ev_TriggeredAt DESC,ev_ID DESC LIMIT 100');
}
