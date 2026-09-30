<?php

namespace MapSVG;

/**
 * Parses location fields from CSV.
 *
 * One CSV column expands into seven DB columns:
 *   location_lat, location_lng, location_address, location_x, location_y, location_img, location_geocoding_status
 *
 * Accepted CSV formats:
 *   "45.1233, 56.9812"   → stored as lat/lng directly
 *   "Main st. 1, NY"     → address JSON with address_formatted; status forward candidate or skipped
 *
 * Context keys used:
 *   - convertLatlngToAddress (bool): queue reverse-geocoding for lat/lng rows.
 *   - convertAddressToLatLng (bool): set forward candidate when geocoding enabled.
 *   - needsGeocodingRef    (&bool): reference flag set to true when geocoding is needed.
 */
class LocationFieldParser implements FieldParserInterface
{

	/** Matches "lat,lng" or "lat lng" within valid coordinate ranges. */
	private const LAT_LNG_REGEX = '/^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?)[\s]?[,\s]?[\s]?[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/';

	public function supports(string $fieldType): bool
	{
		return $fieldType === 'location';
	}

	public function parse(string $rawValue, object $field, array &$context = []): array
	{
		$convertLatlngToAddress  = (bool) ($context['convertLatlngToAddress'] ?? false);
		$convertAddressToLatLng = (bool) ($context['convertAddressToLatLng'] ?? false);
		$rawValue               = trim($rawValue);

		if ($rawValue === '') {
			return $this->emptyColumns();
		}

		if (preg_match(self::LAT_LNG_REGEX, $rawValue)) {
			return $this->parseLatLng($rawValue, $convertLatlngToAddress, $context);
		}

		return $this->parseAddress($rawValue, $convertAddressToLatLng, $context);
	}

	// ── Private helpers ──────────────────────────────────────────────────────

	private function parseLatLng(string $rawValue, bool $reverseGeocode, array &$context): array
	{
		$delimiter = str_contains($rawValue, ',') ? ',' : ' ';
		$parts     = array_map('trim', explode($delimiter, $rawValue, 2));

		if ($reverseGeocode) {
			$context['needsGeocoding'] = true;
		}

		return [
			'location_lat'                => (float) $parts[0],
			'location_lng'                => isset($parts[1]) ? (float) $parts[1] : 0.0,
			'location_address'            => '',
			'location_img'                => '',
			'location_x'                  => null,
			'location_y'                  => null,
			'location_geocoding_status'  => $reverseGeocode
				? LocationGeocodingStatus::REVERSE_CANDIDATE
				: LocationGeocodingStatus::SKIPPED,
		];
	}

	private function parseAddress(string $rawValue, bool $forwardGeocode, array &$context): array
	{
		$addressJson = wp_json_encode(['address_formatted' => $rawValue], JSON_UNESCAPED_UNICODE);

		if ($forwardGeocode) {
			$context['needsGeocoding'] = true;
			$status                   = LocationGeocodingStatus::FORWARD_CANDIDATE;
		} else {
			$status = LocationGeocodingStatus::SKIPPED;
		}

		return [
			'location_address'            => $addressJson,
			'location_lat'                => null,
			'location_lng'                => null,
			'location_x'                  => null,
			'location_y'                  => null,
			'location_img'                => '',
			'location_geocoding_status'   => $status,
		];
	}

	private function emptyColumns(): array
	{
		return [
			'location_address'            => '',
			'location_lat'              => null,
			'location_lng'              => null,
			'location_x'                => null,
			'location_y'                => null,
			'location_img'              => '',
			'location_geocoding_status' => LocationGeocodingStatus::SKIPPED,
		];
	}
}
