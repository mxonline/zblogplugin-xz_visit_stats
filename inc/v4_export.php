<?php

if (!defined('ZBP_PATH')) exit('Access denied');

function xz_visit_stats_v4_export_table()
{
    global $zbp;
    $name = $zbp->db->dbpre . 'xz_visit_stats_export_tasks';
    return '`' . str_replace('`', '``', $name) . '`';
}

function xz_visit_stats_v4_export_quote($value)
{
    global $zbp;
    $escaped = method_exists($zbp->db, 'EscapeString') ? $zbp->db->EscapeString((string) $value) : addslashes((string) $value);
    return "'" . $escaped . "'";
}

function xz_visit_stats_v4_export_user_id()
{
    global $zbp;
    return isset($zbp->user->ID) ? (int) $zbp->user->ID : 0;
}

function xz_visit_stats_v4_export_visible_ip($ip, $ipMode = null)
{
    if ($ipMode === null) {
        $settings = function_exists('xz_visit_stats_settings_values') ? xz_visit_stats_settings_values() : array('ip_mode' => 'full');
        $ipMode = isset($settings['ip_mode']) ? $settings['ip_mode'] : 'full';
    }
    if ($ipMode === 'masked' && function_exists('xz_visit_stats_settings_mask_ip')) return xz_visit_stats_settings_mask_ip((string) $ip);
    return (string) $ip;
}

function xz_visit_stats_v4_export_authorized($requestedUserId = null, $csrf = false)
{
    global $zbp;
    if (!isset($zbp->user->Level) || (int) $zbp->user->Level !== 1) return false;
    if ($requestedUserId !== null && !is_scalar($requestedUserId)) return false;
    if ($requestedUserId !== null && (int) $requestedUserId !== xz_visit_stats_v4_export_user_id()) return false;
    return !$csrf || (function_exists('CheckCSRFTokenValid') && CheckCSRFTokenValid('csrfToken', 'post'));
}

function xz_visit_stats_v4_export_filters(array $filters)
{
    if (!function_exists('xz_visit_stats_v4_report_filters')) return null;
    if (isset($filters['range']) && !is_scalar($filters['range'])) return null;
    $requestedRange = isset($filters['range']) ? (string) $filters['range'] : 'today';
    if (!in_array($requestedRange, array('today', 'yesterday', '7d', '30d', 'custom'), true)) return null;
    if ($requestedRange === 'custom') {
        if (!isset($filters['start'], $filters['end']) || !is_scalar($filters['start']) || !is_scalar($filters['end'])) return null;
        $start = function_exists('xz_visit_stats_query_datetime') && isset($filters['start']) ? xz_visit_stats_query_datetime($filters['start']) : null;
        $end = function_exists('xz_visit_stats_query_datetime') && isset($filters['end']) ? xz_visit_stats_query_datetime($filters['end']) : null;
        if ($start === null || $end === null || $end < $start || ($end - $start + 60) > 93 * 86400) return null;
    }
    $normalized = xz_visit_stats_v4_report_filters($filters);
    if (!in_array($normalized['range'], array('today', 'yesterday', '7d', '30d', 'custom'), true)) return null;
    $range = xz_visit_stats_v2_range($normalized);
    if ($range['end'] <= $range['start'] || ($range['end'] - $range['start']) > 93 * 86400) return null;
    $normalized['range'] = $range['range'];
    return $normalized;
}

function xz_visit_stats_v4_export_request($userId, array $filters)
{
    global $zbp;
    if (!is_scalar($userId) || !xz_visit_stats_v4_export_authorized($userId, true) || (int) $userId <= 0) return array('ok' => false, 'error' => 'forbidden');
    $normalized = xz_visit_stats_v4_export_filters($filters);
    if ($normalized === null) return array('ok' => false, 'error' => 'invalid_or_unbounded_range');
    $json = function_exists('wp_json_encode') ? wp_json_encode($normalized) : json_encode($normalized);
    if (!is_string($json)) return array('ok' => false, 'error' => 'invalid_filters');
    $now = time();
    $table = xz_visit_stats_v4_export_table();
    $zbp->db->Query('INSERT INTO ' . $table . ' (ex_UserID,ex_Status,ex_Filters,ex_FileName,ex_RequestedAt,ex_RowCount,ex_UpdatedAt) VALUES ('
        . (int) $userId . ",'pending'," . xz_visit_stats_v4_export_quote($json) . ",''," . $now . ',0,' . $now . ')');
    $id = method_exists($zbp->db, 'GetInsertId') ? (int) $zbp->db->GetInsertId() : (method_exists($zbp->db, 'InsertID') ? (int) $zbp->db->InsertID() : 0);
    if ($id <= 0) return array('ok' => false, 'error' => 'task_create_failed');
    return array('ok' => true, 'task_id' => $id, 'status' => 'pending');
}

