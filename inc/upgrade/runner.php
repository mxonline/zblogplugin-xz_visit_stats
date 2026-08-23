<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_upgrade_run()
{
    $current = xz_visit_stats_upgrade_current_version();
    $target = xz_visit_stats_upgrade_target_version();

    if (!xz_visit_stats_upgrade_need_update($current, $target)) {
        return true;
    }

    $result = xz_visit_stats_upgrade_migrate_to_20();

    if ($result) {
        xz_visit_stats_upgrade_mark_complete($target);
    }

    return $result;
}

function xz_visit_stats_upgrade_mark_complete($version)
{
    global $zbp;

    if (!isset($zbp->Config('xz_visit_stats'))) {
        return;
    }

    $zbp->Config('xz_visit_stats')->db_version = $version;
    $zbp->SaveConfig('xz_visit_stats');
}
