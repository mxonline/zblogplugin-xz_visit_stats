<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

/**
 * v2.0 database migration entry.
 *
 * Keep the original xz_visit_stats_log table untouched.
 */
function xz_visit_stats_upgrade_migrate_to_20()
{
    xz_visit_stats_upgrade_create_pages_table();
    xz_visit_stats_upgrade_create_keywords_table();

    return true;
}

function xz_visit_stats_upgrade_create_pages_table()
{
    global $zbp;

    $table = $zbp->db->dbpre . 'xz_visit_stats_pages';
    if ($zbp->db->ExistTable($table)) {
        return;
    }

    $sql = $zbp->db->sql->CreateTable($table, array(
        'ID' => array('ID', 'integer', 'bigint', 0),
        'Url' => array('Url', 'string', 'text', ''),
        'Title' => array('Title', 'string', 255, ''),
        'PV' => array('PV', 'integer', '', 0),
        'UV' => array('UV', 'integer', '', 0),
        'LastVisit' => array('LastVisit', 'integer', 'bigint', 0),
    ));

    $zbp->db->QueryMulti($sql);
}

function xz_visit_stats_upgrade_create_keywords_table()
{
    global $zbp;

    $table = $zbp->db->dbpre . 'xz_visit_stats_keywords';
    if ($zbp->db->ExistTable($table)) {
        return;
    }

    $sql = $zbp->db->sql->CreateTable($table, array(
        'ID' => array('ID', 'integer', 'bigint', 0),
        'Engine' => array('Engine', 'string', 64, ''),
        'Keyword' => array('Keyword', 'string', 255, ''),
        'Url' => array('Url', 'string', 'text', ''),
        'Count' => array('Count', 'integer', '', 0),
        'Updated' => array('Updated', 'integer', 'bigint', 0),
    ));

    $zbp->db->QueryMulti($sql);
}