function xz_visit_stats_v4_export_directory()
{
    if (defined('XZ_VISIT_STATS_V4_EXPORT_TEST_DIR')) return XZ_VISIT_STATS_V4_EXPORT_TEST_DIR;
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'exports';
}

function xz_visit_stats_v4_export_safe_directory($create = false)
{
    $directory = xz_visit_stats_v4_export_directory();
    if ($create && !is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) return null;
    $parent = realpath(dirname($directory));
    $resolved = realpath($directory);
    if ($parent === false || $resolved === false) return null;
    $expected = $parent . DIRECTORY_SEPARATOR . basename($directory);
    if (strtolower(rtrim($expected, '\\/')) !== strtolower(rtrim($resolved, '\\/'))) return null;
    return $resolved;
}

function xz_visit_stats_v4_export_filename($taskId)
{
    try {
        $nonce = bin2hex(random_bytes(8));
    } catch (Exception $exception) {
        $nonce = hash('sha256', uniqid((string) mt_rand(), true));
        $nonce = substr($nonce, 0, 16);
    }
    return 'xzvs-v4-export-' . (int) $taskId . '-' . $nonce . '.csv';
}

function xz_visit_stats_v4_export_csv_line($values)
{
    $cells = array();
    foreach ((array) $values as $value) {
        $safe = function_exists('xz_visit_stats_v3_csv_safe') ? xz_visit_stats_v3_csv_safe($value) : $value;
        $cells[] = '"' . str_replace('"', '""', (string) $safe) . '"';
    }
    return implode(',', $cells) . "\r\n";
}

function xz_visit_stats_v4_export_write($handle, $contents)
{
    $length = strlen($contents);
    $offset = 0;
    while ($offset < $length) {
        $written = fwrite($handle, substr($contents, $offset));
        if ($written === false || $written === 0) return false;
        $offset += $written;
    }
    return true;
}

