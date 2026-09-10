<?php
/**
 * Site check: Laser Centre appointment booking form is on the page and can persist a test request.
 *
 * Creates a tagged appointment, verifies it saved, then deletes it. Does not send mail.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Site_Checks;

use Matrix_Site_Monitor\Check_Base;

defined('ABSPATH') || exit;

class Check_Laser_Booking_Form extends Check_Base {
    public function id(): string { return 'laser_booking_form'; }
    public function label(): string { return 'Site: booking form'; }
    public function category(): string { return 'Site'; }
    public function severity(): string { return 'warning'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start = microtime(true);
        $path  = (string) apply_filters('msm_laser_booking_path', '/book-appointment/');

        $page = Laser_Helpers::assert_form_on_page($path, [
            'data-appointment-form',
            'admin-post.php',
            'name="name"',
            'name="email"',
            'name="phone"',
            '_theme_form_type',
            'theme_form_nonce',
            'name="privacy_consent"',
        ]);
        if (! $page['ok']) {
            return $this->fail($page['message'], $start);
        }
        if (class_exists('Theme_Forms') && ! \Theme_Forms::turnstile_enabled() && ! \Theme_Forms::recaptcha_enabled()) {
            return $this->fail('Booking form is on the page but Turnstile/reCAPTCHA is not enabled.', $start);
        }

        $fired = Laser_Helpers::fire_and_delete('appointment', [
            'name'                => Laser_Helpers::TEST_NAME,
            'email'               => Laser_Helpers::TEST_EMAIL,
            'phone'               => Laser_Helpers::TEST_PHONE,
            'treatment'           => 'MSM Selftest',
            'preferred_date'      => wp_date('Y-m-d', strtotime('+7 days')),
            'preferred_time'      => '10:00',
            'additional_details'  => 'Matrix Site Monitor booking-form self-test. Safe to delete.',
            'privacy_consent'     => 'yes',
        ]);
        if (! $fired['ok']) {
            return $this->fail($page['message'] . ' ' . $fired['message'], $start);
        }

        return $this->pass($page['message'] . '. ' . $fired['message'], $start);
    }
}

add_filter('msm_registered_checks', static function (array $checks): array {
    $checks[] = new Check_Laser_Booking_Form();
    return $checks;
});
