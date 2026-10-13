<?php

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

$root = getenv('XZVS_ZBLOG_ROOT');
if (!is_string($root) || $root === '') {
    fwrite(STDERR, "Missing XZVS_ZBLOG_ROOT.\n");
    exit(2);
}

$root = rtrim(str_replace('\\', '/', $root), '/') . '/';
defined('ZBP_SAFEMODE') || define('ZBP_SAFEMODE', true);
defined('ZBP_OBSTART') || define('ZBP_OBSTART', false);
require $root . 'zb_system/function/c_system_base.php';

$db = $GLOBALS['zbp']->db;
$prefix = (string) $db->dbpre;
$mode = getenv('XZVS_ACTIVITY_MODE');
$targetMinute = getenv('XZVS_T5_TARGET_MINUTE');
$testMarker = getenv('XZVS_T5_MARKER');
$since = time() - 7200;
$table = '`' . str_replace('`', '``', $prefix . 'xz_visit_stats_log') . '`';
$pages = '`' . str_replace('`', '``', $prefix . 'xz_visit_stats_session_pages') . '`';
$sessions = '`' . str_replace('`', '``', $prefix . 'xz_visit_stats_sessions') . '`';
$events = '`' . str_replace('`', '``', $prefix . 'xz_visit_stats_events') . '`';
$rum = '`' . str_replace('`', '``', $prefix . 'xz_visit_stats_rum') . '`';
$markerPattern = '/^[a-f0-9]{32}$/';

