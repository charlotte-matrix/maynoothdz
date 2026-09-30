<?php

namespace MapSVG;

/**
 * Parses a single raw CSV cell value for a specific field type
 * and returns one or more DB-ready column => value pairs.
 *
 * A parser may expand one CSV column into many DB columns
 * (e.g. "location" → location_lat, location_lng, location_address, …).
 */
interface FieldParserInterface {

	/**
	 * Returns true when this parser handles the given schema field type.
	 */
	public function supports( string $fieldType ): bool;

	/**
	 * Converts a raw CSV string value into an associative array of
	 * DB column => value pairs ready for INSERT.
	 *
	 * @param string   $rawValue  Raw cell content after CSV decoding.
	 * @param object   $field     Schema field descriptor (->name, ->type, …).
	 * @param array    $context   Optional extra data (regionsTableName, flags, …).
	 * @return array<string, mixed>
	 */
	public function parse( string $rawValue, object $field, array &$context = [] ): array;
}
