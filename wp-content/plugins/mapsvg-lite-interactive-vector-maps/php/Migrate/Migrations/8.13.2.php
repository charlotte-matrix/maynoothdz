<?php

namespace MapSVG;

return function () {

    $db = Database::get();

    $schemaTableName = $db->mapsvg_prefix . 'schema';

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
};
