<?php
/**
 * Email guard for synthetic WooCommerce test orders.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

class Mailguard {
    /** @var bool */
    private static $suppressing = false;

    /** @var array<int, string> */
    private const ORDER_EMAILS = [
        'new_order',
        'cancelled_order',
        'failed_order',
        'customer_on_hold_order',
        'customer_processing_order',
        'customer_completed_order',
        'customer_refunded_order',
        'customer_invoice',
        'customer_note',
    ];

    /**
     * Register WooCommerce email filters.
     */
    public static function init(): void {
        foreach (self::ORDER_EMAILS as $email_id) {
            add_filter('woocommerce_email_enabled_' . $email_id, [__CLASS__, 'disable_for_selftest'], 99, 2);
        }
        add_filter('pre_wp_mail', [__CLASS__, 'maybe_block_mail'], 99);
    }

    /**
     * Set suppression state during test runs.
     *
     * @param bool $on Whether to suppress.
     */
    public static function set_suppressing(bool $on): void {
        self::$suppressing = $on;
    }

    /**
     * Whether an order is a self-test order.
     *
     * @param mixed $order Order object.
     */
    public static function is_selftest_order($order): bool {
        return $order instanceof \WC_Order && $order->get_meta(MSM_SELFTEST_META) === 'yes';
    }

    /**
     * Disable WooCommerce emails for self-test orders.
     *
     * @param bool  $enabled Current state.
     * @param mixed $object  Email object.
     */
    public static function disable_for_selftest($enabled, $object) {
        if (self::is_selftest_order($object)) {
            return false;
        }
        return $enabled;
    }

    /**
     * Block all mail during active suppression.
     *
     * @param null|bool $short_circuit Short-circuit value.
     * @return null|bool
     */
    public static function maybe_block_mail($short_circuit) {
        if (self::$suppressing) {
            return false;
        }
        return $short_circuit;
    }
}
