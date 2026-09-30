<?php

namespace MapSVG;

require_once 'Geocoding.php';

/**
 * Geocoding Controller Class.
 * Handles requests to Google Geocoding API.
 */
class GeocodingController extends Controller
{

	public static function index($request)
	{
		$geo = new Geocoding();
		$lang    = $request['language'] ?? 'en';
		$country = $request['country'] ?? '';
		$response = $geo->get($request['address'], true, true, $lang, $country);
		return self::render($response);
	}
}
