<?php

namespace MapSVG;

/**
 * Manages the background geocoding queue.
 *
 * Queue stored in a transient keyed by table name.
 * Row eligibility uses location_geocoding_status only (1 forward, 2 reverse) with schema flags.
 */
class GeocodingQueue
{
	const OPTION_KEY  = 'mapsvg_geocoding_queue';
	const CRON_HOOK   = 'mapsvg_geocode_batch';
	const BATCH_SIZE  = 45; // stay under Google's 50 req/sec hard limit
	const TTL         = WEEK_IN_SECONDS;

	// ─── Queue storage (wp_options via transients — longtext, no size limit) ─

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function get(): array
	{
		$raw = get_transient(self::OPTION_KEY);
		if (empty($raw)) {
			return [];
		}
		$decoded = json_decode($raw, true);
		return is_array($decoded) ? $decoded : [];
	}

	private static function save(array $queue): void
	{
		if (empty($queue)) {
			delete_transient(self::OPTION_KEY);
		} else {
			set_transient(self::OPTION_KEY, wp_json_encode($queue, JSON_UNESCAPED_UNICODE), self::TTL);
		}
	}

	// ─── Public API ─────────────────────────────────────────────────────────

	/**
	 * Add a table to the queue and schedule the cron if not already running.
	 *
	 * @param bool $paidGeocoding When true the daily limit is bypassed and geocoding
	 *                            runs as fast as the API allows (user accepts charges).
	 * @param bool $convertAddressToLatLng Forward (address → lat/lng) when true.
	 */
	public static function add(
		string $tableName,
		string $language,
		bool $convertLatlngToAddress,
		bool $paidGeocoding = false,
		bool $convertAddressToLatLng = true
	): void {
		$queue = self::get();
		$queue[$tableName] = [
			'table'                        => $tableName,
			'language'                     => $language,
			'convert_latlng_to_address'    => $convertLatlngToAddress,
			'convert_address_to_lat_lng'    => $convertAddressToLatLng,
			'paid_geocoding'               => $paidGeocoding,
		];
		self::save($queue);

		self::process();
	}

	public static function schedule(): void
	{
		if (! wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_single_event(time(), self::CRON_HOOK);
		}
	}

	public static function getStatus(): \stdClass
	{
		$queue = self::get();
		if (empty($queue)) {
			return new \stdClass();
		}

		$db     = Database::get();
		$status = new \stdClass();

		foreach ($queue as $tableName => $config) {
			$convertLatlngToAddress  = ! empty($config['convert_latlng_to_address']);
			$convertAddressToLatLng = array_key_exists('convert_address_to_lat_lng', $config)
				? ! empty($config['convert_address_to_lat_lng'])
				: true;
			$count                   = self::countPending($tableName, $convertLatlngToAddress, $convertAddressToLatLng);
			$status->$tableName      = ['pending' => $count];
		}

		return $status;
	}

	/**
	 * @param string $tableName Schema / repository table basename (no mapsvg_ prefix).
	 */
	public static function countPending(
		string $tableName,
		bool $convertLatlngToAddress,
		bool $convertAddressToLatLng = true
	): int {
		$db        = Database::get();
		$fullTable = esc_sql($db->mapsvg_prefix . $tableName);
		$where     = self::buildWhereClause($convertAddressToLatLng, $convertLatlngToAddress);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $db->get_var("SELECT COUNT(*) FROM `{$fullTable}` WHERE {$where}");
	}

	// ─── Cron handler ───────────────────────────────────────────────────────

	public static function process(): void
	{
		$queue = self::get();
		if (empty($queue)) {
			return;
		}

		$anyPaid = array_filter($queue, fn ($c) => ! empty($c['paid_geocoding']));

		if (empty($anyPaid)) {
			$today      = gmdate('Y-m-d');
			$dailyDate  = Options::get('geocoding_daily_date');
			$dailyCount = (int) Options::get('geocoding_daily_count');
			$dailyLimit = (int) (Options::get('google_geocoding_daily_limit') ?: 1300);

			if ($dailyDate === $today && $dailyCount >= $dailyLimit) {
				wp_schedule_single_event(strtotime('tomorrow') + 300, self::CRON_HOOK);
				return;
			}
		}

		$db  = Database::get();
		$geo = new Geocoding();

		foreach ($queue as $tableName => $config) {
			$convertLatlngToAddress  = ! empty($config['convert_latlng_to_address']);
			$convertAddressToLatLng = array_key_exists('convert_address_to_lat_lng', $config)
				? ! empty($config['convert_address_to_lat_lng'])
				: true;
			$language               = $config['language'] ?? 'en';
			$fullTable              = esc_sql($db->mapsvg_prefix . $tableName);
			$where                  = self::buildWhereClause($convertAddressToLatLng, $convertLatlngToAddress);

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $db->get_results(
				$db->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT id, location_lat, location_lng, location_address, location_geocoding_status FROM `{$fullTable}` WHERE {$where} LIMIT %d",
					self::BATCH_SIZE
				),
				ARRAY_A
			);

			if (empty($rows)) {
				unset($queue[$tableName]);
				continue;
			}

			foreach ($rows as $row) {
				$update = self::geocodeRow(
					$row,
					$convertLatlngToAddress,
					$convertAddressToLatLng,
					$language,
					$geo
				);
				if (! empty($update)) {
					$db->update($db->mapsvg_prefix . $tableName, $update, ['id' => $row['id']]);
				}
			}
		}

