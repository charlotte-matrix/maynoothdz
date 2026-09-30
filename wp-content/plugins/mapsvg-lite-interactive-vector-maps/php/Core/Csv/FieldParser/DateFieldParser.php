<?php

namespace MapSVG;

/**
 * Parses "date" and "datetime" fields from CSV.
 *
 * Stores the raw string as-is — matching what encodeParams does for
 * these types. The value is expected to be a valid date/datetime string
 * that MySQL can store (e.g. "2024-06-15" or "2024-06-15 14:30:00").
 *
 * If the value is empty, null is stored so the column stays NULL in the DB
 * rather than an empty string in a datetime column.
 */
class DateFieldParser implements FieldParserInterface {

	public function supports( string $fieldType ): bool {
		return $fieldType === 'date' || $fieldType === 'datetime';
	}

	public function parse( string $rawValue, object $field, array &$context = [] ): array {
		$rawValue = trim( $rawValue );
		return [ $field->name => $rawValue !== '' ? $rawValue : null ];
	}
}
