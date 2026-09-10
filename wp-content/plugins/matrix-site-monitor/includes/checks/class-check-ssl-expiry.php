<?php
/**
 * SSL certificate expiry check.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;
use Matrix_Site_Monitor\Settings;

defined('ABSPATH') || exit;

class Check_Ssl_Expiry extends Check_Base {
    public function id(): string { return 'ssl_expiry'; }
    public function label(): string { return 'SSL certificate expiry'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);
        $url   = home_url('/');
        $host  = wp_parse_url($url, PHP_URL_HOST);

        if (! $host || ! is_ssl()) {
            return $this->skip('Site is not served over HTTPS — SSL check not applicable.', $start);
        }

        $expiry = $this->get_cert_expiry((string) $host);
        if ($expiry === null) {
            return $this->skip('SSL expiry could not be determined.', $start);
        }

        $days_left = (int) floor(($expiry - time()) / DAY_IN_SECONDS);
        $warn_days = (int) (Settings::get()['ssl_warn_days'] ?? 14);

        if ($days_left < 0) {
            return $this->result(false, 'SSL certificate has expired.', $start);
        }

        if ($days_left <= $warn_days) {
            return $this->result(
                false,
                sprintf('SSL certificate expires in %d day(s) (%s).', $days_left, gmdate('Y-m-d', $expiry)),
                $start
            );
        }

        return $this->result(
            true,
            sprintf('SSL valid for %d more day(s).', $days_left),
            $start
        );
    }

    /**
     * Get certificate expiry timestamp.
     *
     * @param string $host Hostname.
     */
    private function get_cert_expiry(string $host): ?int {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer'       => false,
                'verify_peer_name'  => false,
            ],
        ]);

        $stream = @stream_socket_client(
            'ssl://' . $host . ':443',
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $stream) {
            return null;
        }

        $params = stream_context_get_params($stream);
        fclose($stream);

        if (empty($params['options']['ssl']['peer_certificate'])) {
            return null;
        }

        $info = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        return isset($info['validTo_time_t']) ? (int) $info['validTo_time_t'] : null;
    }
}
