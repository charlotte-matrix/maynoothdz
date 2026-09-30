<?php
/**
 * Plugin activation.
 *
 * @package Matrix_Interactive_SVG_Map
 */

namespace Matrix_Interactive_SVG_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Class Activator
 */
class Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		Database::create_tables();
		update_option( 'misvm_db_version', MISVM_DB_VERSION );
		flush_rewrite_rules();
	}
}
