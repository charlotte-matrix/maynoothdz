<?php

namespace MapSVG;

/**
 * Converts one raw CSV row (header => raw-string map) into a DB-ready
 * column => value array using registered field-type parsers.
 *
 * Parsers are tried in registration order; the first one whose supports()
 * returns true is used. A DefaultFieldParser catch-all is always appended.
 *
 * Usage:
 *   $parser = new CsvRowParser($schema);
 *   $parser->register(new SelectFieldParser($schema));
 *   $parser->register(new LocationFieldParser());
 *   $dbRow  = $parser->parseRow($rawRow, $context);
 *
 * Third-party code can extend behaviour by calling register() with additional
 * parsers (or via the 'mapsvg_csv_field_parsers' filter in CsvImporter).
 */
class CsvRowParser
{

	/** @var FieldParserInterface[] */
	private array $parsers = [];

	public function __construct(private Schema $schema) {}

	public function register(FieldParserInterface $parser): void
	{
		$this->parsers[] = $parser;
	}

	/**
	 * Parse one CSV row.
	 *
	 * @param array<string, string> $rawRow   header => raw-string map.
	 * @param array                 $context  Shared context passed through to each parser
	 *                                        (convertLatlngToAddress, convertAddressToLatLng,
	 *                                         regionsTableName, needsGeocoding, …).
	 *                                        Parsers may write back into $context (e.g. needsGeocoding).
	 * @return array<string, mixed>           DB-ready column => value map.
	 */
	public function parseRow(array $rawRow, array &$context = []): array
	{
		$dbRow  = [];
		$fields = $this->schema->getFields();

		if (empty($fields)) {
			// No schema fields — store everything as-is
			foreach ($rawRow as $key => $value) {
				$dbRow[$key] = $value;
			}
			return $dbRow;
		}

		foreach ($fields as $field) {
			if (! array_key_exists($field->name, $rawRow)) {
				continue;
			}

			$rawValue = (string) $rawRow[$field->name];
			$parser   = $this->resolveParser($field->type);
			$columns  = $parser->parse($rawValue, $field, $context);

			$dbRow = array_merge($dbRow, $columns);
		}

		return $dbRow;
	}

	private function resolveParser(string $fieldType): FieldParserInterface
	{
		foreach ($this->parsers as $parser) {
			if ($parser->supports($fieldType)) {
				return $parser;
			}
		}
		return new DefaultFieldParser();
	}
}
