<?php
/**
 * Plugin Name: Matrix Interactive SVG Map
 * Description: Upload SVG maps, edit region names/links/colors, and embed them with a shortcode — lightweight MapSVG replacement.
 * Version:     1.0.0
 * Author:      Matrix Internet
 * Author URI:  https://www.matrixinternet.ie/
 * License:     GPL-2.0-or-later
 * Text Domain: matrix-interactive-svg-map
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Matrix_Interactive_SVG_Map
 */

defined( 'ABSPATH' ) || exit;

define( 'MISVM_VERSION', '1.0.0' );
define( 'MISVM_FILE', __FILE__ );
define( 'MISVM_DIR', plugin_dir_path( __FILE__ ) );
define( 'MISVM_URL', plugin_dir_url( __FILE__ ) );
define( 'MISVM_DB_VERSION', '1.0.0' );

require_once MISVM_DIR . 'includes/class-autoloader.php';

Matrix_Interactive_SVG_Map\Autoloader::register();

register_activation_hook( __FILE__, array( 'Matrix_Interactive_SVG_Map\\Activator', 'activate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		Matrix_Interactive_SVG_Map\Plugin::instance()->boot();
	}
);
