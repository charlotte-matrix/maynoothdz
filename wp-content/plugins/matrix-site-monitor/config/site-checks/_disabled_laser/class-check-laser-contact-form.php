<?php
/**
 * Site check: Laser Centre contact form is on the page and can persist a test entry.
 *
 * Creates a tagged form_entry, verifies it saved, then deletes it. Does not send mail.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Site_Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Laser_Contact_Form extends Check_Base {
    public function id(): string { return 'laser_contact_form'; }
    public function label(): string { return 'Site: contact form'; }
    public function category(): string { return 'Site'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);
        $path  = (string) apply_filters('msm_laser_contact_path', '/contact-us/');

        $page = Laser_Helpers::assert_form_on_page($path, [
            'data-theme-form',
            'admin-post.php',
            'theme_form_nonce',
            'name="privacy_consent"',
        ]);
        if (! $page['ok']) {
            return $this->fail($page['message'], $start);
        }
        if (class_exists('Theme_Forms') && ! \Theme_Forms::turnstile_enabled() && ! \Theme_Forms::recaptcha_enabled()) {
            return $this->fail('Contact form is on the page but Turnstile/reCAPTCHA is not enabled.', $start);
        }

        $fired = Laser_Helpers::fire_and_delete('contact', [
            'name'             => Laser_Helpers::TEST_NAME,
            'email'            => Laser_Helpers::TEST_EMAIL,
            'message'          => 'Matrix Site Monitor contact-form self-test. Safe to delete.',
            'phone'            => Laser_Helpers::TEST_PHONE,
            'privacy_consent'  => 'yes',
        ]);
        if (! $fired['ok']) {
            return $this->fail($page['message'] . ' ' . $fired['message'], $start);
        }

        return $this->pass($page['message'] . '. ' . $fired['message'], $start);
    }
}

add_filter('msm_registered_checks', static function (array $checks): array {
    $checks[] = new Check_Laser_Contact_Form();
    return $checks;
});
