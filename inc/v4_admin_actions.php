<?php

if (!defined('ZBP_PATH')) exit('Access denied');

require_once __DIR__ . '/ip_filter.php';

function xz_visit_stats_v4_admin_action_result($ok, $message, $id = 0)
{
    return array('ok' => (bool) $ok, 'message' => (string) $message, 'id' => (int) $id);
}

function xz_visit_stats_v4_admin_action_allowed()
{
    global $zbp;
    if (!isset($zbp->user->Level) || (int) $zbp->user->Level !== 1) return false;
    return function_exists('CheckCSRFTokenValid') && CheckCSRFTokenValid('csrfToken', 'post');
}

function xz_visit_stats_v4_admin_table($suffix)
{
    global $zbp;
    $name = $zbp->db->dbpre . 'xz_visit_stats_' . $suffix;
    return '`' . str_replace('`', '``', $name) . '`';
}

function xz_visit_stats_v4_admin_quote($value)
{
    global $zbp;
    $escaped = method_exists($zbp->db, 'EscapeString')
        ? $zbp->db->EscapeString((string) $value)
        : addslashes((string) $value);
    return "'" . $escaped . "'";
}

function xz_visit_stats_v4_admin_positive_id($value)
{
    if (!is_scalar($value)) return 0;
    return preg_match('/^[1-9][0-9]*$/', (string) $value) === 1 ? (int) $value : 0;
}

function xz_visit_stats_v4_ip_filter_create(array $input)
{
    global $zbp;
    if (!xz_visit_stats_v4_admin_action_allowed()) return xz_visit_stats_v4_admin_action_result(false, 'forbidden');
    $raw = isset($input['ip']) ? $input['ip'] : (isset($input['value']) ? $input['value'] : '');
    if (!is_scalar($raw)) return xz_visit_stats_v4_admin_action_result(false, 'invalid_ip_or_cidr');
    $rule = xz_visit_stats_v4_ip_rule_normalize($raw);
    if ($rule === null) return xz_visit_stats_v4_admin_action_result(false, 'invalid_ip_or_cidr');
    $table = xz_visit_stats_v4_admin_table('ip_filters');
    $duplicate = (array) $zbp->db->Query('SELECT if_ID FROM ' . $table . ' WHERE if_RuleType=' . xz_visit_stats_v4_admin_quote($rule['type']) . ' AND if_ValueHash=' . xz_visit_stats_v4_admin_quote($rule['hash']) . ' LIMIT 1');
    if (!empty($duplicate)) return xz_visit_stats_v4_admin_action_result(false, 'duplicate_rule');
    $now = time();
    $note = isset($input['note']) && is_scalar($input['note']) ? xz_visit_stats_limit(trim((string) $input['note']), 255) : '';
    $enabled = !isset($input['enabled']) || (is_scalar($input['enabled']) && (string) $input['enabled'] === '1') ? 1 : 0;
    $zbp->db->Query('INSERT INTO ' . $table . ' (if_RuleType,if_Value,if_ValueHash,if_Enabled,if_Note,if_CreatedAt,if_UpdatedAt) VALUES ('
        . xz_visit_stats_v4_admin_quote($rule['type']) . ',' . xz_visit_stats_v4_admin_quote($rule['value']) . ',' . xz_visit_stats_v4_admin_quote($rule['hash']) . ','
        . $enabled . ',' . xz_visit_stats_v4_admin_quote($note) . ',' . $now . ',' . $now . ')');
    $id = method_exists($zbp->db, 'GetInsertId') ? (int) $zbp->db->GetInsertId() : (method_exists($zbp->db, 'InsertID') ? (int) $zbp->db->InsertID() : 0);
    return xz_visit_stats_v4_admin_action_result(true, 'created', $id);
}

