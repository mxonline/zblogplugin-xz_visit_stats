<?php

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');
$root = getenv('XZVS_ZBLOG_ROOT');
$marker = getenv('XZVS_T5_MARKER');
$mode = getenv('XZVS_T5_MARKER_MODE');
if (!is_string($root) || $root === '' || !is_string($marker) || preg_match('/^[a-f0-9]{32}$/', $marker) !== 1) {
    fwrite(STDERR, "Missing runtime root or a 32-character hex marker.\n");
    exit(2);
}
$root = rtrim(str_replace('\\', '/', $root), '/') . '/';
defined('ZBP_SAFEMODE') || define('ZBP_SAFEMODE', true);
defined('ZBP_OBSTART') || define('ZBP_OBSTART', false);
require $root . 'zb_system/function/c_system_base.php';
$db = $GLOBALS['zbp']->db;
$prefix = (string) $db->dbpre;
$quoteTable = function ($suffix) use ($prefix) { return '`' . str_replace('`', '``', $prefix . 'xz_visit_stats_' . $suffix) . '`'; };
$log = $quoteTable('log');
$pages = $quoteTable('session_pages');
$sessions = $quoteTable('sessions');
$events = $quoteTable('events');
$rum = $quoteTable('rum');
$safeMarker = str_replace("'", "''", $marker);
$where = "vs_Url LIKE '%{$safeMarker}%' OR vs_Path LIKE '%{$safeMarker}%' OR vs_Referer LIKE '%{$safeMarker}%' OR vs_UserAgent LIKE '%{$safeMarker}%'";

