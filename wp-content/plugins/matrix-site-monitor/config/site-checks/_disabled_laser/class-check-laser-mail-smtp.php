<?php
/**
 * Site check: WP Mail SMTP configured (Laser Centre).
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Site_Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Laser_Mail_Smtp extends Check_Base {
    public function id(): string { return 'laser_mail_smtp'; }
    public function label(): string { return 'Site: WP Mail SMTP'; }
    public function category(): string { return 'Site'; }
    public function severity(): string { return 'critical'; }
    public function tier(): string { return 'light'; }

    public function run(): array {
        $start = microtime(true);

        if (! Laser_Helpers::any_plugin_active([
            'wp-mail-smtp/wp_mail_smtp.php',
            'wp-mail-smtp-pro/wp_mail_smtp.php',
        ])) {
            return $this->fail('WP Mail SMTP is not active.', $start);
        }

        $opts = get_option('wp_mail_smtp', []);
        if (! is_array($opts)) {
            $opts = [];
        }

        $mailer = '';
        if (isset($opts['mail']['mailer'])) {
            $mailer = (string) $opts['mail']['mailer'];
        } elseif (isset($opts['mailer'])) {
            $mailer = (string) $opts['mailer'];
        }

        if ($mailer === '' || strtolower($mailer) === 'mail' || strtolower($mailer) === 'none') {
            return $this->fail(
                'WP Mail SMTP mailer is not configured (current: ' . ($mailer !== '' ? $mailer : 'empty') . ').',
                $start
            );
        }

        return $this->pass('WP Mail SMTP active; mailer=' . $mailer . '.', $start);
    }
}

add_filter('msm_registered_checks', static function (array $checks): array {
    $checks[] = new Check_Laser_Mail_Smtp();
    return $checks;
});
