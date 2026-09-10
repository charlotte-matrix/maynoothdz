<?php
/**
 * NAA site profile — per-site customisation.
 *
 * Laser Centre site-checks are disabled for this install
 * (see config/site-checks/_disabled_laser/).
 *
 * @package Matrix_Site_Monitor
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'msm_smoke_urls', static function ( array $urls ): array {
	$candidates = array(
		'contact'  => home_url( '/contact-us/' ),
		'shop'     => home_url( '/shop/' ),
		'faq'      => home_url( '/faq/' ),
		'about'    => home_url( '/about/' ),
		'account'  => home_url( '/my-account/' ),
		'delivery' => home_url( '/delivery-information/' ),
	);

	foreach ( $candidates as $label => $url ) {
		$urls[ $label ] = $url;
	}

	return $urls;
} );

add_filter( 'msm_check_config', static function ( array $settings ): array {
	if ( $settings['alert_emails'] === '' ) {
		$settings['alert_emails'] = 'developers@matrixinternet.ie';
	}

	$settings['disk_threshold_mode'] = 'gb';
	$settings['disk_threshold_gb']   = 5;
	$settings['wc_enabled']          = 1;
	$settings['critical_paths']      = "/\n/contact-us/\n/shop/\n/my-account/\n/faq/";
	$settings['contact_path']        = '/contact-us/';

	// Local/staging: avoid Laser Redis/Rocket style expectations in go-live suite noise.
	// Set Tools → Site Monitor QA profile to "live" only on production.

	return $settings;
} );

add_filter( 'msm_theme_form_pages', static function (): array {
	return array(
		array(
			'path'    => '/contact-us/',
			'needles' => array( 'wpcf7', 'wpcf7-form', 'contact-form-7' ),
		),
	);
} );

add_filter( 'msm_banned_plugins', static function ( array $rules ): array {
	// File Manager family is already denylisted by the plugin.
	return $rules;
} );
