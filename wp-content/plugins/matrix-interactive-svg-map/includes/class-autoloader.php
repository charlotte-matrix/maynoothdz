<?php
/**
 * PSR-4-ish autoloader for Matrix_Interactive_SVG_Map classes.
 *
 * @package Matrix_Interactive_SVG_Map
 */

namespace Matrix_Interactive_SVG_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Class Autoloader
 */
class Autoloader {

	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Load a class file.
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public static function load( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$relative = strtolower( str_replace( '_', '-', $relative ) );
		$file     = MISVM_DIR . 'includes/class-' . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
