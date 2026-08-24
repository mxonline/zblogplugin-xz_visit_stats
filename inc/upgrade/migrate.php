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

function xz_visit_stats_migration_columns($table)
{
    global $zbp;
    $result = array();
    foreach ((array) $zbp->db->Query('SHOW COLUMNS FROM ' . xz_visit_stats_upgrade_quote_table($table)) as $row) {
        if (isset($row['Field'])) $result[(string) $row['Field']] = strtolower((string) (isset($row['Type']) ? $row['Type'] : ''));
    }
    return $result;
}

function xz_visit_stats_migration_assert_columns($table, $required)
{
    $columns = xz_visit_stats_migration_columns($table);
    foreach ((array) $required as $name => $type) {
        if (!isset($columns[$name])) throw new Exception('Missing existing column ' . $name . ' in ' . $table);
        if ($type !== '' && strpos($columns[$name], strtolower($type)) === false) throw new Exception('Incompatible existing column ' . $name . ' in ' . $table);
    }
    return true;
}

function xz_visit_stats_migration_index_columns($table, $name)
{
    global $zbp;
    $result = array();
    foreach ((array) $zbp->db->Query('SHOW INDEX FROM ' . xz_visit_stats_upgrade_quote_table($table)) as $row) {
        if (isset($row['Key_name']) && (string) $row['Key_name'] === $name) {
            $part = isset($row['Sub_part']) && $row['Sub_part'] !== '' ? '(' . (int) $row['Sub_part'] . ')' : '';
            $result[(int) $row['Seq_in_index']] = (string) $row['Column_name'] . $part;
        }
    }
    ksort($result);
    return array_values($result);
}

function xz_visit_stats_migration_assert_index($table, $name, $expected)
{
    $actual = xz_visit_stats_migration_index_columns($table, $name);
    if (empty($actual)) return false;
    if ($actual !== $expected) throw new Exception('Incompatible existing index ' . $name . ' in ' . $table);
    return true;
}

