<?php
/**
 * Check contract interface.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

interface Check_Interface {
    /**
     * Unique check identifier.
     */
    public function id(): string;

    /**
     * Human-readable label.
     */
    public function label(): string;

    /**
     * Docs category (e.g. Accessibility, Hosting, WooCommerce).
     */
    public function category(): string;

    /**
     * Default severity when failing: critical, warning, or info.
     */
    public function severity(): string;

    /**
     * Schedule tier: light, heavy, or synthetic.
     */
    public function tier(): string;

    /**
     * Run the check.
     *
     * @return array{id:string,label:string,status:string,ok:bool,skipped:bool,severity:string,message:string,duration:float}
     */
    public function run(): array;
}
