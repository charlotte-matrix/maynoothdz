<?php

namespace MapSVG;

/**
 */
return function () {

    $db = Database::get();

    $addFieldsToSchema = function () use ($db) {
        $schemaTableName = $db->mapsvg_prefix . "schema";
        $schemaTableExists = $db->get_var("SHOW TABLES LIKE '{$schemaTableName}'");

        if (!$schemaTableExists) {
            throw new \Exception('Schema table does not exist');
        }

        // Define the columns to add
        $columnsToAdd = [
            'postType' => 'VARCHAR(255)',
        ];

        // Get the existing columns from the table
        $existingColumnsQuery = "SHOW COLUMNS FROM `{$schemaTableName}`";
        $existingColumnsResult = $db->get_results($existingColumnsQuery);

        // Fetch the existing columns
        $existingColumns = [];
        if ($existingColumnsResult) {
            foreach ($existingColumnsResult as $row) {
                $existingColumns[] = $row->Field; // Assuming $row is an object
            }
        }

        // Prepare the ALTER TABLE statement
        $alterTableQuery = "ALTER TABLE $schemaTableName";

        // Add columns that don't exist
        $addColumns = [];
        foreach ($columnsToAdd as $column => $type) {
            if (!in_array($column, $existingColumns)) {
                $addColumns[] = "ADD COLUMN $column $type";
            }
        }


        $alterTableQuery .= ' ' . implode(', ', $addColumns);
        try {
            $db->query('START TRANSACTION');

            $result1 = $db->query($alterTableQuery);
            if ($result1 === false) {
                throw new \Exception('Failed to execute ALTER TABLE query');
            }

            $result2 = $db->query("UPDATE {$schemaTableName} SET postType =  REPLACE(name, 'posts_', '') WHERE type = 'post'");
            if ($result2 === false) {
                throw new \Exception('Failed to execute UPDATE query');
            }

            $db->query('COMMIT');
        } catch (\Exception $e) {
            $db->query('ROLLBACK');
            throw $e;
        }
    };

    $addFieldsToSchema();
};
