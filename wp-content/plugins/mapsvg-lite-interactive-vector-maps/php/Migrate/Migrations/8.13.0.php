<?php

namespace MapSVG;

return function () {

    // Orphaned WP-Cron from mapsvg.php (Jun–Jul 2024): daily hook `mapsvg_check_updates_event`
    // was removed in favor of YahnisElsts plugin-update-checker + admin-only checkUpdates().
    // See git: 052fc960 (removed), e0b86e33 (introduced daily schedule).
    if (function_exists('wp_clear_scheduled_hook')) {
        wp_clear_scheduled_hook('mapsvg_check_updates_event');
    }

    $db = Database::get();

    $schemaTableName = $db->mapsvg_prefix . "schema";

    if (!$db->get_var("SHOW TABLES LIKE '{$schemaTableName}'")) {
        return;
    }

    if (!$db->get_var("SHOW TABLES LIKE '{$schemaTableName}'")) {
        return;
    }

    $existingColumns = array_column(
        $db->get_results("SHOW COLUMNS FROM `{$schemaTableName}`"),
        'Field'
    );

    if (in_array('primaryKeyField', $existingColumns, true)) {
        return;
    }

    $db->query(
        "ALTER TABLE `{$schemaTableName}` ADD COLUMN `primaryKeyField` VARCHAR(50) NOT NULL DEFAULT 'id'"
    );

    // Create import settings table (separate from schema metadata).
    $importSettingsTable = $db->mapsvg_prefix . 'import_settings';
    $db->query("
        CREATE TABLE IF NOT EXISTS `{$importSettingsTable}` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `schema_id` INT(11) NOT NULL,
            `schema_name` VARCHAR(100) NOT NULL,
            `gsSync` TINYINT(1) NOT NULL DEFAULT 0,
            `gsAutoRefetch` TINYINT(1) NOT NULL DEFAULT 0,
            `gsSyncMode` VARCHAR(2) NOT NULL DEFAULT 'r',
            `gsCsvUrl` VARCHAR(500) NULL,
            `gsCsvHash` CHAR(32) NULL,
            `gsRefetchInterval` INT NOT NULL DEFAULT 24,
            `gsAutoId` TINYINT(1) NOT NULL DEFAULT 0,
            `gsIdFieldName` VARCHAR(50) NOT NULL DEFAULT '',
            `gsSheetName` VARCHAR(100) NOT NULL DEFAULT 'Sheet1',
            `gsGeocode` TINYINT(1) NOT NULL DEFAULT 0,
            `gsGeocodeConvertLatLngToAddress` TINYINT(1) NOT NULL DEFAULT 0,
            `gsGeocodeConvertAddressToLatLng` TINYINT(1) NOT NULL DEFAULT 1,
            `gsPaidGeocoding` TINYINT(1) NOT NULL DEFAULT 0,
            `gsAppScriptUrl` VARCHAR(500) NULL,
            `gsSecret` TEXT NULL,
            `gsImportFinishedAt` DATETIME NULL,
            `gsImportStartedAt` DATETIME NULL,
            `gsImportLastUpdatedAt` DATETIME NULL,
            `gsImportEstimatedSeconds` INT UNSIGNED NULL,
            `gsImportSource` VARCHAR(10) NOT NULL DEFAULT 'upload',
            `gsImportSourceValid` TINYINT(1) NOT NULL DEFAULT 0,
            `gsImportSkipFields` TEXT NULL,
            `preflightToken` VARCHAR(64) NULL,
            `preflightStatus` VARCHAR(20) NULL,
            `preflightExpiresAt` DATETIME NULL,
            `preflightFilePath` TEXT NULL,
            `preflightFileHash` CHAR(32) NULL,
            `preflightMeta` LONGTEXT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_schema_id` (`schema_id`),
            KEY `idx_schema_name` (`schema_name`),
            KEY `idx_preflight_token` (`preflightToken`),
            KEY `idx_preflight_status_expires` (`preflightStatus`, `preflightExpiresAt`)
        ) {$db->db->get_charset_collate()}
    ");

    if ($db->get_var("SHOW TABLES LIKE '{$importSettingsTable}'")) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix
        $gsIdCol = $db->get_row("SHOW COLUMNS FROM `{$importSettingsTable}` WHERE Field = 'gsIdFieldName'");
        if ($gsIdCol && isset($gsIdCol->Default) && $gsIdCol->Default === 'id') {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix
            $db->query(
                "ALTER TABLE `{$importSettingsTable}` MODIFY COLUMN `gsIdFieldName` VARCHAR(50) NOT NULL DEFAULT ''"
            );
        }
    }

    // Add type/schemaId to tokens table (for future two-way sync)
    $tokensTableName = $db->mapsvg_prefix . "tokens";

    if (!$db->get_var("SHOW TABLES LIKE '{$tokensTableName}'")) {
        return;
    }

    $tokenColumnsToAdd = [
        'type'     => "VARCHAR(20) NOT NULL DEFAULT 'token'",
        'schemaId' => "INT NULL",
    ];

    $existingTokenColumns = array_column(
        $db->get_results("SHOW COLUMNS FROM `{$tokensTableName}`"),
        'Field'
    );

    $tokenParts = [];
    foreach ($tokenColumnsToAdd as $column => $type) {
        if (!in_array($column, $existingTokenColumns)) {
            $tokenParts[] = "ADD COLUMN `{$column}` {$type}";
        }
    }

    if (!empty($tokenParts)) {
        $db->query("ALTER TABLE `{$tokensTableName}` " . implode(', ', $tokenParts));
    }

    // Create import logs table
    $logsTableName = $db->mapsvg_prefix . "import_logs";

    $db->query("
        CREATE TABLE IF NOT EXISTS `{$logsTableName}` (
            `id`         VARCHAR(64)  NOT NULL,
            `schemaName` VARCHAR(100) NOT NULL,
            `createdAt`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `message`    TEXT         NOT NULL,
            `type`       VARCHAR(20)  NOT NULL DEFAULT 'info',
            `counter`    INT          NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`),
            KEY `schemaName` (`schemaName`)
        ) {$db->db->get_charset_collate()}
    ");

    // Create geocoding cache table
    $geocodingCacheTable = $db->mapsvg_prefix . "geocoding_cache";

    $db->query("
        CREATE TABLE IF NOT EXISTS `{$geocodingCacheTable}` (
            `request`  VARCHAR(500) NOT NULL,
            `response` LONGTEXT     NOT NULL,
            PRIMARY KEY (`request`)
        ) {$db->db->get_charset_collate()}
    ");

    // Widen r2o.object_id from INT to VARCHAR to support string primary keys
    $r2oTable = $db->mapsvg_prefix . 'r2o';

    if ($db->get_var("SHOW TABLES LIKE '{$r2oTable}'")) {
        $r2oColumn = $db->get_row("SHOW COLUMNS FROM `{$r2oTable}` LIKE 'object_id'");
        if ($r2oColumn && stripos($r2oColumn->Type, 'varchar') === false) {
            $db->query("ALTER TABLE `{$r2oTable}` MODIFY COLUMN `object_id` VARCHAR(255) NOT NULL");
        }
    }

    // Add location geocoding status only for schemas that contain a location field.
    $schemasWithLocation = $db->get_results(
        $db->prepare(
            "SELECT name FROM `{$schemaTableName}` WHERE fields LIKE %s",
            '%"location"%'
        ),
        ARRAY_A
    );

    if (!empty($schemasWithLocation)) {
        foreach ($schemasWithLocation as $schema) {
            if (empty($schema['name'])) {
                continue;
            }

            $tableName = $db->mapsvg_prefix . $schema['name'];
            if (!$db->get_var("SHOW TABLES LIKE '" . esc_sql($tableName) . "'")) {
                continue;
            }

            $fields = array_column(
                $db->get_results("SHOW COLUMNS FROM `" . esc_sql($tableName) . "`", ARRAY_A),
                'Field'
            );

            if (!in_array('location_geocoding_status', $fields, true)) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table from schema, escaped
                $db->query("ALTER TABLE `" . esc_sql($tableName) . "` ADD COLUMN `location_geocoding_status` TINYINT UNSIGNED NOT NULL DEFAULT 6");
            }
        }
    }
};