function xz_visit_stats_v4_export_run($taskId)
{
    global $zbp;
    if (!xz_visit_stats_v4_export_authorized()) return array('status' => 'failed', 'error' => 'forbidden');
    if (!is_scalar($taskId)) return array('status' => 'failed', 'error' => 'invalid_task');
    $id = (int) $taskId;
    if ($id <= 0) return array('status' => 'failed', 'error' => 'invalid_task');
    $table = xz_visit_stats_v4_export_table();
    $rows = (array) $zbp->db->Query('SELECT ex_ID,ex_UserID,ex_Status,ex_Filters,ex_FileName,ex_RequestedAt,ex_StartedAt,ex_FinishedAt,ex_RowCount,ex_ErrorCode FROM ' . $table . ' WHERE ex_ID=' . $id . ' LIMIT 1');
    if (empty($rows) || $rows[0]['ex_Status'] !== 'pending') return array('status' => 'failed', 'error' => 'task_not_pending');
    $task = $rows[0];
    $ownerId = (int) $task['ex_UserID'];
    if ($ownerId <= 0 || $ownerId !== xz_visit_stats_v4_export_user_id()) return array('status' => 'failed', 'error' => 'forbidden');
    try {
        $claim = bin2hex(random_bytes(8));
    } catch (Exception $exception) {
        $claim = substr(hash('sha256', uniqid((string) mt_rand(), true)), 0, 16);
    }
    $claimName = 'claim-' . $claim;
    $zbp->db->Query('UPDATE ' . $table . " SET ex_Status='running',ex_FileName=" . xz_visit_stats_v4_export_quote($claimName) . ',ex_StartedAt=' . time() . ',ex_UpdatedAt=' . time() . " WHERE ex_ID={$id} AND ex_Status='pending'");
    $claimedRows = (array) $zbp->db->Query('SELECT ex_Status,ex_FileName FROM ' . $table . ' WHERE ex_ID=' . $id . ' LIMIT 1');
    if (empty($claimedRows) || $claimedRows[0]['ex_Status'] !== 'running' || $claimedRows[0]['ex_FileName'] !== $claimName) return array('status' => 'failed', 'error' => 'task_not_pending');

    $directory = xz_visit_stats_v4_export_directory();
    $path = '';
    $handle = false;
    try {
        $filters = json_decode((string) $task['ex_Filters'], true);
        if (!is_array($filters)) throw new RuntimeException('invalid_filters');
        $filters = xz_visit_stats_v4_export_filters($filters);
        if ($filters === null) throw new RuntimeException('invalid_or_unbounded_range');
        $settings = function_exists('xz_visit_stats_settings_values') ? xz_visit_stats_settings_values() : array('ip_mode' => 'full');
        $ipMode = isset($settings['ip_mode']) && $settings['ip_mode'] === 'masked' ? 'masked' : 'full';
        $realDirectory = xz_visit_stats_v4_export_safe_directory(true);
        if ($realDirectory === null) throw new RuntimeException('export_directory_unavailable');
        $filename = xz_visit_stats_v4_export_filename($id);
        if (preg_match('/^xzvs-v4-export-' . $id . '-[a-f0-9]{16}\.csv$/', $filename) !== 1) throw new RuntimeException('invalid_filename');
        $path = $realDirectory . DIRECTORY_SEPARATOR . $filename;
        $handle = fopen($path, 'xb');
        if ($handle === false) throw new RuntimeException('export_file_create_failed');
        $zbp->db->Query('UPDATE ' . $table . ' SET ex_FileName=' . xz_visit_stats_v4_export_quote($filename) . " WHERE ex_ID={$id} AND ex_Status='running' AND ex_FileName=" . xz_visit_stats_v4_export_quote($claimName));
        $fileRows = (array) $zbp->db->Query('SELECT ex_Status,ex_FileName FROM ' . $table . ' WHERE ex_ID=' . $id . ' LIMIT 1');
        if (empty($fileRows) || $fileRows[0]['ex_Status'] !== 'running' || $fileRows[0]['ex_FileName'] !== $filename) throw new RuntimeException('export_file_claim_failed');
        if (!xz_visit_stats_v4_export_write($handle, "\xEF\xBB\xBF")) throw new RuntimeException('export_write_failed');
        if (!xz_visit_stats_v4_export_write($handle, xz_visit_stats_v4_export_csv_line(array('编号','访问时间','IP 地址','访问类型','蜘蛛名称','页面路径','来源地址','浏览器','操作系统','设备类型','HTTP 状态码','服务器响应耗时(ms)','来源类型','来源域名','UTM 推广活动')))) throw new RuntimeException('export_write_failed');
        $count = 0;
        $cursor = '';
        $limit = 5000;
        $maxRows = 50000;
        while ($count < $maxRows) {
            $requestedLimit = min($limit, $maxRows - $count);
            $batch = xz_visit_stats_v3_export_rows($filters, $cursor, $requestedLimit);
            if (empty($batch)) break;
            foreach ($batch as $row) {
                $sourceLabels = array('direct' => '直接访问', 'search' => '搜索引擎', 'social' => '社交媒体', 'external' => '外部网站', 'internal' => '站内访问', 'other' => '其他来源');
                $sourceType = isset($row['vs_SourceType']) ? (string) $row['vs_SourceType'] : '';
                $ip = xz_visit_stats_v4_export_visible_ip(isset($row['vs_IP']) ? $row['vs_IP'] : '', $ipMode);
                $values = array(
                    isset($row['vs_ID']) ? $row['vs_ID'] : '', isset($row['vs_VisitedAt']) ? xz_visit_stats_v4_export_format_time((int) $row['vs_VisitedAt']) : '',
                    $ip, (int) (isset($row['vs_IsBot']) ? $row['vs_IsBot'] : 0) === 1 ? '蜘蛛访问' : '真人访问',
                    isset($row['vs_BotName']) ? $row['vs_BotName'] : '', isset($row['vs_Path']) ? $row['vs_Path'] : '', isset($row['vs_Referer']) ? $row['vs_Referer'] : '',
                    isset($row['vs_Browser']) ? $row['vs_Browser'] : '', isset($row['vs_Os']) ? $row['vs_Os'] : '', isset($row['vs_Device']) ? $row['vs_Device'] : '',
                    isset($row['vs_StatusCode']) ? $row['vs_StatusCode'] : '', isset($row['vs_DurationMs']) ? $row['vs_DurationMs'] : '',
                    isset($sourceLabels[$sourceType]) ? $sourceLabels[$sourceType] : $sourceType, isset($row['vs_SourceDomain']) ? $row['vs_SourceDomain'] : '',
                    isset($row['vs_UtmCampaign']) ? $row['vs_UtmCampaign'] : '',
                );
                if (!xz_visit_stats_v4_export_write($handle, xz_visit_stats_v4_export_csv_line($values))) throw new RuntimeException('export_write_failed');
                $count++;
                $cursor = (string) (isset($row['vs_ID']) ? $row['vs_ID'] : '');
                if ($count >= $maxRows) break;
            }
            if (count($batch) < $requestedLimit || $cursor === '') break;
        }
        if (!fclose($handle)) throw new RuntimeException('export_close_failed');
        $handle = false;
        $zbp->db->Query('UPDATE ' . $table . " SET ex_Status='completed',ex_FileName=" . xz_visit_stats_v4_export_quote($filename) . ',ex_RowCount=' . $count . ',ex_FinishedAt=' . time() . ',ex_UpdatedAt=' . time() . " WHERE ex_ID={$id} AND ex_Status='running'");
        return array('status' => 'completed', 'row_count' => $count, 'file_name' => $filename);
    } catch (Exception $exception) {
        if (is_resource($handle)) fclose($handle);
        if ($path !== '' && is_file($path)) unlink($path);
        $code = preg_match('/^[a-z0-9_]{1,64}$/', $exception->getMessage()) === 1 ? $exception->getMessage() : 'export_failed';
        $zbp->db->Query('UPDATE ' . $table . " SET ex_Status='failed',ex_ErrorCode=" . xz_visit_stats_v4_export_quote($code) . ',ex_FinishedAt=' . time() . ',ex_UpdatedAt=' . time() . " WHERE ex_ID={$id} AND ex_Status='running'");
        return array('status' => 'failed', 'error' => $code);
    }
}

