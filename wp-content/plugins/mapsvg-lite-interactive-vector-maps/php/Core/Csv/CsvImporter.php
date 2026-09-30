<?php

namespace MapSVG;

/**
 * Streams a CSV file and bulk-inserts rows via a DataSource.
 *
 * Supports two modes:
 *
 * 1. Single-shot  — `import()` reads and inserts the entire file synchronously.
 * 2. Chunked      — `initialize()` sniffs the file and returns metadata; subsequent
 *                   `importChunk()` calls each process one batch, identified by a
 *                   byte offset so the file can be seeked instead of re-read.
 *
 * Third-party code can inject extra parsers via the 'mapsvg_csv_register_field_parsers'
 * WordPress action, which receives the CsvRowParser instance.
 */
class CsvImporter
{

	/** Rows per single INSERT statement. 5000 is safe under MySQL's default 64 MB max_allowed_packet. */
	private const BATCH_SIZE        = 5000;

	/** Rows read + inserted per importChunk() call (poll-driven / cron-driven). */
	private const CHUNK_SIZE        = 50000;

	private const DELIMITER_OPTIONS = [',', ';', "\t", '|'];

	public function __construct(
		private Schema $schema,
		private DbDataSource $source
	) {}

	// ── Single-shot import ───────────────────────────────────────────────────

	/**
	 * @param string        $filePath              Absolute path to the uploaded CSV file.
	 * @param bool          $convertLatlngToAddress
	 * @param bool          $convertAddressToLatLng
	 * @param string        $regionsTableName      Short table name, e.g. "regions_115".
	 * @param callable|null $onBatchFlush          Optional heartbeat after each inserted batch.
	 * @return array{count: int, needs_geocoding: bool}
	 * @throws \Exception on file / format errors.
	 */
	public function import(
		string $filePath,
		bool   $convertLatlngToAddress  = false,
		bool   $convertAddressToLatLng  = false,
		string $regionsTableName        = '',
		$onBatchFlush = null,
		bool   $upsert = true
	): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen($filePath, 'r');
		if ($handle === false) {
			throw new \Exception('Cannot open CSV file: ' . esc_html(basename($filePath)));
		}

		$separator = $this->sniffDelimiter($handle);
		$headers   = $this->readHeaders($handle, $separator);

		$rowParser = $this->buildRowParser();

		$context = [
			'convertLatlngToAddress' => $convertLatlngToAddress,
			'convertAddressToLatLng' => $convertAddressToLatLng,
			'regionsTableName'       => $regionsTableName,
			'needsGeocoding'         => false,
		];

		$batch     = [];
		$totalRows = 0;

		while (($csvRow = fgetcsv($handle, 0, $separator, '"', '')) !== false) {
			// Skip blank lines
			if (count($csvRow) === 1 && $csvRow[0] === null) {
				continue;
			}

			$rawRow  = array_combine($headers, array_pad($csvRow, count($headers), ''));
			$batch[] = $rowParser->parseRow($rawRow, $context);
			$totalRows++;

			if (count($batch) >= self::BATCH_SIZE) {

				$this->source->import($batch, $upsert);
				$batch = [];
				if (is_callable($onBatchFlush)) {
					$onBatchFlush();
				}
			}
		}

