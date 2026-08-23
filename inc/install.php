<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

$GLOBALS['table']['xz_visit_stats_log'] = '%pre%xz_visit_stats_log';

$GLOBALS['datainfo']['xz_visit_stats_log'] = array(
    'ID'          => array('vs_ID', 'integer', 'bigint', 0),
    'IP'          => array('vs_IP', 'string', 45, ''),
    'VisitorHash' => array('vs_VisitorHash', 'char', 64, ''),
    'Url'         => array('vs_Url', 'string', 'text', ''),
    'Path'        => array('vs_Path', 'string', 2048, ''),
    'Referer'     => array('vs_Referer', 'string', 'text', ''),
    'UserAgent'   => array('vs_UserAgent', 'string', 'text', ''),
    'UaType'      => array('vs_UaType', 'string', 32, ''),
    'Browser'     => array('vs_Browser', 'string', 64, ''),
    'Os'          => array('vs_Os', 'string', 64, ''),
    'Device'      => array('vs_Device', 'string', 32, ''),
    'IsBot'       => array('vs_IsBot', 'boolean', '', false),
    'BotName'     => array('vs_BotName', 'string', 64, ''),
    'StatusCode'  => array('vs_StatusCode', 'integer', '', 200),
    'DurationMs'  => array('vs_DurationMs', 'integer', '', 0),
    'VisitedAt'   => array('vs_VisitedAt', 'integer', 'bigint', 0),
);

function xz_visit_stats_install_table()
{
    global $zbp;

    $table = $GLOBALS['table']['xz_visit_stats_log'];
    if (!$zbp->db->ExistTable($table)) {
        $sql = $zbp->db->sql->CreateTable(
            $table,
            $GLOBALS['datainfo']['xz_visit_stats_log']
        );
        $zbp->db->QueryMulti($sql);
    }

    xz_visit_stats_upgrade_columns();
    xz_visit_stats_install_indexes();
}

function xz_visit_stats_is_mysql()
{
    global $zbp;

    $dbType = strtolower((string) $zbp->db->type);
    $dbClass = strtolower(get_class($zbp->db));

    return $dbType === 'mysql' || strpos($dbClass, 'mysql') !== false;
}

function xz_visit_stats_physical_table()
{
    global $zbp;

    return str_replace(
        '%pre%',
        $zbp->db->dbpre,
        $GLOBALS['table']['xz_visit_stats_log']
    );
}

function xz_visit_stats_quoted_table()
{
    return '`' . str_replace('`', '``', xz_visit_stats_physical_table()) . '`';
}

function xz_visit_stats_upgrade_columns()
{
    global $zbp;

    if (!xz_visit_stats_is_mysql()) {
        return;
    }

    $rows = $zbp->db->Query('SHOW COLUMNS FROM ' . xz_visit_stats_quoted_table());
    $types = array();
    foreach ((array) $rows as $row) {
        if (isset($row['Field'], $row['Type'])) {
            $types[$row['Field']] = strtolower((string) $row['Type']);
        }
    }

    $changes = array();
    if (!isset($types['vs_ID'])
        || strpos($types['vs_ID'], 'bigint') === false
        || strpos($types['vs_ID'], 'unsigned') === false
    ) {
        $changes[] = 'MODIFY vs_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT';
    }
    if (!isset($types['vs_VisitedAt'])
        || strpos($types['vs_VisitedAt'], 'bigint') === false
        || strpos($types['vs_VisitedAt'], 'unsigned') === false
    ) {
        $changes[] = 'MODIFY vs_VisitedAt BIGINT UNSIGNED NOT NULL DEFAULT 0';
    }

    if (!empty($changes)) {
        $zbp->db->Query(
            'ALTER TABLE ' . xz_visit_stats_quoted_table() . ' ' . implode(', ', $changes)
        );
    }
}

function xz_visit_stats_install_indexes()
{
    global $zbp;

    if (!xz_visit_stats_is_mysql()) {
        return;
    }

    $required = array(
        'xzvs_visited_at'   => array('vs_VisitedAt'),
        'xzvs_visitor_time' => array('vs_VisitorHash', 'vs_VisitedAt'),
        'xzvs_bot_time'     => array('vs_IsBot', 'vs_VisitedAt'),
        'xzvs_ip_time'      => array('vs_IP', 'vs_VisitedAt'),
        'xzvs_status_time'  => array('vs_StatusCode', 'vs_VisitedAt'),
    );
    $rows = $zbp->db->Query('SHOW INDEX FROM ' . xz_visit_stats_quoted_table());
    $existing = array();
    foreach ((array) $rows as $row) {
        if (!isset($row['Key_name'], $row['Column_name'])) {
            continue;
        }
        $name = (string) $row['Key_name'];
        if (!isset($existing[$name])) {
            $existing[$name] = array();
        }
        $existing[$name][(int) $row['Seq_in_index']] = (string) $row['Column_name'];
    }

    $operations = array();
    foreach ($required as $name => $columns) {
        $actual = isset($existing[$name]) ? $existing[$name] : array();
        ksort($actual);
        $actual = array_values($actual);
        if ($actual === $columns) {
            continue;
        }
        if (!empty($actual)) {
            $operations[] = 'DROP INDEX `' . $name . '`';
        }
        $operations[] = 'ADD INDEX `' . $name . '` (`' . implode('`, `', $columns) . '`)';
    }

    if (!empty($operations)) {
        $zbp->db->Query(
            'ALTER TABLE ' . xz_visit_stats_quoted_table() . ' ' . implode(', ', $operations)
        );
    }
}