function xz_visit_stats_v4_export_format_time($timestamp)
{
    $date = new DateTime('@' . (int) $timestamp);
    $zone = function_exists('xz_visit_stats_rollup_timezone') ? xz_visit_stats_rollup_timezone() : 'UTC';
    $date->setTimezone(new DateTimeZone($zone));
    return $date->format('Y-m-d H:i:s');
}

function xz_visit_stats_v4_export_status($userId, $taskId)
{
    global $zbp;
    if (!is_scalar($userId) || !is_scalar($taskId)) return null;
    if (!xz_visit_stats_v4_export_authorized($userId, false) || (int) $taskId <= 0) return null;
    $rows = (array) $zbp->db->Query('SELECT ex_ID,ex_UserID,ex_Status,ex_FileName,ex_RequestedAt,ex_StartedAt,ex_FinishedAt,ex_RowCount FROM ' . xz_visit_stats_v4_export_table() . ' WHERE ex_ID=' . (int) $taskId . ' AND ex_UserID=' . (int) $userId . ' LIMIT 1');
    if (empty($rows) || (int) $rows[0]['ex_UserID'] !== (int) $userId) return null;
    $row = $rows[0];
    $safeName = preg_match('/^xzvs-v4-export-' . (int) $row['ex_ID'] . '-[a-f0-9]{16}\.csv$/', (string) $row['ex_FileName']) === 1 ? (string) $row['ex_FileName'] : '';
    $directory = xz_visit_stats_v4_export_safe_directory(false);
    $path = $directory === null || $safeName === '' ? false : realpath($directory . DIRECTORY_SEPARATOR . $safeName);
    $isOwnedFile = $path !== false && dirname($path) === $directory && is_file($path);
    return array(
        'id' => (int) $row['ex_ID'], 'status' => (string) $row['ex_Status'], 'file_name' => $safeName,
        'download_ready' => $isOwnedFile && $row['ex_Status'] === 'completed',
        'requested_at' => (int) $row['ex_RequestedAt'], 'started_at' => isset($row['ex_StartedAt']) ? (int) $row['ex_StartedAt'] : null,
        'finished_at' => isset($row['ex_FinishedAt']) ? (int) $row['ex_FinishedAt'] : null, 'row_count' => (int) $row['ex_RowCount'],
    );
}

