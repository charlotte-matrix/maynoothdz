<?php

namespace MapSVG;

/**
 * Defense-in-depth policy for which wp_posts columns
 * /post-types/{type}/field/{column} may query.
 *
 * Those routes require current_user_can('edit_posts'). Existence in the table
 * is still not enough: published password-protected posts keep
 * post_status = 'publish', so columns such as post_password and post_content
 * must never be selectable even for an editor.
 */
class PublicPostColumns
{
	/**
	 * Columns that must never be returned, even if a caller later adds them
	 * to the allow list.
	 *
	 * @return string[]
	 */
	public static function sensitive(): array
	{
		return array(
			'post_password',
			'post_content',
			'post_content_filtered',
			'post_excerpt',
			'to_ping',
			'pinged',
		);
	}

	/**
	 * Public-safe columns suitable for filter option lists.
	 *
	 * @return string[]
	 */
	public static function allowed(): array
	{
		return array(
			'ID',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_title',
			'post_status',
			'comment_status',
			'ping_status',
			'post_name',
			'post_modified',
			'post_modified_gmt',
			'post_parent',
			'menu_order',
			'post_type',
			'post_mime_type',
			'comment_count',
		);
	}

	/**
	 * Canonical wp_posts column name if $fieldName may be queried.
	 * Comparison is case-insensitive; the returned name is the allow-list spelling.
	 *
	 * @param mixed $fieldName
	 * @return string|null
	 */
	public static function canonicalName($fieldName): ?string
	{
		if (!is_string($fieldName) || $fieldName === '') {
			return null;
		}

		foreach (self::sensitive() as $column) {
			if (strcasecmp($column, $fieldName) === 0) {
				return null;
			}
		}

		foreach (self::allowed() as $column) {
			if (strcasecmp($column, $fieldName) === 0) {
				return $column;
			}
		}

		return null;
	}

	/**
	 * True when $fieldName may be queried by the field-values route.
	 *
	 * @param mixed $fieldName
	 * @return bool
	 */
	public static function isAllowed($fieldName): bool
	{
		return self::canonicalName($fieldName) !== null;
	}
}
