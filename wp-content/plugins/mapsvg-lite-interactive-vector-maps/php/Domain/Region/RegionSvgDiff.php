<?php

namespace MapSVG;

/**
 * Pure diff of region IDs between MySQL and an SVG file.
 * Used by Map::setRegionsTable to mark/restore orphans instead of hard-deleting.
 */
class RegionSvgDiff
{
	/**
	 * @param string[] $dbIds  Region IDs currently in the database (including orphans).
	 * @param string[] $svgIds Region IDs found in the SVG.
	 * @return array{toOrphan: string[], toRestore: string[], toInsert: string[]}
	 */
	public static function diff(array $dbIds, array $svgIds): array
	{
		$dbIds = array_values(array_unique(array_map('strval', $dbIds)));
		$svgIds = array_values(array_unique(array_map('strval', $svgIds)));

		return array(
			'toOrphan'   => array_values(array_diff($dbIds, $svgIds)),
			'toRestore'  => array_values(array_intersect($dbIds, $svgIds)),
			'toInsert'   => array_values(array_diff($svgIds, $dbIds)),
		);
	}
}
