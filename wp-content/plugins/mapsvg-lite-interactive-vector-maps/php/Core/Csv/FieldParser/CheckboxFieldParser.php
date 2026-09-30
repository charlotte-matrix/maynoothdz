<?php

namespace MapSVG;

/**
 * Parses checkbox fields from CSV.
 * Accepts: 1 / 0 / true / false (case-insensitive).
 */
class CheckboxFieldParser implements FieldParserInterface {

	public function supports( string $fieldType ): bool {
		return $fieldType === 'checkbox';
	}

	public function parse( string $rawValue, object $field, array &$context = [] ): array {
		$normalised = strtolower( trim( $rawValue ) );
		$checked    = in_array( $normalised, [ '1', 'true' ], true ) ? 1 : 0;
		return [ $field->name => $checked ];
	}
}
