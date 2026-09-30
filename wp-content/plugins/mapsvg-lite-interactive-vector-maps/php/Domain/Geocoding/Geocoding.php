<?php


namespace MapSVG;

/**
 * Geocoding Class.
 * Handles requests to Google Geocoding API.
 */
class Geocoding
{

	public $geocoding_quota_per_second;
	private $permanent_error;
	private $apiKey;

	public function __construct($apiKey = '')
	{
		$this->geocoding_quota_per_second = 1;
		$this->apiKey = $apiKey;
		$this->permanent_error = '';
	}

	public function get($address, $return_as_array = true, $convert_latlng_to_address = true, $lang = 'en', $country = '')
	{

		if (empty($address)) {
			return false;
		}

		if ($this->permanent_error !== '') {
			return $return_as_array ? json_decode($this->permanent_error, true) : $this->permanent_error;
		}

		if (!$this->apiKey) {
			$this->apiKey = Options::get('google_geocoding_api_key');
			if (!$this->apiKey) {
				$this->apiKey = Options::get('google_api_key');
			}
		}
		$address_is_coordinates = false;
		$reg_latlng = "/^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?)[\s]?[,\s]?[\s]?[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/";
		if (preg_match($reg_latlng, $address)) {
			$address_is_coordinates = true;
			if (strpos($address, ',') !== false) {
				$delimiter = ',';
			} elseif (strpos($address, ' ') !== false) {
				$delimiter = ' ';
			}
			$coords = explode($delimiter, $address);
			$coords[0] = trim($coords[0]);
			$coords[1] = trim($coords[1]);
			$coords_item = array(
				"geometry" => array("location" => array("lat" => $coords[0], "lng" => $coords[1])),
				"formatted_address" => $address,
				"address_components" => array()
			);
		}

		if ((!$address_is_coordinates || $convert_latlng_to_address === true) && $this->apiKey) {
			$countryParam = $country !== '' ? '&components=country:' . $country : '';

			// ── Cache lookup ────────────────────────────────────────────────────────
			$cache    = new GeocodingCacheRepository();
			$cacheKey = md5(trim($address) . '|' . $lang);
			$cached   = $cache->find($cacheKey);

			if ($cached !== null) {
				return $return_as_array ? $cached : wp_json_encode($cached, JSON_UNESCAPED_UNICODE);
			}

			if ($this->geocoding_quota_per_second > 49) {
				sleep(1);
				$this->geocoding_quota_per_second = 1;
			}
			$this->geocoding_quota_per_second++;

			// ── Daily budget guard (shared across imports + live map searches) ──
			$today      = gmdate('Y-m-d');
			$dailyDate  = Options::get('geocoding_daily_date');
			$dailyCount = (int) Options::get('geocoding_daily_count');

			if ($dailyDate !== $today) {
				$dailyCount = 0;
				Options::set('geocoding_daily_date', $today);
				Options::set('geocoding_daily_count', 0);
			}

			$dailyLimit = (int) (Options::get('google_geocoding_daily_limit') ?: 1300);

			if ($dailyCount >= $dailyLimit) {
				return $return_as_array
					? ['status' => 'OVER_DAILY_LIMIT', 'error_message' => 'MapSVG daily geocoding budget of ' . $dailyLimit . ' requests reached. Resets tomorrow (UTC).']
					: wp_json_encode(['status' => 'OVER_DAILY_LIMIT']);
			}

			Options::set('geocoding_daily_count', $dailyCount + 1);

			$address = urlencode($address);

			$data    = Remote::get('https://maps.googleapis.com/maps/api/geocode/json?key=' . $this->apiKey . '&address=' . $address . '&sensor=true&language=' . $lang . $countryParam);
			if ($data && !isset($data['error_message'])) {
				$response = json_decode($data['body'], true);
				if ($response['status'] === 'OVER_DAILY_LIMIT' || $response['status'] === 'OVER_QUERY_LIMIT') {
					$this->permanent_error = $data;
				} else {
					// 
					// if ($address_is_coordinates) {
					// 	array_unshift($response['results'], $coords_item);
					// }
					// ── Cache successful responses only ──────────────────────────────
					if ($response['status'] === 'OK') {
						$cache->store($cacheKey, $response);
					}
				}
			} else {
				$response = $data;
			}
		} else {
			if ($address_is_coordinates) {
				$response = array(
					"status"  => "OK",
					"results" => array($coords_item)
				);
			} else {
				$response = array('status' => 'NO_API_KEY', 'error_message' => 'No Google Geocoding API key. Add the key on MapSVG start screen.');
			}
		}
		return $return_as_array ? $response : wp_json_encode($response);
	}
}
