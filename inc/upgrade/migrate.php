<?php

if (!defined('ZBP_PATH')) {
    exit('Access denied');
}

function xz_visit_stats_upgrade_quote_table($table)
{
    return '`' . str_replace('`', '``', $table) . '`';
}

function xz_visit_stats_upgrade_column_exists($table, $column)
{
    global $zbp;

    $rows = (array) $zbp->db->Query('SHOW COLUMNS FROM ' . xz_visit_stats_upgrade_quote_table($table));
    foreach ($rows as $row) {
        if (isset($row['Field']) && $row['Field'] === $column) {
            return true;
        }
    }

    return false;
}

function xz_visit_stats_upgrade_create_rollup_daily()
{
    global $zbp;

    $tableName = xz_visit_stats_rollup_table();
    if ($zbp->db->ExistTable($tableName)) {
        return;
    }
    $table = xz_visit_stats_upgrade_quote_table($tableName);
    $zbp->db->Query("CREATE TABLE {$table} (
        rd_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        rd_Day CHAR(10) NOT NULL,
        rd_Dimension VARCHAR(24) NOT NULL,
        rd_KeyHash CHAR(64) NOT NULL,
        rd_Key VARCHAR(512) NOT NULL DEFAULT '',
        rd_VisitorPV BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rd_VisitorUV BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rd_VisitorIP BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rd_BotPV BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rd_Error4xx BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rd_Error5xx BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rd_DurationSum BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rd_DurationCount BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rd_LastVisitAt BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rd_UpdatedAt BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (rd_ID),
        UNIQUE KEY xzvs_rollup_day_dimension (rd_Day,rd_Dimension,rd_KeyHash),
        KEY xzvs_rollup_day (rd_Day),
        KEY xzvs_rollup_dimension (rd_Dimension,rd_Day)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
}

function xz_visit_stats_upgrade_create_rollup_state()
{
    global $zbp;

    $tableName = xz_visit_stats_rollup_state_table();
    if ($zbp->db->ExistTable($tableName)) {
        if (!xz_visit_stats_upgrade_column_exists($tableName, 'rs_BackfillCursor')) {
            $zbp->db->Query('ALTER TABLE ' . xz_visit_stats_upgrade_quote_table($tableName) . ' ADD COLUMN rs_BackfillCursor BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER rs_BackfillDay');
        }
        return;
    }
    $table = xz_visit_stats_upgrade_quote_table($tableName);
    $zbp->db->Query("CREATE TABLE {$table} (
        rs_Name VARCHAR(64) NOT NULL,
        rs_LastCompletedDay CHAR(10) NOT NULL DEFAULT '',
        rs_BackfillDay CHAR(10) NOT NULL DEFAULT '',
        rs_BackfillCursor BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rs_Timezone VARCHAR(128) NOT NULL DEFAULT '',
        rs_LastRunAt BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rs_Status VARCHAR(24) NOT NULL DEFAULT 'idle',
        rs_LastError TEXT NOT NULL,
        rs_UpdatedAt BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (rs_Name)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
}

function xz_visit_stats_upgrade_add_path_key()
{
    global $zbp;

    $tableName = xz_visit_stats_physical_table();
    $table = xz_visit_stats_upgrade_quote_table($tableName);
    if (!xz_visit_stats_upgrade_column_exists($tableName, 'vs_PathKey')) {
        $zbp->db->Query("ALTER TABLE {$table} ADD COLUMN vs_PathKey CHAR(64) NOT NULL DEFAULT '' AFTER vs_Path");
    }
    $indexes = (array) $zbp->db->Query('SHOW INDEX FROM ' . $table);
    foreach ($indexes as $index) {
        if (isset($index['Key_name']) && $index['Key_name'] === 'xzvs_pathkey_time') {
            return;
        }
    }
    $zbp->db->Query("ALTER TABLE {$table} ADD INDEX xzvs_pathkey_time (vs_PathKey,vs_VisitedAt)");
}

function xz_visit_stats_upgrade_migrate_to_20()
{
    xz_visit_stats_upgrade_create_rollup_daily();
    xz_visit_stats_upgrade_create_rollup_state();
    xz_visit_stats_upgrade_add_path_key();

    return true;
}
