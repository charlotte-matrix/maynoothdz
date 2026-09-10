<?php
/**
 * WP Mail SMTP staging defaults — run via flexi-install.sh (wp eval-file).
 *
 * Env (theme .env):
 *   MATRIX_SMTP_FROM_EMAIL
 *   MATRIX_SMTP_FROM_NAME  — optional; defaults to site title (get_bloginfo('name'))
 *   MATRIX_SMTP_GOOGLE_CLIENT_ID
 *   MATRIX_SMTP_GOOGLE_CLIENT_SECRET  (required; never commit)
 *
 * Google Cloud Console redirect URI (manual):
 *   https://connect.wpmailsmtp.com/google/
 *
 * @package Matrix_Starter
 */

if (!function_exists('wp_mail_smtp')) {
    fwrite(STDERR, "wp-mail-smtp-not-active\n");
    exit(1);
}

$from_email = getenv('MATRIX_SMTP_FROM_EMAIL') ?: 'devs@matrixinternet.ie';
$from_name  = trim((string) (getenv('MATRIX_SMTP_FROM_NAME') ?: getenv('MATRIX_PROJECT_NAME') ?: ''));
if ($from_name === '') {
    $from_name = (string) get_bloginfo('name');
}

$client_id     = trim((string) (getenv('MATRIX_SMTP_GOOGLE_CLIENT_ID') ?: ''));
$client_secret = trim((string) (getenv('MATRIX_SMTP_GOOGLE_CLIENT_SECRET') ?: ''));

if ($client_id === '' || $client_secret === '') {
    fwrite(STDERR, "missing-google-credentials\n");
    exit(1);
}

$patch = [
    'mail'  => [
        'from_email'       => $from_email,
        'from_name'        => $from_name,
        'mailer'           => 'gmail',
        'from_email_force' => true,
        'from_name_force'  => true,
    ],
    'gmail' => [
        'client_id'               => $client_id,
        'client_secret'           => $client_secret,
        'one_click_setup_enabled' => false,
    ],
];

wp_mail_smtp()->get_options()->set($patch, false, false);

echo "ok\n";
