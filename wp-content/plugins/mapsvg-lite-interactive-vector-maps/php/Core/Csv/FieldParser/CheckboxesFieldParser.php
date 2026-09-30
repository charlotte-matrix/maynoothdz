<?php

namespace MapSVG;

/**
 * Parses "checkboxes" fields (group of checkboxes) from CSV.
 *
 * The CSV cell contains a comma-separated list of checked values or labels,
 * e.g. "North,East" or "1,3". Each token is resolved against the field's
 * option list (matching by value OR label). The result is stored as a
 * JSON array of matched values: ["north","east"].
 *
 * Uses str_getcsv() so that quoted sub-values containing commas are handled
 * correctly — same approach as SelectFieldParser for multiselect.
 */
class CheckboxesFieldParser implements FieldParserInterface {

	public function __construct( private Schema $schema ) {}

	public function supports( string $fieldType ): bool {
		return $fieldType === 'checkboxes';
	}

	public function parse( string $rawValue, object $field, array &$context = [] ): array {
		$fieldOptions = $this->schema->getFieldOptions( $field->name );
		$tokens       = str_getcsv( $rawValue ); // handles inner quoting
		$values       = [];

		foreach ( $tokens as $token ) {
			$token = trim( $token );
			if ( $token === '' ) {
				continue;
			}
			foreach ( $fieldOptions as $option ) {
				if ( (string) $option['value'] === $token || (string) $option['label'] === $token ) {
					$values[] = $option['value'];
					break;
				}
			}
		}

		return [ $field->name => wp_json_encode( $values, JSON_UNESCAPED_UNICODE ) ];
	}
}
