<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_maintenance_retention_days()
{
    $settings = xz_visit_stats_settings_values();

    return $settings['retention_days'];
}

function xz_visit_stats_maintenance_save_retention_days($value)
{
    global $zbp;

    $raw = trim((string) $value);
    if (preg_match('/^[0-9]+$/', $raw) !== 1) {
        return false;
    }
    $days = (int) $raw;
    if (!in_array($days, array(30, 90, 180, 365), true)) {
        return false;
    }
    $zbp->Config('xz_visit_stats')->retention_days = $days;
    $zbp->SaveConfig('xz_visit_stats');

    return true;
}

function xz_visit_stats_maintenance_v4_table_exists($table)
{
    global $zbp;
    return is_object($zbp->db) && method_exists($zbp->db, 'ExistTable') && $zbp->db->ExistTable($table);
}

function xz_visit_stats_maintenance_v4_range_where($column, array $filters)
{
    if (isset($filters['mode']) && $filters['mode'] === 'custom') {
        return $column . ' >= ' . (int) $filters['start'] . ' AND ' . $column . ' < ' . (int) $filters['end'];
    }
    return $column . ' < ' . (int) $filters['end'];
}

function xz_visit_stats_maintenance_cleanup_v4_range(array $filters)
{
    global $zbp;
    if (!function_exists('xz_visit_stats_v4_session_table') || !function_exists('xz_visit_stats_v4_session_pages_table')) return false;
    $sessionsName = xz_visit_stats_v4_session_table();
    if (!xz_visit_stats_maintenance_v4_table_exists($sessionsName)) return false;
    $quoteTable = function ($name) { return '`' . str_replace('`', '``', (string) $name) . '`'; };
    $sessions = $quoteTable($sessionsName);
    $sessionWhere = xz_visit_stats_maintenance_v4_range_where('se_StartedAt', $filters);
    $sessionIds = 'SELECT se_ID FROM ' . $sessions . ' WHERE ' . $sessionWhere;
    $pagesName = xz_visit_stats_v4_session_pages_table();
    if (xz_visit_stats_maintenance_v4_table_exists($pagesName)) {
        $pages = $quoteTable($pagesName);
        $pageWhere = xz_visit_stats_maintenance_v4_range_where('sp_EnteredAt', $filters);
        $zbp->db->Query('DELETE FROM ' . $pages . ' WHERE ' . $pageWhere . ' OR sp_SessionID IN (' . $sessionIds . ')');
    }
    $eventsName = $zbp->db->dbpre . 'xz_visit_stats_events';
    if (xz_visit_stats_maintenance_v4_table_exists($eventsName)) {
        $events = $quoteTable($eventsName);
        $eventWhere = xz_visit_stats_maintenance_v4_range_where('ev_TriggeredAt', $filters);
        $zbp->db->Query('DELETE FROM ' . $events . ' WHERE ' . $eventWhere . ' OR ev_SessionID IN (' . $sessionIds . ')');
    }
    $zbp->db->Query('DELETE FROM ' . $sessions . ' WHERE ' . $sessionWhere);
    return true;
}

function xz_visit_stats_maintenance_cleanup_v4_retention($cutoff)
{
    global $zbp;
    if (!function_exists('xz_visit_stats_v4_session_table') || !function_exists('xz_visit_stats_v4_session_pages_table')) return false;
    $sessionsName = xz_visit_stats_v4_session_table();
    if (!xz_visit_stats_maintenance_v4_table_exists($sessionsName)) return false;
    $quoteTable = function ($name) { return '`' . str_replace('`', '``', (string) $name) . '`'; };
    $sessions = $quoteTable($sessionsName);
    $expiredSessions = 'SELECT se_ID FROM ' . $sessions . ' WHERE se_LastSeenAt < ' . (int) $cutoff;
    $pagesName = xz_visit_stats_v4_session_pages_table();
    if (xz_visit_stats_maintenance_v4_table_exists($pagesName)) {
        $pages = $quoteTable($pagesName);
        $zbp->db->Query('DELETE FROM ' . $pages . ' WHERE sp_EnteredAt < ' . (int) $cutoff . ' OR sp_SessionID IN (' . $expiredSessions . ')');
    }
    $eventsName = $zbp->db->dbpre . 'xz_visit_stats_events';
    if (xz_visit_stats_maintenance_v4_table_exists($eventsName)) {
        $events = $quoteTable($eventsName);
        $zbp->db->Query('DELETE FROM ' . $events . ' WHERE ev_TriggeredAt < ' . (int) $cutoff . ' OR ev_SessionID IN (' . $expiredSessions . ')');
    }
    $zbp->db->Query('DELETE FROM ' . $sessions . ' WHERE se_LastSeenAt < ' . (int) $cutoff);
    return true;
}

