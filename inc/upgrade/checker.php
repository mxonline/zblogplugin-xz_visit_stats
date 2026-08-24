<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_upgrade_need_update($current, $target)
{
    return version_compare((string)$current, (string)$target, '<');
}

function xz_visit_stats_upgrade_table_exists($table)
{
    global $zbp;

    return $zbp->db->ExistTable($table);
}
