<?php
/**
 * PSR-4-style autoloader for Matrix Site Monitor.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

class Autoloader {
    /**
     * Register the autoloader.
     */
    public static function register(): void {
        spl_autoload_register([__CLASS__, 'load']);
    }

    /**
     * Load a class file.
     *
     * @param string $class Fully qualified class name.
     */
    public static function load(string $class): void {
        if (strpos($class, __NAMESPACE__ . '\\') !== 0) {
            return;
        }

        $relative = substr($class, strlen(__NAMESPACE__ . '\\'));
        $relative = str_replace('\\', '/', $relative);
        $relative = strtolower(str_replace('_', '-', $relative));
        $relative = preg_replace('#^checks-woocommerce/#', 'checks-woocommerce/', $relative);
        $relative = preg_replace('#^checks/#', 'checks/', $relative);
        $relative = preg_replace('#^admin/#', 'admin/', $relative);

        $parts = explode('/', $relative);
        $file  = array_pop($parts);

        if (! empty($parts)) {
            $path = MSM_DIR . 'includes/' . implode('/', $parts) . '/class-' . $file . '.php';
        } else {
            $path = MSM_DIR . 'includes/class-' . $file . '.php';
        }

        if (is_readable($path)) {
            require_once $path;
        }
    }
}
