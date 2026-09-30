<?php
/**
 * Main plugin bootstrap.
 *
 * @package Matrix_Interactive_SVG_Map
 */

namespace Matrix_Interactive_SVG_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Class Plugin
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot hooks.
	 */
	public function boot(): void {
		if ( get_option( 'misvm_db_version' ) !== MISVM_DB_VERSION ) {
			Database::create_tables();
			update_option( 'misvm_db_version', MISVM_DB_VERSION );
		}

		Admin::instance()->hooks();
		Shortcode::instance()->hooks();

		add_filter( 'upload_mimes', array( $this, 'allow_svg_upload' ) );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'fix_svg_mime' ), 10, 4 );
	}

	/**
	 * Allow SVG uploads for users who can manage maps.
	 *
	 * @param array<string,string> $mimes Mime types.
	 * @return array<string,string>
	 */
	public function allow_svg_upload( array $mimes ): array {
		if ( current_user_can( 'manage_options' ) ) {
			$mimes['svg'] = 'image/svg+xml';
		}
		return $mimes;
	}

	/**
	 * Fix SVG filetype detection for WordPress.
	 *
	 * @param array<string,mixed> $data     File data.
	 * @param string              $file     Path.
	 * @param string              $filename Filename.
	 * @param array<string,string>|null $mimes Mimes.
	 * @return array<string,mixed>
	 */
	public function fix_svg_mime( $data, $file, $filename, $mimes ) {
		$ext = pathinfo( $filename, PATHINFO_EXTENSION );
		if ( 'svg' === strtolower( (string) $ext ) && current_user_can( 'manage_options' ) ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}
		return $data;
	}
}
