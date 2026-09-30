<?php

namespace MapSVG;

/**
 * Passthrough parser for plain text, number, textarea, and any
 * unrecognised field type — stores the raw value as-is.
 */
class DefaultFieldParser implements FieldParserInterface {

	public function supports( string $fieldType ): bool {
		return true; // catch-all, always registered last
	}

	public function parse( string $rawValue, object $field, array &$context = [] ): array {
		return [ $field->name => $rawValue ];
	}
}
