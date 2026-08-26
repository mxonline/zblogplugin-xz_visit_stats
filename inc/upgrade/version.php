<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_upgrade_current_version()
{
    global $zbp;

    $config = $zbp->Config('xz_visit_stats');
    if (isset($config->db_version) && is_string($config->db_version) && $config->db_version !== '') {
        return $config->db_version;
    }

    return '1.3.0';
}

function xz_visit_stats_upgrade_target_version()
{
    return '4.0.0';
}