function xz_visit_stats_v4_ip_filter_update($id, array $input)
{
    global $zbp;
    if (!xz_visit_stats_v4_admin_action_allowed()) return xz_visit_stats_v4_admin_action_result(false, 'forbidden');
    $id = xz_visit_stats_v4_admin_positive_id($id);
    $raw = isset($input['ip']) ? $input['ip'] : (isset($input['value']) ? $input['value'] : '');
    if (!is_scalar($raw)) return xz_visit_stats_v4_admin_action_result(false, 'invalid_ip_or_id');
    $rule = xz_visit_stats_v4_ip_rule_normalize($raw);
    if ($id <= 0 || $rule === null) return xz_visit_stats_v4_admin_action_result(false, 'invalid_ip_or_id');
    $table = xz_visit_stats_v4_admin_table('ip_filters');
    $duplicate = (array) $zbp->db->Query('SELECT if_ID FROM ' . $table . ' WHERE if_RuleType=' . xz_visit_stats_v4_admin_quote($rule['type']) . ' AND if_ValueHash=' . xz_visit_stats_v4_admin_quote($rule['hash']) . ' AND if_ID<>' . $id . ' LIMIT 1');
    if (!empty($duplicate)) return xz_visit_stats_v4_admin_action_result(false, 'duplicate_rule');
    $note = isset($input['note']) && is_scalar($input['note']) ? xz_visit_stats_limit(trim((string) $input['note']), 255) : '';
    $enabled = isset($input['enabled']) && is_scalar($input['enabled']) && (string) $input['enabled'] === '1' ? 1 : 0;
    $zbp->db->Query('UPDATE ' . $table . ' SET if_RuleType=' . xz_visit_stats_v4_admin_quote($rule['type']) . ',if_Value=' . xz_visit_stats_v4_admin_quote($rule['value']) . ',if_ValueHash=' . xz_visit_stats_v4_admin_quote($rule['hash']) . ',if_Enabled=' . $enabled . ',if_Note=' . xz_visit_stats_v4_admin_quote($note) . ',if_UpdatedAt=' . time() . ' WHERE if_ID=' . $id);
    return xz_visit_stats_v4_admin_action_result(true, 'updated', $id);
}

function xz_visit_stats_v4_ip_filter_delete($id)
{
    global $zbp;
    if (!xz_visit_stats_v4_admin_action_allowed()) return xz_visit_stats_v4_admin_action_result(false, 'forbidden');
    $id = xz_visit_stats_v4_admin_positive_id($id);
    if ($id <= 0) return xz_visit_stats_v4_admin_action_result(false, 'invalid_id');
    $zbp->db->Query('DELETE FROM ' . xz_visit_stats_v4_admin_table('ip_filters') . ' WHERE if_ID=' . $id);
    return xz_visit_stats_v4_admin_action_result(true, 'deleted', $id);
}

function xz_visit_stats_v4_ip_filter_rows()
{
    global $zbp;
    return (array) $zbp->db->Query('SELECT if_ID,if_RuleType,if_Value,if_Enabled,if_Note,if_CreatedAt,if_UpdatedAt FROM ' . xz_visit_stats_v4_admin_table('ip_filters') . ' ORDER BY if_ID DESC LIMIT 100');
}

function xz_visit_stats_v4_directory_rule_normalize(array $input)
{
    foreach (array('name', 'match_type', 'pattern', 'action') as $key) {
        if (isset($input[$key]) && !is_scalar($input[$key])) return null;
    }
    $name = trim(isset($input['name']) ? (string) $input['name'] : '');
    $matchType = isset($input['match_type']) ? (string) $input['match_type'] : '';
    $pattern = trim(isset($input['pattern']) ? (string) $input['pattern'] : '');
    $action = isset($input['action']) ? (string) $input['action'] : '';
    if ($name === '' || strlen($name) > 128 || preg_match('/[\x00-\x1F\x7F]/', $name)) return null;
    if (!in_array($matchType, array('exact', 'prefix', 'contains'), true)) return null;
    if (!in_array($action, array('include', 'exclude'), true)) return null;
    if ($pattern === '' || strlen($pattern) > 2048 || preg_match('/[\x00-\x1F\x7F]/', $pattern) || strpos($pattern, '/') !== 0 || substr($pattern, 0, 2) === '//' || strpos($pattern, '\\') !== false) return null;
    return array('name' => $name, 'match_type' => $matchType, 'pattern' => $pattern, 'action' => $action);
}

function xz_visit_stats_v4_directory_rule_create(array $input)
{
    global $zbp;
    if (!xz_visit_stats_v4_admin_action_allowed()) return xz_visit_stats_v4_admin_action_result(false, 'forbidden');
    $rule = xz_visit_stats_v4_directory_rule_normalize($input);
    if ($rule === null) return xz_visit_stats_v4_admin_action_result(false, 'invalid_directory_rule');
    $table = xz_visit_stats_v4_admin_table('directory_rules');
    $rows = (array) $zbp->db->Query('SELECT MAX(dr_SortOrder) AS sort_order FROM ' . $table);
    $sortOrder = (!empty($rows) && isset($rows[0]['sort_order'])) ? (int) $rows[0]['sort_order'] + 1 : 1;
    $enabled = !isset($input['enabled']) || (is_scalar($input['enabled']) && (string) $input['enabled'] === '1') ? 1 : 0;
    $now = time();
    $zbp->db->Query('INSERT INTO ' . $table . ' (dr_Name,dr_MatchType,dr_Pattern,dr_Action,dr_Enabled,dr_SortOrder,dr_CreatedAt,dr_UpdatedAt) VALUES ('
        . xz_visit_stats_v4_admin_quote($rule['name']) . ',' . xz_visit_stats_v4_admin_quote($rule['match_type']) . ',' . xz_visit_stats_v4_admin_quote($rule['pattern']) . ','
        . xz_visit_stats_v4_admin_quote($rule['action']) . ',' . $enabled . ',' . $sortOrder . ',' . $now . ',' . $now . ')');
    $id = method_exists($zbp->db, 'GetInsertId') ? (int) $zbp->db->GetInsertId() : (method_exists($zbp->db, 'InsertID') ? (int) $zbp->db->InsertID() : 0);
    return xz_visit_stats_v4_admin_action_result(true, 'created', $id);
}

