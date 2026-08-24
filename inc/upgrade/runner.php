<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/checker.php';
require_once __DIR__ . '/migrate.php';

function xz_visit_stats_upgrade_run()
{
    $current = xz_visit_stats_upgrade_current_version();
    $target = xz_visit_stats_upgrade_target_version();

    if (!xz_visit_stats_upgrade_need_update($current, $target)) {
        return true;
    }

    try {
        $result = xz_visit_stats_upgrade_migrate_to_20();
    } catch (Exception $exception) {
        return false;
    }

    if ($result) {
        xz_visit_stats_upgrade_mark_complete($target);
    }

    return $result;
}

function xz_visit_stats_upgrade_mark_complete($version)
{
    global $zbp;

    $config = $zbp->Config('xz_visit_stats');
    if ($config === null) {
        return;
    }

    $config->db_version = $version;
    $zbp->SaveConfig('xz_visit_stats');
}
