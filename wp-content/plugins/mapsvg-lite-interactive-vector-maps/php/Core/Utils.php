<?php

namespace MapSVG;

/**
 * Shared SQL / identifier safety helpers.
 */
class Utils
{
	/**
	 * Cached column names per table for the current request.
	 *
	 * @var array<string, string[]>
	 */
	private static $tableColumns = [];

	/**
	 * True if value matches a safe WP slug / REST path segment (a-zA-Z0-9_-).
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function isSafeSlug($value): bool
	{
		return is_string($value) && $value !== '' && preg_match('/^[a-zA-Z0-9_-]+$/', $value) === 1;
	}

	/**
	 * True if value is a safe SQL identifier (letters, digits, underscore).
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function isSafeSqlIdentifier($value): bool
	{
		return is_string($value) && $value !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $value) === 1;
	}

	/**
	 * Returns true when $fieldName is an existing column on $tableName.
	 * Columns are read from the DB (not hardcoded).
	 *
	 * @param string $tableName Full table name (e.g. wp_posts, wp_mapsvg6_objects_1).
	 * @param string $fieldName Column name to check.
	 * @return bool
	 */
	public static function isTableColumn($tableName, $fieldName): bool
	{
		if (!self::isSafeSqlIdentifier($tableName) || !self::isSafeSqlIdentifier($fieldName)) {
			return false;
		}

		if (!isset(self::$tableColumns[$tableName])) {
			$db = Database::get();
			// DESCRIBE first column is Field name
			$columns = $db->get_col("DESCRIBE `{$tableName}`", 0);
			self::$tableColumns[$tableName] = is_array($columns) ? $columns : [];
		}

		return in_array($fieldName, self::$tableColumns[$tableName], true);
	}
}
