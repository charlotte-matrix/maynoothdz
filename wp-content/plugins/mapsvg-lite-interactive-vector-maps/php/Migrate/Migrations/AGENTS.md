# Migrations

1. New migrations must be added to the file with pending migrations `next.php`.

2. The upgrade system runs all files whose version is greater than the stored `db_version`, in ascending version order.

3. **Use this minimal template:**

```php
<?php

namespace MapSVG;

return function () {

    $db = Database::get();

    // your migration code here
};
```

3. **Adding columns to the `schema` table** (most common task):

```php
return function () {

    $db = Database::get();

    $schemaTableName = $db->mapsvg_prefix . "schema";
    if (!$db->get_var("SHOW TABLES LIKE '{$schemaTableName}'")) {
        return;
    }

    $columnsToAdd = [
        'myNewColumn' => 'VARCHAR(255)',
        'myJsonColumn' => 'LONGTEXT',
    ];

    $existingColumns = array_column(
        $db->get_results("SHOW COLUMNS FROM `{$schemaTableName}`"),
        'Field'
    );

    $parts = [];
    foreach ($columnsToAdd as $column => $type) {
        if (!in_array($column, $existingColumns)) {
            $parts[] = "ADD COLUMN `{$column}` {$type}";
        }
    }

    if (!empty($parts)) {
        $db->query("ALTER TABLE `{$schemaTableName}` " . implode(', ', $parts));
    }
};
```

4. **Rules:**
   - Always guard with `SHOW TABLES LIKE` before touching any table.
   - Always check each column with `SHOW COLUMNS` before adding it (idempotent).
   - Use `$db->mapsvg_prefix` for the table prefix, never hardcode it.
   - Do not use the ORM (`RepositoryFactory`) for structural changes — use raw `$db->query()`.
   - Keep migrations small and focused on one concern.
   - When adding a migration to `next.php` that adds changes to a table (e.g. adding new columns), first check if there is already an ALTER TABLE statement for that table in the migration file. Extend the existing SQL query instead of adding a completely new one.
