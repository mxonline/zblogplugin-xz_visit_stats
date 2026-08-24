<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

RegisterPlugin('xz_visit_stats', 'ActivePlugin_xz_visit_stats');

require_once __DIR__ . '/inc/helpers.php';
require_once __DIR__ . '/inc/bot.php';
require_once __DIR__ . '/inc/ua.php';
require_once __DIR__ . '/inc/v3_dimensions.php';
require_once __DIR__ . '/inc/install.php';
require_once __DIR__ . '/inc/settings.php';
require_once __DIR__ . '/inc/stats.php';
require_once __DIR__ . '/inc/maintenance.php';
require_once __DIR__ . '/inc/rollup.php';
require_once __DIR__ . '/inc/query.php';
require_once __DIR__ . '/inc/query_v2.php';
require_once __DIR__ . '/inc/v3_query.php';
require_once __DIR__ . '/inc/collector.php';
require_once __DIR__ . '/inc/upgrade/runner.php';

function ActivePlugin_xz_visit_stats()
{
    xz_visit_stats_upgrade_run();
    xz_visit_stats_ensure_secret();
    xz_visit_stats_ensure_settings();
    Add_Filter_Plugin('Filter_Plugin_Zbp_Terminate', 'xz_visit_stats_collect');
    Add_Filter_Plugin('Filter_Plugin_Zbp_Terminate', 'xz_visit_stats_maintenance_auto_cleanup');
    Add_Filter_Plugin('Filter_Plugin_Admin_LeftMenu', 'xz_visit_stats_admin_menu');
}

function xz_visit_stats_admin_menu(&$menus)
{
    global $zbp;

    $menus['nav_xz_visit_stats'] = MakeLeftMenu(
        'root',
        '访问统计',
        $zbp->host . 'zb_users/plugin/xz_visit_stats/main.php',
        'nav_xz_visit_stats',
        'a_xz_visit_stats',
        '',
        'icon-bar-chart-fill'
    );
}

function InstallPlugin_xz_visit_stats()
{
    xz_visit_stats_install_table();
    xz_visit_stats_upgrade_run();
    xz_visit_stats_ensure_secret();
    xz_visit_stats_ensure_settings();
}

function UninstallPlugin_xz_visit_stats()
{
    // Intentionally preserve the historical visit table on uninstall.
}