function xz_visit_stats_upgrade_create_rollup_daily()
{
    global $zbp;

    $tableName = xz_visit_stats_rollup_table();
    if ($zbp->db->ExistTable($tableName)) {
        xz_visit_stats_migration_assert_columns($tableName, array('rd_Day' => 'char(10)', 'rd_Dimension' => 'varchar(24)', 'rd_KeyHash' => 'char(64)', 'rd_VisitorPV' => 'bigint'));
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
        xz_visit_stats_migration_assert_columns($tableName, array('rs_Name' => 'varchar(64)', 'rs_Status' => 'varchar(24)', 'rs_Timezone' => 'varchar(128)'));
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

function xz_visit_stats_upgrade_add_v30_columns()
{
    global $zbp;

    if (!xz_visit_stats_is_mysql()) {
        return;
    }
    $tableName = xz_visit_stats_physical_table();
    $table = xz_visit_stats_upgrade_quote_table($tableName);
    $columns = array(
        'vs_SourceType' => "VARCHAR(24) NOT NULL DEFAULT ''",
        'vs_SourceDomain' => "VARCHAR(253) NOT NULL DEFAULT ''",
        'vs_AiSource' => "VARCHAR(32) NOT NULL DEFAULT ''",
        'vs_UtmSource' => "VARCHAR(128) NOT NULL DEFAULT ''",
        'vs_UtmMedium' => "VARCHAR(128) NOT NULL DEFAULT ''",
        'vs_UtmCampaign' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'vs_UtmContent' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'vs_UtmTerm' => "VARCHAR(255) NOT NULL DEFAULT ''",
        'vs_PageTitle' => "VARCHAR(512) NOT NULL DEFAULT ''",
        'vs_PostID' => "BIGINT UNSIGNED NOT NULL DEFAULT 0",
        'vs_GeoCountry' => "VARCHAR(64) NOT NULL DEFAULT ''",
        'vs_GeoRegion' => "VARCHAR(128) NOT NULL DEFAULT ''",
        'vs_AiCrawler' => "VARCHAR(32) NOT NULL DEFAULT ''",
    );
    $operations = array();
    foreach ($columns as $name => $definition) {
        if (!xz_visit_stats_upgrade_column_exists($tableName, $name)) {
            $operations[] = 'ADD COLUMN `' . $name . '` ' . $definition;
        }
    }
    if (!empty($operations)) {
        $zbp->db->Query('ALTER TABLE ' . $table . ' ' . implode(', ', $operations));
    }
    xz_visit_stats_migration_assert_columns($tableName, array(
        'vs_SourceType' => 'varchar(24)', 'vs_SourceDomain' => 'varchar(253)', 'vs_AiSource' => 'varchar(32)',
        'vs_UtmCampaign' => 'varchar(255)', 'vs_PageTitle' => 'varchar(512)', 'vs_PostID' => 'bigint',
        'vs_GeoCountry' => 'varchar(64)', 'vs_AiCrawler' => 'varchar(32)',
    ));
    $indexes = (array) $zbp->db->Query('SHOW INDEX FROM ' . $table);
    $existing = array();
    foreach ($indexes as $index) {
        if (isset($index['Key_name'])) {
            $existing[(string) $index['Key_name']] = true;
        }
    }
    $indexOps = array(
        'xzvs_source_time' => '(`vs_SourceType`,`vs_VisitedAt`)',
        'xzvs_domain_time' => '(`vs_SourceDomain`(191),`vs_VisitedAt`)',
        'xzvs_campaign_time' => '(`vs_UtmCampaign`(191),`vs_VisitedAt`)',
    );
    foreach ($indexOps as $name => $definition) {
        $expected = $name === 'xzvs_source_time' ? array('vs_SourceType', 'vs_VisitedAt') : ($name === 'xzvs_domain_time' ? array('vs_SourceDomain(191)', 'vs_VisitedAt') : array('vs_UtmCampaign(191)', 'vs_VisitedAt'));
        if (isset($existing[$name])) {
            xz_visit_stats_migration_assert_index($tableName, $name, $expected);
        } else {
            $zbp->db->Query('ALTER TABLE ' . $table . ' ADD INDEX `' . $name . '` ' . $definition);
        }
    }
}

function xz_visit_stats_upgrade_create_hourly()
{
    global $zbp;

    $tableName = xz_visit_stats_rollup_hourly_table();
    if ($zbp->db->ExistTable($tableName)) {
        xz_visit_stats_migration_assert_columns($tableName, array('rh_Hour' => 'char(16)', 'rh_Dimension' => 'varchar(24)', 'rh_KeyHash' => 'char(64)', 'rh_VisitorPV' => 'bigint'));
        $columns = (array) $zbp->db->Query('SHOW COLUMNS FROM ' . xz_visit_stats_upgrade_quote_table($tableName));
        foreach ($columns as $column) {
            if (isset($column['Field'], $column['Type']) && $column['Field'] === 'rh_Hour' && strtolower((string) $column['Type']) !== 'char(16)') {
                $zbp->db->Query('ALTER TABLE ' . xz_visit_stats_upgrade_quote_table($tableName) . ' MODIFY rh_Hour CHAR(16) NOT NULL');
            }
        }
        return;
    }
    $table = xz_visit_stats_upgrade_quote_table($tableName);
    $zbp->db->Query("CREATE TABLE {$table} (
        rh_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        rh_Hour CHAR(16) NOT NULL,
        rh_Dimension VARCHAR(24) NOT NULL,
        rh_KeyHash CHAR(64) NOT NULL,
        rh_Key VARCHAR(512) NOT NULL DEFAULT '',
        rh_VisitorPV BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rh_VisitorUV BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rh_VisitorIP BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rh_BotPV BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rh_Error4xx BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rh_Error5xx BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rh_DurationSum BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rh_DurationCount BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rh_LastVisitAt BIGINT UNSIGNED NOT NULL DEFAULT 0,
        rh_UpdatedAt BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (rh_ID),
        UNIQUE KEY xzvs_hour_dimension (rh_Hour,rh_Dimension,rh_KeyHash),
        KEY xzvs_hour (rh_Hour),
        KEY xzvs_hour_dimension_day (rh_Dimension,rh_Hour)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
}

function xz_visit_stats_upgrade_create_saved_filters()
{
    global $zbp;

    $tableName = xz_visit_stats_saved_filters_table();
    if ($zbp->db->ExistTable($tableName)) {
        xz_visit_stats_migration_assert_columns($tableName, array('sf_UserID' => 'bigint', 'sf_Name' => 'varchar(128)', 'sf_Filters' => 'text'));
        return;
    }
    $table = xz_visit_stats_upgrade_quote_table($tableName);
    $zbp->db->Query("CREATE TABLE {$table} (
        sf_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        sf_UserID BIGINT UNSIGNED NOT NULL DEFAULT 0,
        sf_Name VARCHAR(128) NOT NULL,
        sf_View VARCHAR(32) NOT NULL,
        sf_Filters TEXT NOT NULL,
        sf_CreatedAt BIGINT UNSIGNED NOT NULL DEFAULT 0,
        sf_UpdatedAt BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (sf_ID),
        KEY xzvs_saved_user (sf_UserID,sf_View)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
}

function xz_visit_stats_upgrade_migrate_to_30()
{
    xz_visit_stats_upgrade_migrate_to_20();
    xz_visit_stats_upgrade_add_v30_columns();
    xz_visit_stats_upgrade_create_hourly();
    xz_visit_stats_upgrade_create_saved_filters();
    return true;
}

function xz_visit_stats_upgrade_v30_schema_compatible()
{
    try {
        xz_visit_stats_migration_assert_columns(xz_visit_stats_physical_table(), array('vs_PathKey' => 'char(64)', 'vs_SourceType' => 'varchar(24)', 'vs_SourceDomain' => 'varchar(253)', 'vs_UtmCampaign' => 'varchar(255)'));
        xz_visit_stats_migration_assert_columns(xz_visit_stats_rollup_table(), array('rd_Day' => 'char(10)', 'rd_Dimension' => 'varchar(24)'));
        xz_visit_stats_migration_assert_columns(xz_visit_stats_rollup_hourly_table(), array('rh_Hour' => 'char(16)', 'rh_Dimension' => 'varchar(24)'));
        xz_visit_stats_migration_assert_columns(xz_visit_stats_saved_filters_table(), array('sf_UserID' => 'bigint', 'sf_Filters' => 'text'));
        xz_visit_stats_migration_assert_index(xz_visit_stats_physical_table(), 'xzvs_source_time', array('vs_SourceType', 'vs_VisitedAt'));
        xz_visit_stats_migration_assert_index(xz_visit_stats_physical_table(), 'xzvs_domain_time', array('vs_SourceDomain(191)', 'vs_VisitedAt'));
        xz_visit_stats_migration_assert_index(xz_visit_stats_physical_table(), 'xzvs_campaign_time', array('vs_UtmCampaign(191)', 'vs_VisitedAt'));
        return true;
    } catch (Exception $exception) {
        return false;
    }
}