if ($mode === 'verify') {
    $rows = $db->Query("SELECT COUNT(*) AS pv,SUM(vs_IsBot=0) AS human_pv,SUM(vs_IsBot=1) AS bot_pv,COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_VisitorHash END) AS uv,COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_IP END) AS ip,COUNT(DISTINCT CASE WHEN vs_IsBot=0 THEN vs_SourceType END) AS source_types FROM {$log} WHERE {$where}");
    $botNames = $db->Query("SELECT vs_BotName,COUNT(*) AS pv FROM {$log} WHERE ({$where}) AND vs_IsBot=1 GROUP BY vs_BotName ORDER BY vs_BotName ASC");
    $statuses = $db->Query("SELECT vs_StatusCode,COUNT(*) AS pv FROM {$log} WHERE {$where} GROUP BY vs_StatusCode ORDER BY vs_StatusCode ASC");
    $markedRows = $db->Query("SELECT vs_ID,vs_VisitorHash,vs_VisitedAt FROM {$log} WHERE {$where} ORDER BY vs_ID ASC LIMIT 11");
    $logIds = array_map(function ($row) { return (int) $row['vs_ID']; }, (array) $markedRows);
    $pageRows = empty($logIds) ? array() : $db->Query("SELECT sp_SessionID,sp_DurationMs FROM {$pages} WHERE sp_LogID IN (" . implode(',', $logIds) . ')');
    $pageMetrics = array('dwell_samples' => 0, 'average_dwell_ms' => null, 'min_dwell_ms' => null, 'max_dwell_ms' => null);
    if (!empty($logIds)) {
        $pageMetricRows = $db->Query("SELECT COUNT(sp_DurationMs) AS dwell_samples,AVG(sp_DurationMs) AS average_dwell_ms,MIN(sp_DurationMs) AS min_dwell_ms,MAX(sp_DurationMs) AS max_dwell_ms FROM {$pages} WHERE sp_LogID IN (" . implode(',', $logIds) . ')');
        $pageMetrics = $pageMetricRows[0];
        foreach (array('dwell_samples', 'min_dwell_ms', 'max_dwell_ms') as $key) $pageMetrics[$key] = $pageMetrics[$key] === null ? null : (int) $pageMetrics[$key];
        $pageMetrics['average_dwell_ms'] = $pageMetrics['average_dwell_ms'] === null ? null : (float) $pageMetrics['average_dwell_ms'];
    }
    $sessionIds = array_values(array_unique(array_map(function ($row) { return (int) $row['sp_SessionID']; }, (array) $pageRows)));
    $sessionMetrics = array('session_count' => 0, 'page_count' => count((array) $pageRows), 'completed_bounces' => 0, 'dwell_samples' => 0, 'average_dwell_ms' => null);
    $eventCount = 0;
    $eventNames = array();
    $eventParameterKeys = array();
    if (!empty($sessionIds)) {
        $sessionList = implode(',', $sessionIds);
        $sessionRows = $db->Query("SELECT COUNT(*) AS session_count,SUM(se_IsBounce=1) AS completed_bounces,COUNT(se_DurationMs) AS dwell_samples,AVG(se_DurationMs) AS average_dwell_ms FROM {$sessions} WHERE se_ID IN ({$sessionList})");
        $sessionMetrics = array_merge($sessionMetrics, $sessionRows[0]);
        $eventRows = $db->Query("SELECT ev_Name,ev_Params FROM {$events} WHERE ev_SessionID IN ({$sessionList}) ORDER BY ev_ID ASC");
        $eventCount = count((array) $eventRows);
        foreach ($eventRows as $eventRow) {
            $eventNames[] = (string) $eventRow['ev_Name'];
            $params = json_decode((string) $eventRow['ev_Params'], true);
            if (is_array($params)) foreach (array_keys($params) as $key) $eventParameterKeys[(string) $key] = true;
        }
    }
    $rumCount = 0;
    if (!empty($markedRows)) {
        $hashes = array_values(array_unique(array_map(function ($row) { return (string) $row['vs_VisitorHash']; }, $markedRows)));
        $quotedHashes = array_map(function ($hash) { return "'" . str_replace("'", "''", $hash) . "'"; }, $hashes);
        $firstAt = min(array_map(function ($row) { return (int) $row['vs_VisitedAt']; }, $markedRows));
        $lastAt = max(array_map(function ($row) { return (int) $row['vs_VisitedAt']; }, $markedRows)) + 60;
        $rumRows = $db->Query("SELECT COUNT(*) AS rum_count FROM {$rum} WHERE rum_VisitorHash IN (" . implode(',', $quotedHashes) . ") AND rum_VisitedAt>={$firstAt} AND rum_VisitedAt<={$lastAt}");
        $rumCount = (int) $rumRows[0]['rum_count'];
    }
    $sessionMetrics['session_count'] = (int) $sessionMetrics['session_count'];
    $sessionMetrics['page_count'] = (int) $sessionMetrics['page_count'];
    $sessionMetrics['completed_bounces'] = (int) $sessionMetrics['completed_bounces'];
    $sessionMetrics['dwell_samples'] = (int) $sessionMetrics['dwell_samples'];
    $sessionMetrics['average_dwell_ms'] = $sessionMetrics['average_dwell_ms'] === null ? null : (float) $sessionMetrics['average_dwell_ms'];
    $eventParameterKeys = array_keys($eventParameterKeys);
    sort($eventParameterKeys, SORT_STRING);
    echo json_encode(array('status' => 'PASS', 'aggregate' => $rows[0], 'bot_names' => $botNames, 'statuses' => $statuses, 'session_metrics' => $sessionMetrics, 'page_metrics' => $pageMetrics, 'events' => array('count' => $eventCount, 'names' => $eventNames, 'parameter_keys' => $eventParameterKeys), 'rum_rows' => $rumCount), JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

if ($mode !== 'cleanup') {
    fwrite(STDERR, "Mode must be verify or cleanup.\n");
    exit(3);
}
$marked = $db->Query("SELECT vs_ID,vs_VisitorHash,vs_VisitedAt FROM {$log} WHERE {$where} ORDER BY vs_ID ASC LIMIT 11");
if (count((array) $marked) > 10) {
    fwrite(STDERR, "Cleanup refused: marker matched more than ten raw logs.\n");
    exit(4);
}
$logIds = array_map(function ($row) { return (int) $row['vs_ID']; }, (array) $marked);
if (empty($logIds)) {
    echo json_encode(array('status' => 'PASS', 'deleted_logs' => 0, 'deleted_pages' => 0, 'deleted_sessions' => 0, 'deleted_events' => 0), JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}
$idList = implode(',', $logIds);
$pageRows = $db->Query("SELECT sp_ID,sp_LogID,sp_SessionID FROM {$pages} WHERE sp_LogID IN ({$idList})");
$expectedEventName = 'T5_' . $marker;
$sessionIds = array_values(array_unique(array_map(function ($row) { return (int) $row['sp_SessionID']; }, (array) $pageRows)));
$sessionPages = array();
$eventIds = array();
foreach ($sessionIds as $sessionId) {
    $sessionPages[$sessionId] = $db->Query("SELECT sp_ID,sp_LogID FROM {$pages} WHERE sp_SessionID={$sessionId}");
    foreach ($sessionPages[$sessionId] as $page) {
        if (!in_array((int) $page['sp_LogID'], $logIds, true)) {
            fwrite(STDERR, "Cleanup refused: marked session contains an unmarked visit page.\n");
            exit(5);
        }
    }
    $sessionRow = $db->Query("SELECT se_ID,se_PageCount FROM {$sessions} WHERE se_ID={$sessionId} LIMIT 1");
    $eventRows = $db->Query("SELECT ev_ID,ev_Name FROM {$events} WHERE ev_SessionID={$sessionId} LIMIT 11");
    foreach ($eventRows as $eventRow) {
        if ((string) $eventRow['ev_Name'] !== $expectedEventName) {
            fwrite(STDERR, "Cleanup refused: marked session contains an unmarked event.\n");
            exit(6);
        }
        $eventIds[] = (int) $eventRow['ev_ID'];
    }
    if (count((array) $sessionRow) !== 1 || (int) $sessionRow[0]['se_PageCount'] !== count((array) $sessionPages[$sessionId])) {
        fwrite(STDERR, "Cleanup refused: associated session has unrelated pages, missing state, or events.\n");
        exit(6);
    }
}
$hashes = array_values(array_unique(array_map(function ($row) { return (string) $row['vs_VisitorHash']; }, (array) $marked)));
$quotedHashes = array_map(function ($hash) { return "'" . str_replace("'", "''", $hash) . "'"; }, $hashes);
$firstAt = min(array_map(function ($row) { return (int) $row['vs_VisitedAt']; }, (array) $marked));
$lastAt = max(array_map(function ($row) { return (int) $row['vs_VisitedAt']; }, (array) $marked)) + 60;
$rumRows = $db->Query("SELECT rum_ID FROM {$rum} WHERE rum_VisitorHash IN (" . implode(',', $quotedHashes) . ") AND rum_VisitedAt>={$firstAt} AND rum_VisitedAt<={$lastAt} LIMIT 11");
if (count((array) $rumRows) > 10) {
    fwrite(STDERR, "Cleanup refused: more than ten RUM rows matched the exact test visitor and time window.\n");
    exit(7);
}
foreach ($eventIds as $eventId) $db->Query("DELETE FROM {$events} WHERE ev_ID={$eventId} AND ev_Name='" . str_replace("'", "''", $expectedEventName) . "'");
foreach ($rumRows as $rumRow) $db->Query("DELETE FROM {$rum} WHERE rum_ID=" . (int) $rumRow['rum_ID']);
foreach ($sessionIds as $sessionId) $db->Query("DELETE FROM {$pages} WHERE sp_SessionID={$sessionId}");
foreach ($sessionIds as $sessionId) $db->Query("DELETE FROM {$sessions} WHERE se_ID={$sessionId}");
$db->Query("DELETE FROM {$log} WHERE vs_ID IN ({$idList}) AND ({$where})");
echo json_encode(array('status' => 'PASS', 'deleted_logs' => count($logIds), 'deleted_pages' => count((array) $pageRows), 'deleted_sessions' => count($sessionIds), 'deleted_events' => count($eventIds), 'deleted_rum_rows' => count((array) $rumRows)), JSON_UNESCAPED_SLASHES) . "\n";