function xz_visit_stats_v4_directory_rule_update($id, array $input)
{
    global $zbp;
    if (!xz_visit_stats_v4_admin_action_allowed()) return xz_visit_stats_v4_admin_action_result(false, 'forbidden');
    $id = xz_visit_stats_v4_admin_positive_id($id);
    $rule = xz_visit_stats_v4_directory_rule_normalize($input);
    if ($id <= 0 || $rule === null) return xz_visit_stats_v4_admin_action_result(false, 'invalid_directory_rule');
    $sortValue = isset($input['sort_order']) && is_scalar($input['sort_order']) ? $input['sort_order'] : 0;
    $sortOrder = max(0, min(1000000, (int) $sortValue));
    $enabled = isset($input['enabled']) && is_scalar($input['enabled']) && (string) $input['enabled'] === '1' ? 1 : 0;
    $zbp->db->Query('UPDATE ' . xz_visit_stats_v4_admin_table('directory_rules') . ' SET dr_Name=' . xz_visit_stats_v4_admin_quote($rule['name']) . ',dr_MatchType=' . xz_visit_stats_v4_admin_quote($rule['match_type']) . ',dr_Pattern=' . xz_visit_stats_v4_admin_quote($rule['pattern']) . ',dr_Action=' . xz_visit_stats_v4_admin_quote($rule['action']) . ',dr_Enabled=' . $enabled . ',dr_SortOrder=' . $sortOrder . ',dr_UpdatedAt=' . time() . ' WHERE dr_ID=' . $id);
    return xz_visit_stats_v4_admin_action_result(true, 'updated', $id);
}

function xz_visit_stats_v4_directory_rule_delete($id)
{
    global $zbp;
    if (!xz_visit_stats_v4_admin_action_allowed()) return xz_visit_stats_v4_admin_action_result(false, 'forbidden');
    $id = xz_visit_stats_v4_admin_positive_id($id);
    if ($id <= 0) return xz_visit_stats_v4_admin_action_result(false, 'invalid_id');
    $zbp->db->Query('DELETE FROM ' . xz_visit_stats_v4_admin_table('directory_rules') . ' WHERE dr_ID=' . $id);
    return xz_visit_stats_v4_admin_action_result(true, 'deleted', $id);
}

function xz_visit_stats_v4_directory_rule_rows()
{
    global $zbp;
    return (array) $zbp->db->Query('SELECT dr_ID,dr_Name,dr_MatchType,dr_Pattern,dr_Action,dr_Enabled,dr_SortOrder FROM ' . xz_visit_stats_v4_admin_table('directory_rules') . ' ORDER BY dr_SortOrder ASC,dr_ID ASC LIMIT 100');
}

function xz_visit_stats_v4_spider_policy_save(array $input)
{
    global $zbp;
    if (!xz_visit_stats_v4_admin_action_allowed()) return xz_visit_stats_v4_admin_action_result(false, 'forbidden');
    $keys = array('record_bots', 'record_baiduspider', 'record_googlebot', 'record_bingbot', 'record_other_bots');
    $config = $zbp->Config('xz_visit_stats');
    $changed = false;
    foreach ($keys as $key) {
        if (!array_key_exists($key, $input)) continue;
        if (!is_scalar($input[$key])) return xz_visit_stats_v4_admin_action_result(false, 'invalid_spider_policy');
        $value = (string) $input[$key];
        if ($value !== '0' && $value !== '1') return xz_visit_stats_v4_admin_action_result(false, 'invalid_spider_policy');
        $config->$key = (int) $value;
        $changed = true;
    }
    if (!$changed) return xz_visit_stats_v4_admin_action_result(false, 'empty_spider_policy');
    $zbp->SaveConfig('xz_visit_stats');
    return xz_visit_stats_v4_admin_action_result(true, 'updated');
}
