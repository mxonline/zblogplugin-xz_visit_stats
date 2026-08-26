<?php

if (!defined('ZBP_PATH')) exit('Access denied');

function xz_visit_stats_v4_session_timeout()
{
    return 1800;
}

function xz_visit_stats_v4_session_bounce_value($pageCount, $lastSeenAt, $now, $timeout = 1800)
{
    if ((int) $now - (int) $lastSeenAt <= max(1, (int) $timeout)) return null;
    return (int) $pageCount === 1 ? 1 : 0;
}

function xz_visit_stats_v4_finalize_expired_bounces($now = null)
{
    global $zbp;
    $now = $now === null ? time() : (int) $now;
    $cutoff = max(0, $now - xz_visit_stats_v4_session_timeout());
    $table = xz_visit_stats_v4_session_table();
    $zbp->db->Query('UPDATE `' . $table . '` SET se_IsBounce=1,se_UpdatedAt=' . $now . ' WHERE se_PageCount=1 AND se_IsBounce=0 AND se_LastSeenAt<' . $cutoff);
}

function xz_visit_stats_v4_session_decide($previous, $visitorHash, $secret, $now, $timeout = 1800, $preferredKey = '')
{
    $previous = is_array($previous) ? $previous : array();
    $now = max(0, (int) $now);
    $sameVisitor = isset($previous['visitor_hash']) && hash_equals((string) $previous['visitor_hash'], (string) $visitorHash);
    $withinWindow = $sameVisitor && isset($previous['last_seen_at']) && $now >= (int) $previous['last_seen_at'] && ($now - (int) $previous['last_seen_at']) <= max(1, (int) $timeout);
    if ($withinWindow) {
        if (preg_match('/^[a-f0-9]{64}$/', (string) $preferredKey)) $previous['session_key'] = (string) $preferredKey;
        $previous['last_seen_at'] = $now;
        $previous['page_count'] = max(0, (int) $previous['page_count']) + 1;
        $previous['sequence'] = max(0, (int) $previous['sequence']) + 1;
        return $previous;
    }
    $sessionKey = preg_match('/^[a-f0-9]{64}$/', (string) $preferredKey) ? (string) $preferredKey : hash_hmac('sha256', (string) $visitorHash . '|' . $now . '|' . bin2hex(random_bytes(16)), (string) $secret);
    return array('session_key' => $sessionKey, 'visitor_hash' => (string) $visitorHash, 'started_at' => $now, 'last_seen_at' => $now, 'page_count' => 1, 'sequence' => 1);
}

function xz_visit_stats_v4_session_table()
{
    global $zbp;
    return $zbp->db->dbpre . 'xz_visit_stats_sessions';
}

function xz_visit_stats_v4_session_pages_table()
{
    global $zbp;
    return $zbp->db->dbpre . 'xz_visit_stats_session_pages';
}

function xz_visit_stats_v4_sql($value)
{
    return "'" . str_replace("'", "''", (string) $value) . "'";
}

function xz_visit_stats_v4_track_page($visit)
{
    global $zbp;
    $now = isset($visit['visited_at']) ? (int) $visit['visited_at'] : time();
    xz_visit_stats_v4_finalize_expired_bounces($now);
    $visitor = isset($visit['visitor_hash']) ? (string) $visit['visitor_hash'] : '';
    if (!preg_match('/^[a-f0-9]{64}$/', $visitor)) return null;
    $sessions = xz_visit_stats_v4_session_table();
    $pages = xz_visit_stats_v4_session_pages_table();
    $last = (array) $zbp->db->Query('SELECT * FROM `' . $sessions . '` WHERE se_VisitorHash=' . xz_visit_stats_v4_sql($visitor) . ' ORDER BY se_LastSeenAt DESC,se_ID DESC LIMIT 1');
    $previous = !empty($last) ? array('session_key' => $last[0]['se_SessionKey'], 'visitor_hash' => $last[0]['se_VisitorHash'], 'started_at' => (int) $last[0]['se_StartedAt'], 'last_seen_at' => (int) $last[0]['se_LastSeenAt'], 'page_count' => (int) $last[0]['se_PageCount'], 'sequence' => (int) $last[0]['se_PageCount'], 'id' => (int) $last[0]['se_ID']) : array();
    $preferredKey = isset($_COOKIE['xzvs_sk']) ? (string) $_COOKIE['xzvs_sk'] : '';
    $decision = xz_visit_stats_v4_session_decide($previous, $visitor, xz_visit_stats_ensure_secret(), $now, xz_visit_stats_v4_session_timeout(), $preferredKey);
    $pathKey = isset($visit['path_key']) ? (string) $visit['path_key'] : '';
    if (isset($previous['id']) && $decision['session_key'] === $previous['session_key']) {
        $id = $previous['id'];
        $zbp->db->Query('UPDATE `' . $sessions . '` SET se_SessionKey=' . xz_visit_stats_v4_sql($decision['session_key']) . ',se_LastSeenAt=' . $now . ',se_ExitPathKey=' . xz_visit_stats_v4_sql($pathKey) . ',se_PageCount=' . (int) $decision['page_count'] . ',se_UpdatedAt=' . $now . ' WHERE se_ID=' . $id);
    } else {
        $zbp->db->Query('INSERT INTO `' . $sessions . '` (se_SessionKey,se_VisitorHash,se_StartedAt,se_LastSeenAt,se_EntryPathKey,se_ExitPathKey,se_PageCount,se_SourceType,se_SourceDomain,se_UpdatedAt) VALUES (' . xz_visit_stats_v4_sql($decision['session_key']) . ',' . xz_visit_stats_v4_sql($visitor) . ',' . $now . ',' . $now . ',' . xz_visit_stats_v4_sql($pathKey) . ',' . xz_visit_stats_v4_sql($pathKey) . ',1,' . xz_visit_stats_v4_sql(isset($visit['source_type']) ? $visit['source_type'] : '') . ',' . xz_visit_stats_v4_sql(isset($visit['source_domain']) ? $visit['source_domain'] : '') . ',' . $now . ')');
        $row = (array) $zbp->db->Query('SELECT se_ID FROM `' . $sessions . '` WHERE se_SessionKey=' . xz_visit_stats_v4_sql($decision['session_key']) . ' LIMIT 1');
        if (empty($row)) return null;
        $id = (int) $row[0]['se_ID'];
    }
    $zbp->db->Query('INSERT INTO `' . $pages . '` (sp_SessionID,sp_LogID,sp_Sequence,sp_PathKey,sp_Path,sp_EnteredAt,sp_UpdatedAt) VALUES (' . $id . ',' . (int) (isset($visit['log_id']) ? $visit['log_id'] : 0) . ',' . (int) $decision['sequence'] . ',' . xz_visit_stats_v4_sql($pathKey) . ',' . xz_visit_stats_v4_sql(isset($visit['path']) ? $visit['path'] : '/') . ',' . $now . ',' . $now . ')');
    return array('session_id' => $id, 'session_key' => $decision['session_key'], 'sequence' => $decision['sequence']);
}