if ($mode === 'verify_marker') {
    if (!is_string($testMarker) || preg_match($markerPattern, $testMarker) !== 1) {
        fwrite(STDERR, "Marker verification needs one 32-character hex marker.\n");
        exit(3);
    }
    $markerValue = str_replace("'", "''", $testMarker);
    $matching = $db->Query("SELECT COUNT(*) AS total_rows,SUM(vs_IsBot=1) AS bot_rows,SUM(vs_StatusCode=404) AS not_found_rows FROM {$table} WHERE vs_Url LIKE '%{$markerValue}%' OR vs_Path LIKE '%{$markerValue}%' OR vs_Referer LIKE '%{$markerValue}%' OR vs_UserAgent LIKE '%{$markerValue}%'");
    echo json_encode(array('status' => 'PASS', 'marker_rows' => (int) $matching[0]['total_rows'], 'bot_rows' => (int) $matching[0]['bot_rows'], 'not_found_rows' => (int) $matching[0]['not_found_rows']), JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}
if ($mode === 'explain') {
    $probes = array(
        array('name' => 'log_time', 'expected_index' => 'xzvs_visited_at', 'sql' => 'SELECT vs_ID FROM ' . $table . ' WHERE vs_VisitedAt>=' . $since . ' ORDER BY vs_VisitedAt DESC LIMIT 100'),
        array('name' => 'log_visitor_time', 'expected_index' => 'xzvs_visitor_time', 'sql' => "SELECT vs_ID FROM {$table} WHERE vs_VisitorHash='" . str_repeat('0', 64) . "' AND vs_VisitedAt>={$since} ORDER BY vs_VisitedAt DESC LIMIT 100"),
        array('name' => 'log_ip_time', 'expected_index' => 'xzvs_ip_time', 'sql' => "SELECT vs_ID FROM {$table} WHERE vs_IP='192.0.2.1' AND vs_VisitedAt>={$since} ORDER BY vs_VisitedAt DESC LIMIT 100"),
        array('name' => 'session_active_time', 'expected_index' => 'xzvs_session_active', 'sql' => 'SELECT se_ID FROM ' . $sessions . ' WHERE se_LastSeenAt>=' . $since . ' ORDER BY se_LastSeenAt DESC,se_ID DESC LIMIT 100'),
        array('name' => 'session_page_log_drilldown', 'expected_index' => 'xzvs_page_log', 'sql' => 'SELECT sp_ID FROM ' . $pages . ' WHERE sp_LogID=0 LIMIT 100'),
        array('name' => 'event_time', 'expected_index' => 'xzvs_event_time', 'sql' => 'SELECT ev_ID FROM ' . $events . ' WHERE ev_TriggeredAt>=' . $since . ' ORDER BY ev_TriggeredAt DESC,ev_ID DESC LIMIT 100'),
    );
    $plans = array();
    foreach ($probes as $probe) {
        $started = microtime(true);
        $rows = (array) $db->Query('EXPLAIN ' . $probe['sql']);
        $elapsed = round((microtime(true) - $started) * 1000, 3);
        foreach ($rows as $row) {
            $actualIndex = isset($row['key']) ? (string) $row['key'] : '';
            $plans[] = array(
                'query' => $probe['name'],
                'table' => isset($row['table']) ? (string) $row['table'] : '',
                'access_type' => isset($row['type']) ? (string) $row['type'] : '',
                'expected_index' => $probe['expected_index'],
                'possible_keys' => isset($row['possible_keys']) ? (string) $row['possible_keys'] : '',
                'selected_index' => $actualIndex,
                'uses_expected_index' => $actualIndex === $probe['expected_index'],
                'estimated_rows' => isset($row['rows']) ? (int) $row['rows'] : null,
                'explain_ms' => $elapsed,
                'extra' => isset($row['Extra']) ? (string) $row['Extra'] : '',
            );
        }
    }
    echo json_encode(array('status' => 'PASS', 'database_mode' => 'read_only', 'plans' => $plans), JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}
$rows = $db->Query("SELECT DATE_FORMAT(FROM_UNIXTIME(vs_VisitedAt),'%Y-%m-%d %H:%i') AS minute_bucket,COUNT(*) AS rows_count,SUM(vs_IsBot=1) AS bot_count FROM {$table} WHERE vs_VisitedAt>={$since} GROUP BY minute_bucket ORDER BY minute_bucket DESC LIMIT 40");
$totals = $db->Query("SELECT COUNT(*) AS rows_count,MIN(vs_VisitedAt) AS min_at,MAX(vs_VisitedAt) AS max_at,MAX(vs_ID) AS max_id FROM {$table} WHERE vs_VisitedAt>={$since}");

if ($mode === 'cleanup_one_homepage_smoke') {
    if (!is_string($targetMinute) || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $targetMinute) !== 1) {
        fwrite(STDERR, "Cleanup needs one exact local minute in YYYY-MM-DD HH:MM form.\n");
        exit(3);
    }
    $safeMinute = str_replace("'", "''", $targetMinute);
    $candidate = $db->Query("SELECT vs_ID FROM {$table} WHERE DATE_FORMAT(FROM_UNIXTIME(vs_VisitedAt),'%Y-%m-%d %H:%i')='{$safeMinute}' AND vs_Path='/' AND vs_IsBot=0 AND vs_StatusCode=200 LIMIT 2");
    if (count((array) $candidate) !== 1) {
        fwrite(STDERR, "Cleanup refused: the exact-minute homepage candidate is not unique.\n");
        exit(4);
    }
    $logId = (int) $candidate[0]['vs_ID'];
    $pageRows = $db->Query("SELECT sp_ID,sp_SessionID FROM {$pages} WHERE sp_LogID={$logId}");
    if (count((array) $pageRows) !== 1) {
        fwrite(STDERR, "Cleanup refused: the expected single session-page link is missing or ambiguous.\n");
        exit(5);
    }
    $pageId = (int) $pageRows[0]['sp_ID'];
    $sessionId = (int) $pageRows[0]['sp_SessionID'];
    $sessionRows = $db->Query("SELECT se_ID,se_StartedAt,se_PageCount,DATE_FORMAT(FROM_UNIXTIME(se_StartedAt),'%Y-%m-%d %H:%i') AS minute_bucket FROM {$sessions} WHERE se_ID={$sessionId}");
    $siblingPages = $db->Query("SELECT sp_ID FROM {$pages} WHERE sp_SessionID={$sessionId}");
    $eventRows = $db->Query("SELECT ev_ID FROM {$events} WHERE ev_SessionID={$sessionId} LIMIT 2");
    if (count((array) $sessionRows) !== 1 || (string) $sessionRows[0]['minute_bucket'] !== $targetMinute || (int) $sessionRows[0]['se_PageCount'] !== 1 || count((array) $siblingPages) !== 1 || (int) $siblingPages[0]['sp_ID'] !== $pageId || !empty($eventRows)) {
        fwrite(STDERR, "Cleanup refused: associated session contains unrelated pages or events.\n");
        exit(6);
    }
    $db->Query("DELETE FROM {$pages} WHERE sp_ID={$pageId} AND sp_LogID={$logId}");
    $db->Query("DELETE FROM {$sessions} WHERE se_ID={$sessionId} AND se_PageCount=1");
    $db->Query("DELETE FROM {$table} WHERE vs_ID={$logId} AND vs_Path='/' AND vs_IsBot=0 AND vs_StatusCode=200");
    echo json_encode(array('status' => 'PASS', 'deleted_exact_test_log_rows' => 1, 'deleted_exact_test_page_rows' => 1, 'deleted_exact_test_sessions' => 1, 'events_deleted' => 0), JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

echo json_encode(array('last_two_hour_activity' => $rows, 'aggregate' => $totals[0]), JSON_UNESCAPED_SLASHES) . "\n";
