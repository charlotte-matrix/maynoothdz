<?php
/**
 * XML-RPC endpoint exposure check.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Xmlrpc extends Check_Base {
    public function id(): string { return 'xmlrpc'; }
    public function label(): string { return 'XML-RPC exposure'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);

        if ($this->is_dev_environment()) {
            return $this->skip('Skipped in development QA profile. Use live profile for go-live checks.', $start);
        }

        $url   = site_url('xmlrpc.php');

        $res = wp_remote_post($url, [
            'timeout'   => 10,
            'sslverify' => false,
            'headers'   => ['Content-Type' => 'text/xml'],
            'body'      => '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName></methodCall>',
        ]);

        if (is_wp_error($res)) {
            return $this->pass('XML-RPC not reachable (' . $res->get_error_message() . ').', $start);
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        $body = (string) wp_remote_retrieve_body($res);

        if ($code === 403 || $code === 401 || $code === 405) {
            return $this->pass('XML-RPC appears blocked (HTTP ' . $code . ').', $start);
        }

        if ($code >= 200 && $code < 300 && strpos($body, 'methodResponse') !== false) {
            return $this->fail('XML-RPC is publicly reachable and responding. Consider disabling it if unused.', $start);
        }

        return $this->pass('XML-RPC does not appear fully open (HTTP ' . $code . ').', $start);
    }
}