function xz_visit_stats_maintenance_auto_cleanup()
{
    global $zbp;

    $settings = xz_visit_stats_settings_values();
    $config = $zbp->Config('xz_visit_stats');
    $today = strtotime('today');
    if ((int) $config->last_export_cleanup_at < $today) {
        try {
            if (function_exists('xz_visit_stats_v4_export_cleanup')) xz_visit_stats_v4_export_cleanup(30 * 86400);
            $config->last_export_cleanup_at = $today;
            $zbp->SaveConfig('xz_visit_stats');
        } catch (Exception $exception) {
            // Export cleanup is isolated from page collection and legacy logs.
        }
    }
    if ($settings['auto_cleanup'] !== 1) {
        return;
    }
    if ((int) $config->last_cleanup_at >= $today) {
        return;
    }
    try {
        $cutoff = (int) ($today - $settings['retention_days'] * 86400);
        $zbp->db->Query('DELETE FROM ' . xz_visit_stats_stats_table() . ' WHERE vs_VisitedAt < ' . $cutoff);
        xz_visit_stats_maintenance_cleanup_v4_retention($cutoff);
        $config->last_cleanup_at = $today;
        $zbp->SaveConfig('xz_visit_stats');
    } catch (Exception $exception) {
        // Maintenance must not affect frontend requests.
    }
}

function xz_visit_stats_maintenance_table_name()
{
    global $zbp;

    if (function_exists('xz_visit_stats_physical_table')) {
        return xz_visit_stats_physical_table();
    }

    return str_replace('%pre%', $zbp->db->dbpre, $GLOBALS['table']['xz_visit_stats_log']);
}

function xz_visit_stats_maintenance_overview()
{
    global $zbp;

    $table = xz_visit_stats_stats_table();
    $row = xz_visit_stats_stats_row(
        'SELECT COUNT(*) AS logs, MIN(vs_VisitedAt) AS first_visit, MAX(vs_VisitedAt) AS last_visit FROM ' . $table
    );
    $size = 0;
    if (xz_visit_stats_is_mysql()) {
        $physical = str_replace("'", "''", xz_visit_stats_maintenance_table_name());
        $sizeRow = xz_visit_stats_stats_row(
            "SELECT COALESCE(DATA_LENGTH, 0) + COALESCE(INDEX_LENGTH, 0) AS bytes"
            . " FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $physical . "'"
        );
        $size = xz_visit_stats_stats_number($sizeRow, 'bytes');
    }

    return array(
        'logs' => xz_visit_stats_stats_number($row, 'logs'),
        'first_visit' => xz_visit_stats_stats_number($row, 'first_visit'),
        'last_visit' => xz_visit_stats_stats_number($row, 'last_visit'),
        'bytes' => $size,
    );
}

function xz_visit_stats_maintenance_format_bytes($bytes)
{
    $bytes = max(0, (float) $bytes);
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $unit = 0;
    while ($bytes >= 1024 && $unit < count($units) - 1) {
        $bytes /= 1024;
        $unit++;
    }

    return number_format($bytes, $unit === 0 ? 0 : 1) . ' ' . $units[$unit];
}