		if (! empty($batch)) {

			$this->source->import($batch, $upsert);
			if (is_callable($onBatchFlush)) {
				$onBatchFlush();
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose($handle);

		return [
			'count'           => $totalRows,
			'needs_geocoding' => $context['needsGeocoding'],
		];
	}

	// ── Chunked import (background / poll-driven) ────────────────────────────

	/**
	 * Sniff the delimiter and headers, count total data rows, and return the byte
	 * offset of the first data row. Call this once on upload; store the result in
	 * a CsvImportJob so subsequent importChunk() calls can resume from the right
	 * position without re-reading the whole file.
	 *
	 * @return array{separator: string, headers: string[], data_offset: int, total: int}
	 * @throws \Exception on file / format errors.
	 */
	public function initialize(string $filePath): array
	{
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen($filePath, 'r');
		if ($handle === false) {
			throw new \Exception('Cannot open CSV file: ' . esc_html(basename($filePath)));
		}

		$separator  = $this->sniffDelimiter($handle);
		$headers    = $this->readHeaders($handle, $separator);
		$dataOffset = ftell($handle);

		// Quick row count — no parsing, just counting non-blank lines.
		$total = 0;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgets
		while (fgets($handle) !== false) {
			$total++;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose($handle);

		return [
			'separator'   => $separator,
			'headers'     => $headers,
			'data_offset' => $dataOffset,
			'total'       => $total,
		];
	}

	/**
	 * Process one chunk of up to BATCH_SIZE rows, starting at $byteOffset.
	 *
	 * @param string   $filePath
	 * @param string   $separator            Delimiter returned by initialize().
	 * @param string[] $headers              Header names returned by initialize().
	 * @param int      $byteOffset           Byte position to seek to (0 = start of data).
	 * @param bool     $convertLatlngToAddress
	 * @param bool     $convertAddressToLatLng
	 * @param string   $regionsTableName
	 * @return array{
	 *   rows_processed: int,
	 *   next_offset: int,
	 *   eof: bool,
	 *   needs_geocoding: bool,
	 *   errors: list<array{message: string}>
	 * }
	 * @throws \Exception if the file cannot be opened.
	 */
	public function importChunk(
		string $filePath,
		string $separator,
		array  $headers,
		int    $byteOffset,
		bool   $convertLatlngToAddress  = false,
		bool   $convertAddressToLatLng  = false,
		string $regionsTableName        = '',
		bool   $upsert                  = true
	): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen($filePath, 'r');
		if ($handle === false) {
			throw new \Exception('Cannot open CSV file for chunk processing.');
		}

		fseek($handle, $byteOffset);

		$rowParser = $this->buildRowParser();

		$context = [
			'convertLatlngToAddress' => $convertLatlngToAddress,
			'convertAddressToLatLng' => $convertAddressToLatLng,
			'regionsTableName'       => $regionsTableName,
			'needsGeocoding'         => false,
		];

		$batch     = [];
		$rowsRead  = 0;
		$errors    = [];

		while ($rowsRead < self::CHUNK_SIZE) {
			$csvRow = fgetcsv($handle, 0, $separator, '"', '');
			if ($csvRow === false) {
				break;
			}
			if (count($csvRow) === 1 && $csvRow[0] === null) {
				continue; // blank line
			}

			$rawRow  = array_combine($headers, array_pad($csvRow, count($headers), ''));
			$batch[] = $rowParser->parseRow($rawRow, $context);
			$rowsRead++;

			// Flush sub-batches to the DB every BATCH_SIZE rows to keep memory bounded.
			if (count($batch) >= self::BATCH_SIZE) {
				try {
					$this->source->import($batch, $upsert);
				} catch (\Exception $e) {
					$errors[] = ['message' => $e->getMessage()];
				}
				$batch = [];
			}
		}

		$nextOffset = (int) ftell($handle);
		$eof        = feof($handle);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose($handle);

		if (! empty($batch)) {
			try {
				$this->source->import($batch, $upsert);
			} catch (\Exception $e) {
				$errors[] = ['message' => $e->getMessage()];
			}
		}

		return [
			'rows_processed'  => $rowsRead,
			'next_offset'     => $nextOffset,
			'eof'             => $eof,
			'needs_geocoding' => $context['needsGeocoding'],
			'errors'          => $errors,
		];
	}

	// ── Private helpers ──────────────────────────────────────────────────────

	/**
	 * Peek at the first line, count candidate delimiters, pick the most frequent.
	 */
	private function sniffDelimiter($handle): string
	{
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fgets
		$firstLine = fgets($handle);
		rewind($handle);

		$line   = $firstLine !== false ? $firstLine : '';
		$counts = array_map(fn(string $d): int => substr_count($line, $d), self::DELIMITER_OPTIONS);

		return self::DELIMITER_OPTIONS[(int) array_search(max($counts), $counts, true)];
	}

	/**
	 * Read and normalise the header row (lowercase, spaces → underscores).
	 *
	 * @return string[]
	 * @throws \Exception if the file is empty.
	 */
	private function readHeaders($handle, string $separator): array
	{
		$rawHeaders = fgetcsv($handle, 0, $separator, '"', '');
		if ($rawHeaders === false) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose($handle);
			throw new \Exception('CSV file is empty or invalid.');
		}

		return array_map(
			[self::class, 'normalizeHeader'],
			array_map('strval', $rawHeaders)
		);
	}

	/**
	 * Normalise a CSV header: strip a leading BOM, trim, lowercase, spaces → underscores.
	 *
	 * Excel and some Google Sheets exports prefix the first column with a UTF-8 BOM.
	 * Without stripping it, the ID header never matches the schema `id` field,
	 * and imports insert blank-ID rows instead of upserting.
	 */
	public static function normalizeHeader(string $header): string
	{
		if (strncmp($header, "\xEF\xBB\xBF", 3) === 0) {
			$header = substr($header, 3);
		}
		if (strncmp($header, "\xFE\xFF", 2) === 0 || strncmp($header, "\xFF\xFE", 2) === 0) {
			$header = substr($header, 2);
		}
		$stripped = preg_replace('/^\x{FEFF}/u', '', $header);
		if (is_string($stripped)) {
			$header = $stripped;
		}

		return strtolower(str_replace(' ', '_', trim($header)));
	}

	/**
	 * Build a CsvRowParser pre-loaded with all built-in field parsers.
	 * Fires an action so external code can add or replace parsers.
	 */
	private function buildRowParser(): CsvRowParser
	{
		$rowParser = new CsvRowParser($this->schema);

		$rowParser->register(new SelectFieldParser($this->schema));
		$rowParser->register(new CheckboxesFieldParser($this->schema));
		$rowParser->register(new LocationFieldParser());
		$rowParser->register(new RegionFieldParser());
		$rowParser->register(new CheckboxFieldParser());
		$rowParser->register(new PostFieldParser());
		$rowParser->register(new DateFieldParser());
		$rowParser->register(new ImageFieldParser());

		/**
		 * Action: mapsvg_csv_register_field_parsers
		 * Allows plugins / themes to register additional field parsers.
		 *
		 * @param CsvRowParser $rowParser The parser instance to call ->register() on.
		 * @param Schema       $schema    The active schema.
		 */
		do_action('mapsvg_csv_register_field_parsers', $rowParser, $this->schema);

		return $rowParser;
	}
}
