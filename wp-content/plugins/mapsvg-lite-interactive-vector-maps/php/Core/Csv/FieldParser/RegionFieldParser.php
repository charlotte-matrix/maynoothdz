<?php

namespace MapSVG;

/**
 * Parses region fields from CSV.
 *
 * Expected CSV cell: comma-separated list of region IDs (or titles when the
 * region ID equals the title, which is the current convention).
 *
 * Example: "US-TX,US-AL" → [{"id":"US-TX","title":"US-TX","tableName":"regions_115"},…]
 *
 * Context keys used:
 *   - regionsTableName (string): short DB table name, e.g. "regions_115".
 */
class RegionFieldParser implements FieldParserInterface {

	public function supports( string $fieldType ): bool {
		return $fieldType === 'region';
	}

	public function parse( string $rawValue, object $field, array &$context = [] ): array {
		$regionsTableName = (string) ( $context['regionsTableName'] ?? '' );
		$rawValue         = trim( $rawValue );

		if ( $rawValue === '' || $regionsTableName === '' ) {
			return [ $field->name => '' ];
		}

		$ids    = array_filter( array_map( 'trim', explode( ',', $rawValue ) ) );
		$result = array_map(
			fn( string $id ): array => [
				'id'        => $id,
				'title'     => $id,
				'tableName' => $regionsTableName,
			],
			$ids
		);

		return [ $field->name => wp_json_encode( $result, JSON_UNESCAPED_UNICODE ) ];
	}
}
