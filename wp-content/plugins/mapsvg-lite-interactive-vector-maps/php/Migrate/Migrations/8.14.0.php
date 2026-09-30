<?php

namespace MapSVG;

return function () {

    $db = Database::get();

    $schemaTableName = $db->mapsvg_prefix . 'schema';

    if (!$db->get_var("SHOW TABLES LIKE '{$schemaTableName}'")) {
        return;
    }

    $columns = array_column(
        $db->get_results("SHOW COLUMNS FROM `{$schemaTableName}`"),
        'Field'
    );

    if (!in_array('postType', $columns, true) || !in_array('type', $columns, true) || !in_array('name', $columns, true)) {
        return;
    }

    // Derive postType from legacy table names like posts_movie → movie
    $db->query(
        "UPDATE `{$schemaTableName}` SET `postType` = REPLACE(`name`, 'posts_', '') WHERE `type` = 'post' AND (`postType` IS NULL OR `postType` = '')"
    );
};
