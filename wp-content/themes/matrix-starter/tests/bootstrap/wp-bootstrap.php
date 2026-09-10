<?php
// ─────────────────────────────────────────────────────────────
// 0) Load .env (in project root) so getenv() works everywhere
// ─────────────────────────────────────────────────────────────
$root = dirname( dirname( __DIR__ ) );      // -> project root
if ( file_exists( $root . '/.env' ) ) {
    Dotenv\Dotenv::createImmutable( $root )->safeLoad();
}

// ─────────────────────────────────────────────────────────────
// 1) Polyfills + core test-suite paths (unchanged)
// ─────────────────────────────────────────────────────────────
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $root . '/vendor/yoast/phpunit-polyfills' );
define( 'WP_TESTS_DIR',                    $root . '/vendor/wp-phpunit/wp-phpunit' );

// ─────────────────────────────────────────────────────────────
// 2) Constants fed from ENV with sane fallbacks
// ─────────────────────────────────────────────────────────────
define( 'WP_TESTS_DOMAIN',  getenv('WP_HOME')       ?: 'example.org' );
define( 'WP_TESTS_EMAIL',   getenv('WP_ADMIN_EMAIL')?: 'admin@example.org' );
define( 'WP_TESTS_TITLE',   getenv('WP_SITE_TITLE') ?: 'Matrix Starter Test Site' );
define( 'WP_PHP_BINARY',    PHP_BINARY );

define( 'DB_HOST',          getenv('DB_HOST')       ?: '127.0.0.1' );
define( 'DB_NAME',          getenv('DB_NAME')       ?: 'wptests'   );
define( 'DB_USER',          getenv('DB_USER')       ?: 'root'      );
define( 'DB_PASSWORD',      getenv('DB_PASSWORD')   ?: 'root'      );
define( 'DB_PREFIX',        getenv('DB_PREFIX')     ?: 'wptests_'  );

// ─────────────────────────────────────────────────────────────
// 3) Boot WordPress
// ─────────────────────────────────────────────────────────────
require WP_TESTS_DIR . '/includes/bootstrap.php';

