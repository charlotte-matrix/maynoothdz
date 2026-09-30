<?php

namespace MapSVG;

/**
 * Class that gets data from a remote server via WordPress HTTP API
 * @package MapSVG
 */
class Remote
{
	public static function get($url)
	{
		$response = SafeRemoteUrl::get($url, array(
			'timeout'   => 30,
			'sslverify' => true,
		));

		if (is_wp_error($response)) {
			return array(
				"body" => "",
				"status" => "ERROR",
				"error_message" => SafeRemoteUrl::publicErrorMessage($response),
			);
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		return array(
			"body" => $body,
			"status" => ($code >= 200 && $code < 300) ? "OK" : "ERROR",
			"http_code" => $code,
		);
	}

	public static function post($url, $args = array())
	{
		$response = SafeRemoteUrl::post($url, array_merge(array(
			'timeout'   => 30,
			'sslverify' => true,
		), $args));

		if (is_wp_error($response)) {
			return array(
				"body" => "",
				"status" => "ERROR",
				"error_message" => SafeRemoteUrl::publicErrorMessage($response),
			);
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		return array(
			"body" => $body,
			"status" => ($code >= 200 && $code < 300) ? "OK" : "ERROR",
			"http_code" => $code,
		);
	}
}