		self::save($queue);

		if (! empty($queue)) {
			wp_schedule_single_event(time() + 1, self::CRON_HOOK);
		}
	}

	// ─── Helpers ────────────────────────────────────────────────────────────

	private static function buildWhereClause(bool $convertAddressToLatLng, bool $convertLatlngToAddress): string
	{
		$parts = [];
		if ($convertAddressToLatLng) {
			$parts[] = 'location_geocoding_status = ' . LocationGeocodingStatus::FORWARD_CANDIDATE;
		}
		if ($convertLatlngToAddress) {
			$parts[] = 'location_geocoding_status = ' . LocationGeocodingStatus::REVERSE_CANDIDATE;
		}

		if (empty($parts)) {
			return '0=1';
		}

		return '(' . implode(' OR ', $parts) . ')';
	}

	/**
	 * @return array<string, mixed>  Empty = no row change (retry later).
	 */
	private static function geocodeRow(
		array $row,
		bool $convertLatlngToAddress,
		bool $convertAddressToLatLng,
		string $language,
		Geocoding $geo
	): array {
		$addressJson = $row['location_address'] ?? '';
		$lat         = $row['location_lat'] ?? null;
		$lng         = $row['location_lng'] ?? null;
		$status      = isset($row['location_geocoding_status']) ? (int) $row['location_geocoding_status'] : LocationGeocodingStatus::SKIPPED;

		if ($status === LocationGeocodingStatus::FORWARD_CANDIDATE && $convertAddressToLatLng) {
			return self::geocodeForward($addressJson, $language, $geo);
		}

		if ($status === LocationGeocodingStatus::REVERSE_CANDIDATE && $convertLatlngToAddress) {
			return self::geocodeReverse($lat, $lng, $addressJson, $language, $geo);
		}

		return [];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function geocodeForward(string $addressJson, string $language, Geocoding $geo): array
	{
		$decoded  = json_decode($addressJson, true);
		$rawInput = is_array($decoded) ? (string) ($decoded['address_formatted'] ?? '') : '';
		if ($rawInput === '') {
			return [
				'location_geocoding_status' => LocationGeocodingStatus::FAILED_NO_RETRY,
			];
		}

		$response = $geo->get($rawInput, true, true, $language);

		if (isset($response['status']) && $response['status'] === 'OK') {
			$result = $response['results'][0];

			return [
				'location_lat'                => $result['geometry']['location']['lat'],
				'location_lng'                => $result['geometry']['location']['lng'],
				'location_address'            => self::buildAddressJson($result),
				'location_geocoding_status'  => LocationGeocodingStatus::DONE,
			];
		}

		if (isset($response['status']) && in_array(
			$response['status'],
			['OVER_DAILY_LIMIT', 'OVER_QUERY_LIMIT', 'REQUEST_DENIED'],
			true
		)) {
			return [
				'location_geocoding_status' => LocationGeocodingStatus::FAILED_NO_RETRY,
			];
		}

		return [];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function geocodeReverse($lat, $lng, string $addressJson, string $language, Geocoding $geo): array
	{
		$hasCoordsOnly = ! empty($lat) && (float) $lat !== 0.0 && (empty($addressJson) || $addressJson === '');

		if (! $hasCoordsOnly) {
			return [];
		}

		$response = $geo->get("{$lat},{$lng}", true, true, $language);

		if (isset($response['status']) && $response['status'] === 'OK') {
			$result = $response['results'][1] ?? $response['results'][0];

			return [
				'location_address'            => self::buildAddressJson($result),
				'location_geocoding_status' => LocationGeocodingStatus::DONE,
			];
		}

		if (isset($response['status']) && in_array(
			$response['status'],
			['OVER_DAILY_LIMIT', 'OVER_QUERY_LIMIT', 'REQUEST_DENIED'],
			true
		)) {
			return [
				'location_geocoding_status' => LocationGeocodingStatus::FAILED_NO_RETRY,
			];
		}

		return [];
	}

	private static function buildAddressJson(array $result): string
	{
		$formatted = $result['formatted_address'];
		$address   = [
			'address_formatted' => $formatted,
			'formatted'        => $formatted,
		];
		if (! empty($result['address_components']) && is_array($result['address_components'])) {
			foreach ($result['address_components'] as $component) {
				$type               = $component['types'][0];
				$address[ $type ]   = $component['long_name'];
				if ($component['short_name'] !== $component['long_name']) {
					$address[ $type . '_short' ] = $component['short_name'];
				}
			}
		}
		return wp_json_encode($address, JSON_UNESCAPED_UNICODE);
	}
}
