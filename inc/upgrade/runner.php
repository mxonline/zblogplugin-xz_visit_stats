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

    return xz_visit_stats_upgrade_migrate_to_20();
}
