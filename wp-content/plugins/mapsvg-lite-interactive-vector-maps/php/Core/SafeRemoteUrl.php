<?php

namespace MapSVG;

/**
 * SSRF-hardened remote HTTP helpers for user-supplied URLs (CSV import, AppScript, etc.).
 *
 * WordPress {@see wp_safe_remote_get()} / {@see wp_http_validate_url()} alone are not enough
 * on WP 5.x–6.x: core did not reject link-local cloud metadata (169.254.0.0/16) and may
 * allow "localhost" when redirect-host filters are active. This class adds explicit
 * non-public IP / hostname checks, then re-validates every HTTP hop (including redirects).
 */
class SafeRemoteUrl
{
	private const BLOCKED_HOSTNAMES = array(
		'localhost',
		'metadata',
		'metadata.google.internal',
		'metadata.google',
		'instance-data',
		'instance-data.ec2.internal',
	);

	/** @var int Nested get()/post() calls share one pre_http_request guard. */
	private static $requestGuardDepth = 0;

	/**
	 * Sanitize and validate a URL for outbound server-side fetches.
	 *
	 * @param string $url
	 * @return string|\WP_Error Validated URL, or WP_Error when blocked / invalid.
	 */
	public static function validate(string $url)
	{
		$url = esc_url_raw(trim($url));
		if ($url === '') {
			return new \WP_Error('mapsvg_invalid_url', 'A valid URL is required.');
		}

		$parts = wp_parse_url($url);
		if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
			return new \WP_Error('mapsvg_invalid_url', 'A valid http(s) URL is required.');
		}

		$scheme = strtolower((string) $parts['scheme']);
		if ($scheme !== 'http' && $scheme !== 'https') {
			return new \WP_Error('mapsvg_invalid_url', 'Only http and https URLs are allowed.');
		}

		if (isset($parts['user']) || isset($parts['pass'])) {
			return new \WP_Error('mapsvg_unsafe_url', 'URLs with credentials are not allowed.');
		}

		$host = (string) $parts['host'];
		if (self::isBlockedHost($host)) {
			return new \WP_Error(
				'mapsvg_unsafe_url',
				'This URL is not allowed. Only public http(s) URLs can be fetched.'
			);
		}

		// Core checks (ports, some private ranges). Host checks above run first because
		// WP < 7.1 does not reject 169.254.0.0/16 and may allow localhost via filters.
		if (function_exists('wp_http_validate_url')) {
			$validated = wp_http_validate_url($url);
			if (false === $validated || $validated === '') {
				return new \WP_Error(
					'mapsvg_unsafe_url',
					'This URL is not allowed. Only public http(s) URLs can be fetched.'
				);
			}
			$url = $validated;
		}

		return $url;
	}

	/**
	 * True when the host is localhost, metadata, or resolves to a non-public IP.
	 */
	public static function isBlockedHost(string $host): bool
	{
		$host = strtolower(trim($host));
		if ($host === '') {
			return true;
		}

		// IPv6 in brackets: [::1]
		if ($host[0] === '[' && substr($host, -1) === ']') {
			$host = substr($host, 1, -1);
		}

		if (in_array($host, self::BLOCKED_HOSTNAMES, true)) {
			return true;
		}

		if (
			substr($host, -10) === '.localhost'
			|| substr($host, -6) === '.local'
			|| substr($host, -9) === '.internal'
		) {
			return true;
		}

		if (filter_var($host, FILTER_VALIDATE_IP)) {
			return self::isNonPublicIp($host);
		}

		$ips = gethostbynamel($host);
		if ($ips === false) {
			// Unresolvable: let the HTTP client fail later; do not treat as SSRF.
			return false;
		}

		foreach ($ips as $ip) {
			if (self::isNonPublicIp($ip)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * True for private, loopback, link-local, and other reserved addresses (incl. 169.254/16).
	 */
	public static function isNonPublicIp(string $ip): bool
	{
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$flags = FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
			return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
		}

		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			$flags = FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
			return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
		}

		return true;
	}

	/**
	 * Safe GET for a user-supplied URL.
	 *
	 * @param string $url
	 * @param array  $args Arguments for {@see wp_safe_remote_get()}.
	 * @return array|\WP_Error
	 */
	public static function get(string $url, array $args = array())
	{
		$validated = self::validate($url);
		if (is_wp_error($validated)) {
			return $validated;
		}

		$args = self::mergeArgs($args);

		return self::withRequestGuard(function () use ($validated, $args) {
			if (function_exists('wp_safe_remote_get')) {
				return wp_safe_remote_get($validated, $args);
			}

			return wp_remote_get($validated, $args);
		});
	}

	/**
	 * Safe POST for a user-supplied URL.
	 *
	 * @param string $url
	 * @param array  $args Arguments for {@see wp_safe_remote_post()}.
	 * @return array|\WP_Error
	 */
	public static function post(string $url, array $args = array())
	{
		$validated = self::validate($url);
		if (is_wp_error($validated)) {
			return $validated;
		}

		$args = self::mergeArgs($args);

		return self::withRequestGuard(function () use ($validated, $args) {
			if (function_exists('wp_safe_remote_post')) {
				return wp_safe_remote_post($validated, $args);
			}

			return wp_remote_post($validated, $args);
		});
	}

	/**
	 * Block unsafe hosts on every WP_Http hop (redirect targets included).
	 *
	 * @param false|array|\WP_Error $preempt
	 * @param array                 $parsedArgs
	 * @param string                $url
	 * @return false|array|\WP_Error
	 */
	public static function filterPreHttpRequest($preempt, $parsedArgs, $url)
	{
		if (!is_string($url) || $url === '') {
			return new \WP_Error('mapsvg_invalid_url', 'A valid URL is required.');
		}

		$validated = self::validate($url);
		if (is_wp_error($validated)) {
			return $validated;
		}

		return $preempt;
	}

	/**
	 * User-facing error string that does not leak internal network details.
	 *
	 * @param \WP_Error $error
	 * @param string    $fallback
	 * @return string
	 */
	public static function publicErrorMessage(\WP_Error $error, string $fallback = 'Failed to fetch remote URL.'): string
	{
		$code = $error->get_error_code();
		if ($code === 'mapsvg_unsafe_url' || $code === 'mapsvg_invalid_url') {
			return $error->get_error_message();
		}

		// http_request_failed / http_request_not_executed from reject_unsafe_urls, etc.
		if (
			$code === 'http_request_not_executed'
			|| strpos($error->get_error_message(), 'User has blocked requests') !== false
		) {
			return 'This URL is not allowed. Only public http(s) URLs can be fetched.';
		}

		return $fallback;
	}

	/**
	 * @param array $args
	 * @return array
	 */
	private static function mergeArgs(array $args): array
	{
		$defaults = array(
			'timeout'     => 30,
			'redirection' => 5,
		);

		$merged = array_merge($defaults, $args);
		$merged['reject_unsafe_urls'] = true;

		return $merged;
	}

	/**
	 * @param callable $fn
	 * @return mixed
	 */
	private static function withRequestGuard(callable $fn)
	{
		if (self::$requestGuardDepth === 0) {
			add_filter('pre_http_request', array(self::class, 'filterPreHttpRequest'), 1, 3);
		}
		self::$requestGuardDepth++;

		try {
			return $fn();
		} finally {
			self::$requestGuardDepth--;
			if (self::$requestGuardDepth === 0) {
				remove_filter('pre_http_request', array(self::class, 'filterPreHttpRequest'), 1);
			}
		}
	}
}