function xz_visit_stats_v4_export_download_path($userId, $taskId)
{
    $status = xz_visit_stats_v4_export_status($userId, $taskId);
    if ($status === null || !$status['download_ready']) return null;
    $directory = xz_visit_stats_v4_export_safe_directory(false);
    $path = $directory === null ? false : realpath($directory . DIRECTORY_SEPARATOR . $status['file_name']);
    return $path !== false && dirname($path) === $directory && is_file($path) ? $path : null;
}

function xz_visit_stats_v4_export_recent_tasks($userId, $limit = 20)
{
    global $zbp;
    if (!xz_visit_stats_v4_export_authorized($userId, false)) return array();
    $limit = max(1, min(50, (int) $limit));
    return (array) $zbp->db->Query('SELECT ex_ID,ex_UserID,ex_Status,ex_FileName,ex_RequestedAt,ex_FinishedAt,ex_RowCount FROM ' . xz_visit_stats_v4_export_table() . ' WHERE ex_UserID=' . (int) $userId . ' ORDER BY ex_RequestedAt DESC,ex_ID DESC LIMIT ' . $limit);
}

function xz_visit_stats_v4_export_cleanup($olderThan, $now = null)
{
    global $zbp;
    $now = $now === null ? time() : (int) $now;
    $cutoff = $now - max(86400, (int) $olderThan);
    $rows = (array) $zbp->db->Query('SELECT ex_ID,ex_Status,ex_FileName,ex_RequestedAt,ex_StartedAt,ex_FinishedAt FROM ' . xz_visit_stats_v4_export_table() . " WHERE (ex_Status IN ('completed','failed') AND ex_FinishedAt>0 AND ex_FinishedAt<" . $cutoff . ") OR (ex_Status='running' AND ex_StartedAt>0 AND ex_StartedAt<" . $cutoff . ") OR (ex_Status='pending' AND ex_RequestedAt>0 AND ex_RequestedAt<" . $cutoff . ') ORDER BY ex_ID ASC LIMIT 100');
    $directory = xz_visit_stats_v4_export_safe_directory(false);
    $removed = 0;
    foreach ($rows as $row) {
        $id = isset($row['ex_ID']) ? (int) $row['ex_ID'] : 0;
        $name = isset($row['ex_FileName']) ? (string) $row['ex_FileName'] : '';
        if ($directory !== null && preg_match('/^xzvs-v4-export-' . $id . '-[a-f0-9]{16}\.csv$/', $name) === 1) {
            $path = $directory . DIRECTORY_SEPARATOR . $name;
            $realPath = realpath($path);
            if ($realPath !== false && dirname($realPath) === $directory && is_file($realPath) && unlink($realPath)) $removed++;
        }
        $zbp->db->Query('DELETE FROM ' . xz_visit_stats_v4_export_table() . ' WHERE ex_ID=' . $id . ' AND ((ex_Status IN (\'completed\',\'failed\') AND ex_FinishedAt>0 AND ex_FinishedAt<' . $cutoff . ') OR (ex_Status=\'running\' AND ex_StartedAt>0 AND ex_StartedAt<' . $cutoff . ') OR (ex_Status=\'pending\' AND ex_RequestedAt>0 AND ex_RequestedAt<' . $cutoff . '))');
    }
    return array('tasks_removed' => count($rows), 'files_removed' => $removed);
}
