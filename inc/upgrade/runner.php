<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

require_once __DIR__ . '/version.php';
require_once __DIR__ . '/checker.php';
require_once __DIR__ . '/migrate.php';
require_once dirname(__DIR__) . '/rollup.php';

function xz_visit_stats_upgrade_run()
{
    $current = xz_visit_stats_upgrade_current_version();
    $target = xz_visit_stats_upgrade_target_version();

    if (!xz_visit_stats_upgrade_need_update($current, $target) && xz_visit_stats_upgrade_schema_ready()) {
        return true;
    }

    try {
        $result = xz_visit_stats_upgrade_migrate_to_40();
    } catch (Exception $exception) {
        return false;
    }

    if ($result) {
        xz_visit_stats_upgrade_mark_complete($target);
    }

    return $result;
}

function xz_visit_stats_upgrade_schema_ready()
{
    global $zbp;

    if (!$zbp->db->ExistTable(xz_visit_stats_rollup_table()) || !$zbp->db->ExistTable(xz_visit_stats_rollup_state_table()) || !$zbp->db->ExistTable(xz_visit_stats_rollup_hourly_table()) || !$zbp->db->ExistTable(xz_visit_stats_saved_filters_table()) || !$zbp->db->ExistTable(xz_visit_stats_rum_table())) {
        return false;
    }

    return xz_visit_stats_upgrade_column_exists(xz_visit_stats_physical_table(), 'vs_PathKey')
        && xz_visit_stats_upgrade_column_exists(xz_visit_stats_physical_table(), 'vs_SourceType')
        && xz_visit_stats_upgrade_rum_metrics_nullable(xz_visit_stats_rum_table())
        && xz_visit_stats_upgrade_v30_schema_compatible()
        && xz_visit_stats_upgrade_v4_schema_compatible();
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
