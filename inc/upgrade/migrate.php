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

function xz_visit_stats_upgrade_create_rum()
{
    global $zbp;

    $tableName = xz_visit_stats_rum_table();
    if ($zbp->db->ExistTable($tableName)) {
        xz_visit_stats_migration_assert_columns($tableName, array(
            'rum_Path' => 'varchar(2048)', 'rum_PathKey' => 'char(64)',
            'rum_LCP' => 'decimal', 'rum_INP' => 'decimal', 'rum_CLS' => 'decimal',
            'rum_TTFB' => 'decimal', 'rum_FCP' => 'decimal', 'rum_VisitedAt' => 'bigint',
        ));
        xz_visit_stats_upgrade_make_rum_metrics_nullable($tableName);
        return;
    }
    $table = xz_visit_stats_upgrade_quote_table($tableName);
    $zbp->db->Query("CREATE TABLE {$table} (
        rum_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        rum_VisitorHash CHAR(64) NOT NULL DEFAULT '', rum_Path VARCHAR(2048) NOT NULL DEFAULT '/', rum_PathKey CHAR(64) NOT NULL DEFAULT '',
        rum_Browser VARCHAR(64) NOT NULL DEFAULT '', rum_Os VARCHAR(64) NOT NULL DEFAULT '', rum_Device VARCHAR(32) NOT NULL DEFAULT '',
        rum_Language VARCHAR(32) NOT NULL DEFAULT '', rum_Screen VARCHAR(32) NOT NULL DEFAULT '', rum_Viewport VARCHAR(32) NOT NULL DEFAULT '',
        rum_LCP DECIMAL(10,2) NULL DEFAULT NULL, rum_INP DECIMAL(10,2) NULL DEFAULT NULL, rum_CLS DECIMAL(10,4) NULL DEFAULT NULL,
        rum_TTFB DECIMAL(10,2) NULL DEFAULT NULL, rum_FCP DECIMAL(10,2) NULL DEFAULT NULL, rum_VisitedAt BIGINT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (rum_ID), KEY xzvs_rum_time (rum_VisitedAt), KEY xzvs_rum_path_time (rum_PathKey,rum_VisitedAt)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
}

function xz_visit_stats_upgrade_make_rum_metrics_nullable($tableName)
{
    global $zbp;

    $table = xz_visit_stats_upgrade_quote_table($tableName);
    $columns = (array) $zbp->db->Query('SHOW COLUMNS FROM ' . $table);
    $required = array('rum_LCP' => 'DECIMAL(10,2)', 'rum_INP' => 'DECIMAL(10,2)', 'rum_CLS' => 'DECIMAL(10,4)', 'rum_TTFB' => 'DECIMAL(10,2)', 'rum_FCP' => 'DECIMAL(10,2)');
    $operations = array();
    foreach ($columns as $column) {
        $name = isset($column['Field']) ? (string) $column['Field'] : '';
        if (!isset($required[$name]) || strtoupper((string) (isset($column['Null']) ? $column['Null'] : 'YES')) === 'YES') {
            continue;
        }
        $operations[] = 'MODIFY `' . $name . '` ' . $required[$name] . ' NULL DEFAULT NULL';
    }
    if (!empty($operations)) {
        $zbp->db->Query('ALTER TABLE ' . $table . ' ' . implode(', ', $operations));
    }
}

function xz_visit_stats_upgrade_rum_metrics_nullable($tableName)
{
    global $zbp;

    $required = array('rum_LCP', 'rum_INP', 'rum_CLS', 'rum_TTFB', 'rum_FCP');
    $columns = (array) $zbp->db->Query('SHOW COLUMNS FROM ' . xz_visit_stats_upgrade_quote_table($tableName));
    $seen = array();
    foreach ($columns as $column) {
        if (isset($column['Field'])) {
            $seen[(string) $column['Field']] = strtoupper((string) (isset($column['Null']) ? $column['Null'] : 'YES')) === 'YES';
        }
    }
    foreach ($required as $name) {
        if (!isset($seen[$name]) || !$seen[$name]) {
            return false;
        }
    }
    return true;
}

function xz_visit_stats_upgrade_migrate_to_30()
{
    xz_visit_stats_upgrade_migrate_to_20();
    xz_visit_stats_upgrade_add_v30_columns();
    xz_visit_stats_upgrade_create_hourly();
    xz_visit_stats_upgrade_create_saved_filters();
    xz_visit_stats_upgrade_create_rum();
    return true;
}

function xz_visit_stats_upgrade_v30_schema_compatible()
{
    try {
        xz_visit_stats_migration_assert_columns(xz_visit_stats_physical_table(), array('vs_PathKey' => 'char(64)', 'vs_SourceType' => 'varchar(24)', 'vs_SourceDomain' => 'varchar(253)', 'vs_UtmCampaign' => 'varchar(255)'));
        xz_visit_stats_migration_assert_columns(xz_visit_stats_rollup_table(), array('rd_Day' => 'char(10)', 'rd_Dimension' => 'varchar(24)'));
        xz_visit_stats_migration_assert_columns(xz_visit_stats_rollup_hourly_table(), array('rh_Hour' => 'char(16)', 'rh_Dimension' => 'varchar(24)'));
        xz_visit_stats_migration_assert_columns(xz_visit_stats_saved_filters_table(), array('sf_UserID' => 'bigint', 'sf_Filters' => 'text'));
        xz_visit_stats_migration_assert_columns(xz_visit_stats_rum_table(), array('rum_Path' => 'varchar(2048)', 'rum_PathKey' => 'char(64)', 'rum_LCP' => 'decimal', 'rum_INP' => 'decimal', 'rum_CLS' => 'decimal', 'rum_TTFB' => 'decimal', 'rum_FCP' => 'decimal', 'rum_VisitedAt' => 'bigint'));
        xz_visit_stats_migration_assert_index(xz_visit_stats_physical_table(), 'xzvs_source_time', array('vs_SourceType', 'vs_VisitedAt'));
        xz_visit_stats_migration_assert_index(xz_visit_stats_physical_table(), 'xzvs_domain_time', array('vs_SourceDomain(191)', 'vs_VisitedAt'));
        xz_visit_stats_migration_assert_index(xz_visit_stats_physical_table(), 'xzvs_campaign_time', array('vs_UtmCampaign(191)', 'vs_VisitedAt'));
        return true;
    } catch (Exception $exception) {
        return false;
    }
}

function xz_visit_stats_v4_schema_definitions()
{
    return array(
        'sessions' => array('table' => 'xz_visit_stats_sessions', 'sql' => "se_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,se_SessionKey CHAR(64) NOT NULL,se_VisitorHash CHAR(64) NOT NULL,se_StartedAt BIGINT UNSIGNED NOT NULL,se_LastSeenAt BIGINT UNSIGNED NOT NULL,se_EntryPathKey CHAR(64) NOT NULL DEFAULT '',se_ExitPathKey CHAR(64) NOT NULL DEFAULT '',se_PageCount BIGINT UNSIGNED NOT NULL DEFAULT 0,se_DurationMs BIGINT UNSIGNED NULL DEFAULT NULL,se_IsBounce TINYINT(1) NOT NULL DEFAULT 0,se_SourceType VARCHAR(24) NOT NULL DEFAULT '',se_SourceDomain VARCHAR(253) NOT NULL DEFAULT '',se_UpdatedAt BIGINT UNSIGNED NOT NULL DEFAULT 0,PRIMARY KEY (se_ID),UNIQUE KEY xzvs_session_key (se_SessionKey),KEY xzvs_session_visitor_time (se_VisitorHash,se_LastSeenAt),KEY xzvs_session_time (se_StartedAt,se_LastSeenAt),KEY xzvs_session_entry_time (se_EntryPathKey,se_StartedAt)", 'columns' => array('se_SessionKey' => 'char(64)', 'se_VisitorHash' => 'char(64)', 'se_StartedAt' => 'bigint', 'se_LastSeenAt' => 'bigint', 'se_DurationMs' => 'bigint'), 'indexes' => array('xzvs_session_key' => array('se_SessionKey'), 'xzvs_session_visitor_time' => array('se_VisitorHash', 'se_LastSeenAt'))),
        'session_pages' => array('table' => 'xz_visit_stats_session_pages', 'sql' => "sp_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,sp_SessionID BIGINT UNSIGNED NOT NULL,sp_LogID BIGINT UNSIGNED NULL DEFAULT NULL,sp_Sequence BIGINT UNSIGNED NOT NULL,sp_PathKey CHAR(64) NOT NULL,sp_Path VARCHAR(2048) NOT NULL DEFAULT '/',sp_EnteredAt BIGINT UNSIGNED NOT NULL,sp_LeftAt BIGINT UNSIGNED NULL DEFAULT NULL,sp_DurationMs BIGINT UNSIGNED NULL DEFAULT NULL,sp_ExitReason VARCHAR(24) NOT NULL DEFAULT '',sp_UpdatedAt BIGINT UNSIGNED NOT NULL DEFAULT 0,PRIMARY KEY (sp_ID),UNIQUE KEY xzvs_page_sequence (sp_SessionID,sp_Sequence),KEY xzvs_page_session_time (sp_SessionID,sp_EnteredAt),KEY xzvs_page_path_time (sp_PathKey,sp_EnteredAt),KEY xzvs_page_log (sp_LogID)", 'columns' => array('sp_SessionID' => 'bigint', 'sp_Sequence' => 'bigint', 'sp_PathKey' => 'char(64)', 'sp_DurationMs' => 'bigint'), 'indexes' => array('xzvs_page_sequence' => array('sp_SessionID', 'sp_Sequence'), 'xzvs_page_session_time' => array('sp_SessionID', 'sp_EnteredAt'))),
        'events' => array('table' => 'xz_visit_stats_events', 'sql' => "ev_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,ev_SessionID BIGINT UNSIGNED NULL DEFAULT NULL,ev_VisitorHash CHAR(64) NOT NULL DEFAULT '',ev_Name VARCHAR(128) NOT NULL,ev_Params TEXT NOT NULL,ev_PathKey CHAR(64) NOT NULL,ev_TriggeredAt BIGINT UNSIGNED NOT NULL,ev_UpdatedAt BIGINT UNSIGNED NOT NULL DEFAULT 0,PRIMARY KEY (ev_ID),KEY xzvs_event_name_time (ev_Name,ev_TriggeredAt),KEY xzvs_event_session_time (ev_SessionID,ev_TriggeredAt),KEY xzvs_event_visitor_time (ev_VisitorHash,ev_TriggeredAt),KEY xzvs_event_path_time (ev_PathKey,ev_TriggeredAt)", 'columns' => array('ev_Name' => 'varchar(128)', 'ev_VisitorHash' => 'char(64)', 'ev_Params' => 'text', 'ev_TriggeredAt' => 'bigint'), 'indexes' => array('xzvs_event_name_time' => array('ev_Name', 'ev_TriggeredAt'), 'xzvs_event_visitor_time' => array('ev_VisitorHash', 'ev_TriggeredAt'))),
        'directory_rules' => array('table' => 'xz_visit_stats_directory_rules', 'sql' => "dr_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,dr_Name VARCHAR(128) NOT NULL,dr_MatchType VARCHAR(24) NOT NULL,dr_Pattern VARCHAR(2048) NOT NULL,dr_Action VARCHAR(16) NOT NULL,dr_Enabled TINYINT(1) NOT NULL DEFAULT 1,dr_SortOrder INT NOT NULL DEFAULT 0,dr_CreatedAt BIGINT UNSIGNED NOT NULL DEFAULT 0,dr_UpdatedAt BIGINT UNSIGNED NOT NULL DEFAULT 0,PRIMARY KEY (dr_ID),KEY xzvs_directory_enabled_sort (dr_Enabled,dr_SortOrder)", 'columns' => array('dr_Name' => 'varchar(128)', 'dr_Pattern' => 'varchar(2048)', 'dr_Enabled' => 'tinyint'), 'indexes' => array('xzvs_directory_enabled_sort' => array('dr_Enabled', 'dr_SortOrder'))),
        'export_tasks' => array('table' => 'xz_visit_stats_export_tasks', 'sql' => "ex_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,ex_UserID BIGINT UNSIGNED NOT NULL DEFAULT 0,ex_Status VARCHAR(24) NOT NULL,ex_Filters TEXT NOT NULL,ex_FileName VARCHAR(255) NOT NULL DEFAULT '',ex_RequestedAt BIGINT UNSIGNED NOT NULL,ex_StartedAt BIGINT UNSIGNED NULL DEFAULT NULL,ex_FinishedAt BIGINT UNSIGNED NULL DEFAULT NULL,ex_RowCount BIGINT UNSIGNED NOT NULL DEFAULT 0,ex_ErrorCode VARCHAR(64) NOT NULL DEFAULT '',ex_UpdatedAt BIGINT UNSIGNED NOT NULL DEFAULT 0,PRIMARY KEY (ex_ID),KEY xzvs_export_user_time (ex_UserID,ex_RequestedAt),KEY xzvs_export_status_time (ex_Status,ex_RequestedAt)", 'columns' => array('ex_UserID' => 'bigint', 'ex_Status' => 'varchar(24)', 'ex_Filters' => 'text', 'ex_RequestedAt' => 'bigint'), 'indexes' => array('xzvs_export_user_time' => array('ex_UserID', 'ex_RequestedAt'), 'xzvs_export_status_time' => array('ex_Status', 'ex_RequestedAt'))),
        'ip_filters' => array('table' => 'xz_visit_stats_ip_filters', 'sql' => "if_ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,if_RuleType VARCHAR(8) NOT NULL,if_Value VARCHAR(128) NOT NULL,if_ValueHash CHAR(64) NOT NULL,if_Enabled TINYINT(1) NOT NULL DEFAULT 1,if_Note VARCHAR(255) NOT NULL DEFAULT '',if_CreatedAt BIGINT UNSIGNED NOT NULL DEFAULT 0,if_UpdatedAt BIGINT UNSIGNED NOT NULL DEFAULT 0,PRIMARY KEY (if_ID),UNIQUE KEY xzvs_ip_rule (if_RuleType,if_ValueHash),KEY xzvs_ip_enabled_type (if_Enabled,if_RuleType)", 'columns' => array('if_RuleType' => 'varchar(8)', 'if_Value' => 'varchar(128)', 'if_ValueHash' => 'char(64)', 'if_Enabled' => 'tinyint'), 'indexes' => array('xzvs_ip_rule' => array('if_RuleType', 'if_ValueHash'), 'xzvs_ip_enabled_type' => array('if_Enabled', 'if_RuleType'))),
    );
}

function xz_visit_stats_upgrade_create_v4_table($definition)
{
    global $zbp;
    $name = $zbp->db->dbpre . $definition['table'];
    if ($zbp->db->ExistTable($name)) {
        xz_visit_stats_migration_assert_columns($name, $definition['columns']);
        foreach ($definition['indexes'] as $index => $columns) xz_visit_stats_migration_assert_index($name, $index, $columns);
        return;
    }
    $zbp->db->Query('CREATE TABLE ' . xz_visit_stats_upgrade_quote_table($name) . ' (' . $definition['sql'] . ') ENGINE=MyISAM DEFAULT CHARSET=utf8mb4');
}

function xz_visit_stats_upgrade_migrate_to_40()
{
    xz_visit_stats_upgrade_migrate_to_30();
    foreach (xz_visit_stats_v4_schema_definitions() as $definition) xz_visit_stats_upgrade_create_v4_table($definition);
    return true;
}

function xz_visit_stats_upgrade_v4_schema_compatible()
{
    global $zbp;
    try {
        foreach (xz_visit_stats_v4_schema_definitions() as $definition) {
            $name = $zbp->db->dbpre . $definition['table'];
            if (!$zbp->db->ExistTable($name)) return false;
            xz_visit_stats_migration_assert_columns($name, $definition['columns']);
            foreach ($definition['indexes'] as $index => $columns) xz_visit_stats_migration_assert_index($name, $index, $columns);
        }
        return true;
    } catch (Exception $exception) { return false; }
}
