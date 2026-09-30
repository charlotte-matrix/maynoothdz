<?php

namespace MapSVG;

/**
 */
return function () {

    $db = Database::get();

    $createTableTokens = function () use ($db) {
        $tableName = esc_sql($db->mapsvg_prefix . "tokens");
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` INT(11) AUTO_INCREMENT PRIMARY KEY,
            `tokenFirstFour` CHAR(4) NOT NULL,
            `hashedToken` CHAR(32) NOT NULL,            
            `accessRights` TEXT NOT NULL,
            `createdAt` DATETIME NOT NULL,
            `lastUsedAt` DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        $db->query($sql);
        if ($db->last_error) {
            Logger::error("[SERVER-011] Error creating tokens table: " . $db->last_error . " — Read more: https://mapsvg.com/docs/errors#SERVER-011");
        } else {
            // Check if the table was actually created
            $tableExists = $db->get_var("SHOW TABLES LIKE '{$tableName}'");
            if (!$tableExists) {
                Logger::error("[SERVER-012] Failed to create tokens table. Table does not exist after creation attempt. Read more: https://mapsvg.com/docs/errors#SERVER-012");
            }
        }
    };

    $createTableTokens();
};
