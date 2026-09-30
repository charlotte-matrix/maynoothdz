<?php

namespace MapSVG;

/**
 * Parses select / radio / status fields from CSV.
 *
 * Single-select: matches the raw CSV value against field options by value OR
 * label, then stores the canonical value + label pair.
 *
 * Multiselect: the CSV cell contains a comma-separated list (quoted if needed,
 * e.g. "North,East"). str_getcsv() re-parses the cell so quoted sub-values
 * containing commas work correctly. Each token is resolved by value OR label.
 * Result is stored as a JSON array of values.
 */
class SelectFieldParser implements FieldParserInterface {

	public function __construct( private Schema $schema ) {}

	public function supports( string $fieldType ): bool {
		return in_array( $fieldType, [ 'select', 'radio', 'status' ], true );
	}

	public function parse( string $rawValue, object $field, array &$context = [] ): array {
		$fieldOptions = $this->schema->getFieldOptions( $field->name );
		$isMulti      = ! empty( $field->multiselect ) && filter_var( $field->multiselect, FILTER_VALIDATE_BOOLEAN );

		if ( $isMulti ) {
			return $this->parseMulti( $rawValue, $field, $fieldOptions );
		}

		return $this->parseSingle( $rawValue, $field, $fieldOptions );
	}

	/**
	 * @param array<int, array{value: string, label: string}> $fieldOptions
	 * @return array<string, mixed>
	 */
	private function parseSingle( string $rawValue, object $field, array $fieldOptions ): array {
		foreach ( $fieldOptions as $option ) {
			if ( (string) $option['value'] === $rawValue || (string) $option['label'] === $rawValue ) {
				return [
					$field->name            => $option['value'],
					$field->name . '_text'  => $option['label'],
				];
			}
		}

		return [
			$field->name           => '',
			$field->name . '_text' => '',
		];
	}

	/**
	 * CSV cell "North,East" → [{"value":"north_value","label":"North"},…] JSON.
	 * Uses str_getcsv so quoted sub-values with embedded commas work.
	 *
	 * @param array<int, array{value: string, label: string}> $fieldOptions
	 * @return array<string, mixed>
	 */
	private function parseMulti( string $rawValue, object $field, array $fieldOptions ): array {
		$tokens = str_getcsv( $rawValue, ',', '"', '' ); // explicit $escape='' avoids PHP 8.4 deprecation
		$items  = [];

		foreach ( $tokens as $token ) {
			$token = trim( (string) $token );
			if ( $token === '' ) {
				continue;
			}
			foreach ( $fieldOptions as $option ) {
				if ( (string) $option['value'] === $token || (string) $option['label'] === $token ) {
					$items[] = [ 'value' => $option['value'], 'label' => $option['label'] ];
					break;
				}
			}
		}

		return [ $field->name => wp_json_encode( $items, JSON_UNESCAPED_UNICODE ) ];
	}
}