function xz_visit_stats_maintenance_purge_filters($source = null)
{
    if ($source === null) {
        $source = $_POST;
    }
    $modes = array('7', '30', '90', '180', '365', 'custom');
    $mode = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'purge_mode', ''), 16);
    if (!in_array($mode, $modes, true)) {
        $mode = '';
    }
    $start = xz_visit_stats_query_datetime(xz_visit_stats_query_value($source, 'start', ''));
    $end = xz_visit_stats_query_datetime(xz_visit_stats_query_value($source, 'end', ''));
    $result = array('mode' => $mode, 'start' => $start, 'end' => $end, 'label' => '', 'error' => '');

    if ($mode === 'custom') {
        if ($start === null || $end === null || $end < $start) {
            $result['error'] = '请提供有效的自定义开始和结束时间。';
            return $result;
        }
        // datetime-local has minute precision; include the final selected minute.
        $result['end'] = $end + 60;
        $result['label'] = date('Y-m-d H:i', $start) . ' 至 ' . date('Y-m-d H:i', $end);

        return $result;
    }
    if ($mode === '') {
        $result['error'] = '请选择日志清理范围。';
        return $result;
    }

    $days = (int) $mode;
    $result['start'] = 0;
    $result['end'] = strtotime('today') - $days * 86400;
    $result['label'] = '早于 ' . $days . ' 天前的日志';

    return $result;
}

function xz_visit_stats_maintenance_purge_where($filters)
{
    if ($filters['mode'] === 'custom') {
        return 'vs_VisitedAt >= ' . (int) $filters['start']
            . ' AND vs_VisitedAt < ' . (int) $filters['end'];
    }

    return 'vs_VisitedAt < ' . (int) $filters['end'];
}

function xz_visit_stats_maintenance_purge_count($filters)
{
    if ($filters['error'] !== '') {
        return 0;
    }
    $row = xz_visit_stats_stats_row(
        'SELECT COUNT(*) AS num FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_maintenance_purge_where($filters)
    );

    return xz_visit_stats_stats_number($row, 'num');
}

function xz_visit_stats_maintenance_delete($filters)
{
    global $zbp;

    if ($filters['error'] !== '') {
        return 0;
    }
    $count = xz_visit_stats_maintenance_purge_count($filters);
    if ($count === 0) {
        return 0;
    }
    $zbp->db->Query(
        'DELETE FROM ' . xz_visit_stats_stats_table()
        . ' WHERE ' . xz_visit_stats_maintenance_purge_where($filters)
    );
    xz_visit_stats_maintenance_cleanup_v4_range($filters);

    return $count;
}

function xz_visit_stats_maintenance_handle_post($source = null)
{
    if ($source === null) {
        $source = $_POST;
    }
    $action = xz_visit_stats_query_text(xz_visit_stats_query_value($source, 'maintenance_action', ''), 24);
    if ($action === '') {
        return array('type' => '', 'message' => '', 'filters' => null, 'count' => 0);
    }
    if (!CheckCSRFTokenValid('csrfToken', 'post')) {
        return array('type' => 'error', 'message' => '安全校验失败，请刷新页面后重试。', 'filters' => null, 'count' => 0);
    }
    if ($action === 'save_retention') {
        return xz_visit_stats_maintenance_save_retention_days(xz_visit_stats_query_value($source, 'retention_days', ''))
            ? array('type' => 'success', 'message' => '日志保存天数已保存。自动清理仍处于关闭状态。', 'filters' => null, 'count' => 0)
            : array('type' => 'error', 'message' => '日志保存天数仅支持 30、90、180 或 365 天。', 'filters' => null, 'count' => 0);
    }
    if ($action !== 'preview_purge' && $action !== 'confirm_purge') {
        return array('type' => 'error', 'message' => '无效的维护操作。', 'filters' => null, 'count' => 0);
    }

    $filters = xz_visit_stats_maintenance_purge_filters($source);
    if ($filters['error'] !== '') {
        return array('type' => 'error', 'message' => $filters['error'], 'filters' => $filters, 'count' => 0);
    }
    $count = xz_visit_stats_maintenance_purge_count($filters);
    if ($action === 'preview_purge') {
        return array('type' => 'preview', 'message' => '预计删除 ' . $count . ' 条访问日志，并清除对应时间范围的 v4 页面/事件及在该范围开始的会话记录。', 'filters' => $filters, 'count' => $count);
    }
    if (xz_visit_stats_query_value($source, 'confirm_delete', '') !== 'yes') {
        return array('type' => 'error', 'message' => '请勾选确认删除后再执行。', 'filters' => $filters, 'count' => $count);
    }

    $deleted = xz_visit_stats_maintenance_delete($filters);

    return array('type' => 'success', 'message' => '已删除 ' . $deleted . ' 条访问日志及对应的 v4 页面、事件和会话记录。', 'filters' => $filters, 'count' => $deleted);
}
