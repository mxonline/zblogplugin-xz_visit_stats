<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_v4_directory_rules(): array
{
    global $zbp;
    $tableName = $zbp->db->dbpre . 'xz_visit_stats_directory_rules';
    if (method_exists($zbp->db, 'ExistTable') && !$zbp->db->ExistTable($tableName)) return array();
    $table = '`' . str_replace('`', '``', $tableName) . '`';
    return (array) $zbp->db->Query('SELECT dr_ID,dr_Name,dr_MatchType,dr_Pattern,dr_Action,dr_SortOrder FROM ' . $table . ' WHERE dr_Enabled=1 ORDER BY dr_SortOrder ASC,dr_ID ASC LIMIT 100');
}

function xz_visit_stats_v4_directory_match(string $path, array $rule): bool
{
    $pattern = (string) (isset($rule['dr_Pattern']) ? $rule['dr_Pattern'] : '');
    if ($pattern === '' || strlen($pattern) > 2048) return false;
    $type = (string) (isset($rule['dr_MatchType']) ? $rule['dr_MatchType'] : '');
    if ($type === 'exact') return $path === $pattern;
    if ($type === 'prefix') return strpos($path, $pattern) === 0;
    if ($type === 'contains') return strpos($path, $pattern) !== false;
    return false;
}

function xz_visit_stats_v4_directory_report(array $filters, ?array $pages = null): array
{
    if (function_exists('xz_visit_stats_v4_report_filters')) $filters = xz_visit_stats_v4_report_filters($filters);
    if ($pages === null) $pages = xz_visit_stats_v2_path_summary($filters, 100);
    $rules = xz_visit_stats_v4_directory_rules();
    $groups = array();
    $excludedPaths = 0;
    foreach ($pages as $page) {
        $matched = null;
        foreach ($rules as $rule) {
            if (xz_visit_stats_v4_directory_match((string) $page['path'], $rule)) {
                $matched = $rule;
                break;
            }
        }
        if ($matched !== null && isset($matched['dr_Action']) && $matched['dr_Action'] === 'exclude') {
            $excludedPaths++;
            continue;
        }
        $id = $matched !== null ? (int) $matched['dr_ID'] : 0;
        $key = $id > 0 ? 'rule:' . $id : 'other';
        if (!isset($groups[$key])) {
            $groups[$key] = array(
                'rule_id' => $id,
                'directory' => $id > 0 ? (string) $matched['dr_Name'] : '其他目录',
                'path_count' => 0,
                'visitor_pv' => 0,
                'visitor_uv' => 0,
                'visitor_ip' => 0,
                'bot_pv' => 0,
            );
        }
        $groups[$key]['path_count']++;
        foreach (array('visitor_pv', 'visitor_uv', 'visitor_ip', 'bot_pv') as $metric) {
            $groups[$key][$metric] += (int) (isset($page[$metric]) ? $page[$metric] : 0);
        }
    }
    $groups = array_values($groups);
    usort($groups, function ($left, $right) {
        return $right['visitor_pv'] <=> $left['visitor_pv'];
    });
    return array('rows' => $groups, 'excluded_path_count' => $excludedPaths, 'rules_applied' => count($rules), 'source_path_limit' => 100);
}

function xz_visit_stats_v4_host_report(array $filters): array
{
    if (function_exists('xz_visit_stats_v4_report_filters')) $filters = xz_visit_stats_v4_report_filters($filters);
    return array('rows' => xz_visit_stats_v2_source_domains($filters, 100), 'source' => 'referrer_domain');
}

function xz_visit_stats_v4_content_report(array $filters): array
{
    if (function_exists('xz_visit_stats_v4_report_filters')) $filters = xz_visit_stats_v4_report_filters($filters);
    $pages = xz_visit_stats_v2_path_summary($filters, 100);
    $directoryReport = xz_visit_stats_v4_directory_report($filters, $pages);
    $hostReport = xz_visit_stats_v4_host_report($filters);
    return array(
        'pages' => $pages,
        'entry_pages' => xz_visit_stats_v3_entry_rows($filters, 50),
        'directories' => $directoryReport['rows'],
        'directory_excluded_path_count' => $directoryReport['excluded_path_count'],
        'hosts' => $hostReport['rows'],
    );
}

