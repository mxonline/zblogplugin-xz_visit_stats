<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_upgrade_migrate_to_20()
{
    // v2.0 migration steps will be added incrementally.
    // Existing xz_visit_stats_log data must remain untouched.
    return true;
}
