<?php

namespace MapSVG;

return function () {

	$db = Database::get();

	// Soft domain-limit warning JSON exceeds legacy varchar(100).
	$settingsTable = $db->mapsvg_prefix . 'settings';
	if ($db->get_var("SHOW TABLES LIKE '{$settingsTable}'")) {
		$col = $db->get_row("SHOW COLUMNS FROM `{$settingsTable}` LIKE 'value'", ARRAY_A);
		$type = strtolower((string) ($col['Type'] ?? ''));
		if ($col && strpos($type, 'varchar') !== false) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix
			$db->query("ALTER TABLE `{$settingsTable}` MODIFY COLUMN `value` TEXT NOT NULL");
		}
	}

	$schemaTableName = $db->mapsvg_prefix . 'schema';
	if (!$db->get_var("SHOW TABLES LIKE '{$schemaTableName}'")) {
		return;
	}

	$orphanedField = array(
		'name'       => 'orphaned',
		'label'      => 'Orphaned',
		'type'       => 'checkbox',
		'db_type'    => 'tinyint(1)',
		'visible'    => false,
		'protected'  => true,
		'db_default' => 0,
	);

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from prefix
	$schemas = $db->get_results(
		"SELECT `id`, `name`, `fields` FROM `{$schemaTableName}` WHERE `name` LIKE 'regions\\_%'",
		ARRAY_A
	);

	if (empty($schemas)) {
		return;
	}

	foreach ($schemas as $schemaRow) {
		$tableName = $db->mapsvg_prefix . $schemaRow['name'];

		if (!$db->get_var("SHOW TABLES LIKE '" . esc_sql($tableName) . "'")) {
			continue;
		}

		$columnExists = $db->get_var(
			"SHOW COLUMNS FROM `" . esc_sql($tableName) . "` LIKE 'orphaned'"
		);
		if (!$columnExists) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name guarded above
			$db->query(
				"ALTER TABLE `" . esc_sql($tableName) . "` ADD COLUMN `orphaned` tinyint(1) NOT NULL DEFAULT 0"
			);
		}

		$fields = json_decode($schemaRow['fields'], true);
		if (!is_array($fields)) {
			$fields = array();
		}

		$hasOrphanedField = false;
		foreach ($fields as $field) {
			$name = is_array($field) ? ($field['name'] ?? '') : (isset($field->name) ? $field->name : '');
			if ($name === 'orphaned') {
				$hasOrphanedField = true;
				break;
			}
		}

		if (!$hasOrphanedField) {
			$fields[] = $orphanedField;
			$db->update(
				$schemaTableName,
				array('fields' => wp_json_encode($fields, JSON_UNESCAPED_UNICODE)),
				array('id' => (int) $schemaRow['id'])
			);
		}
	}
};