function xz_visit_stats_v4_enrich_visit_rows(array $rows, string $ipMode = 'full'): array
{
    global $zbp;
    $ids = array();
    foreach ($rows as $row) {
        $id = isset($row['vs_ID']) ? (int) $row['vs_ID'] : 0;
        if ($id > 0) $ids[$id] = $id;
    }
    $byLogId = array();
    if (!empty($ids)) {
        $sessions = '`' . str_replace('`', '``', xz_visit_stats_v4_session_table()) . '`';
        $pages = '`' . str_replace('`', '``', xz_visit_stats_v4_session_pages_table()) . '`';
        $sql = 'SELECT current.sp_LogID AS log_id,s.se_ID AS session_id,s.se_PageCount AS page_count,'
            . 's.se_DurationMs AS visitor_dwell_ms,entry.sp_Path AS entry_path,current.sp_Path AS current_path'
            . ' FROM ' . $pages . ' current INNER JOIN ' . $sessions . ' s ON s.se_ID=current.sp_SessionID'
            . ' LEFT JOIN ' . $pages . ' entry ON entry.sp_SessionID=s.se_ID AND entry.sp_Sequence=1'
            . ' WHERE current.sp_LogID IN (' . implode(',', array_values($ids)) . ')';
        foreach ((array) $zbp->db->Query($sql) as $session) {
            $byLogId[(int) $session['log_id']] = $session;
        }
    }

    foreach ($rows as &$row) {
        $hash = (string) (isset($row['vs_VisitorHash']) ? $row['vs_VisitorHash'] : '');
        $row['visitor_id'] = function_exists('xz_visit_stats_v4_query_mask_hash') ? xz_visit_stats_v4_query_mask_hash($hash) : '';
        if ($ipMode === 'masked' && function_exists('xz_visit_stats_settings_mask_ip')) {
            $row['vs_IP'] = xz_visit_stats_settings_mask_ip(isset($row['vs_IP']) ? $row['vs_IP'] : '');
        }
        $row['country'] = $ipMode === 'masked' ? '' : (string) (isset($row['vs_GeoCountry']) ? $row['vs_GeoCountry'] : '');
        $row['region'] = $ipMode === 'masked' ? '' : (string) (isset($row['vs_GeoRegion']) ? $row['vs_GeoRegion'] : '');
        $row['source_type'] = (string) (isset($row['vs_SourceType']) ? $row['vs_SourceType'] : '');
        $row['source_domain'] = (string) (isset($row['vs_SourceDomain']) ? $row['vs_SourceDomain'] : '');
        $row['server_response_ms'] = isset($row['vs_DurationMs']) ? (int) $row['vs_DurationMs'] : null;
        $row['session_id'] = null;
        $row['session_page_count'] = null;
        $row['visitor_dwell_ms'] = null;
        $row['entry_path'] = '';
        $row['current_path'] = (string) (isset($row['vs_Path']) ? $row['vs_Path'] : '');
        $row['search_engine'] = '';
        $row['keyword'] = '';
        if (function_exists('xz_visit_stats_source_referer_details')) {
            $source = xz_visit_stats_source_referer_details(isset($row['vs_Referer']) ? $row['vs_Referer'] : '');
            $row['search_engine'] = (string) $source['search_engine'];
            $row['keyword'] = (string) $source['keyword'];
        }
        $id = isset($row['vs_ID']) ? (int) $row['vs_ID'] : 0;
        if (isset($byLogId[$id])) {
            $session = $byLogId[$id];
            $row['session_id'] = (int) $session['session_id'];
            $row['session_page_count'] = (int) $session['page_count'];
            $row['visitor_dwell_ms'] = isset($session['visitor_dwell_ms']) && $session['visitor_dwell_ms'] !== null ? (int) $session['visitor_dwell_ms'] : null;
            $row['entry_path'] = (string) (isset($session['entry_path']) ? $session['entry_path'] : '');
            $row['current_path'] = (string) (isset($session['current_path']) ? $session['current_path'] : $row['current_path']);
        }
    }
    unset($row);
    return $rows;
}
