<?php

namespace MapSVG;

/**
 * Helpers for orphaned-region cleanup (pure logic, unit-testable without WP).
 */
class RegionOrphanCleanup
{
	/**
	 * Collect region IDs that should be permanently deleted by deleteOrphaned().
	 *
	 * @param array<int, object|array> $rows Region rows (objects or assoc arrays) with id + orphaned.
	 * @return string[]
	 */
	public static function orphanIdsFromRows(array $rows): array
	{
		$ids = array();
		foreach ($rows as $row) {
			if (is_object($row)) {
				$id = isset($row->id) ? (string) $row->id : '';
				$orphaned = !empty($row->orphaned);
			} elseif (is_array($row)) {
				$id = isset($row['id']) ? (string) $row['id'] : '';
				$orphaned = !empty($row['orphaned']);
			} else {
				continue;
			}

			if ($id !== '' && $orphaned) {
				$ids[] = $id;
			}
		}

		return array_values(array_unique($ids));
	}
}
