<?php
/**
 * Community group registration — pending Contributors + email notifications.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether a user account has been approved by an admin.
 */
function matrix_dz_user_is_approved(int $user_id): bool
{
    if ($user_id <= 0) {
        return false;
    }
    // Admins / editors always allowed.
    $user = get_userdata($user_id);
    if ($user && (user_can($user, 'edit_others_posts') || user_can($user, 'manage_options'))) {
        return true;
    }
    return (string) get_user_meta($user_id, 'community_account_approved', true) === '1';
}

/**
 * Absolute URL for the email logo (PNG — SVG is unreliable in clients).
 */
function matrix_dz_community_email_logo_url(): string
{
    $path = get_template_directory() . '/assets/dz/logo-email.png';
    if (is_readable($path)) {
        return get_template_directory_uri() . '/assets/dz/logo-email.png';
    }
    return get_template_directory_uri() . '/assets/dz/logo.svg';
}

/**
 * Wrap community notification content in a branded HTML layout.
 *
 * @param string               $heading Visible email heading.
 * @param string               $intro   Lead paragraph (plain text).
 * @param list<string>         $paras   Extra paragraphs (plain text).
 * @param array{label?:string,url?:string}|null $cta Optional button.
 * @param list<array{label:string,value:string}> $details Optional key/value rows.
 */
function matrix_dz_community_email_html(
    string $heading,
    string $intro,
    array $paras = [],
    ?array $cta = null,
    array $details = []
): string {
    $site   = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $logo   = esc_url(matrix_dz_community_email_logo_url());
    $home   = esc_url(home_url('/'));
    $year   = gmdate('Y');

    $forest = '#203129';
    $green  = '#1b853f';
    $mint   = '#d1f3d6';
    $paper  = '#f9f8f3';
    $teal   = '#02a59f';

    $paras_html = '';
    foreach ($paras as $p) {
        $paras_html .= '<p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:' . $forest . ';">'
            . esc_html($p) . '</p>';
    }

    $details_html = '';
    if ($details !== []) {
        $details_html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 20px;border-collapse:collapse;background:#fff;border:1px solid ' . $mint . ';border-radius:8px;">';
        foreach ($details as $i => $row) {
            $bg = $i % 2 === 0 ? $paper : '#ffffff';
            $value = (string) $row['value'];
            if (preg_match('#^https?://#i', $value)) {
                $value_html = '<a href="' . esc_url($value) . '" style="color:' . $green . ';word-break:break-all;">'
                    . esc_html($value) . '</a>';
            } elseif (is_email($value)) {
                $value_html = '<a href="mailto:' . esc_attr($value) . '" style="color:' . $green . ';">'
                    . esc_html($value) . '</a>';
            } else {
                $value_html = esc_html($value);
            }
            $details_html .= '<tr style="background:' . $bg . ';">'
                . '<td style="padding:12px 16px;font-size:13px;font-weight:700;color:' . $green . ';width:38%;vertical-align:top;">'
                . esc_html($row['label']) . '</td>'
                . '<td style="padding:12px 16px;font-size:15px;color:' . $forest . ';vertical-align:top;">'
                . $value_html . '</td></tr>';
        }
        $details_html .= '</table>';
    }

    $cta_html = '';
    if ($cta && !empty($cta['url']) && !empty($cta['label'])) {
        $cta_html = '<p style="margin:24px 0 8px;">'
            . '<a href="' . esc_url($cta['url']) . '" style="display:inline-block;background:' . $green . ';color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 22px;border-radius:6px;">'
            . esc_html($cta['label']) . '</a></p>';
    }

    return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
        . '<title>' . esc_html($heading) . '</title></head>'
        . '<body style="margin:0;padding:0;background:' . $paper . ';font-family:Arial,Helvetica,sans-serif;color:' . $forest . ';">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $paper . ';padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid ' . $mint . ';">'
        . '<tr><td style="height:10px;background:' . $green . ';font-size:0;line-height:0;">&nbsp;</td></tr>'
        . '<tr><td style="padding:28px 28px 8px;text-align:center;">'
        . '<a href="' . $home . '" style="text-decoration:none;"><img src="' . $logo . '" alt="' . esc_attr($site) . '" width="190" height="40" style="display:inline-block;width:190px;height:auto;border:0;"></a>'
        . '</td></tr>'
        . '<tr><td style="padding:8px 28px 32px;">'
        . '<p style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:' . $teal . ';">' . esc_html($site) . '</p>'
        . '<h1 style="margin:0 0 18px;font-size:22px;line-height:1.25;color:' . $forest . ';font-family:Arial,Helvetica,sans-serif;">' . esc_html($heading) . '</h1>'
        . '<p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:' . $forest . ';">' . esc_html($intro) . '</p>'
        . $details_html
        . $paras_html
        . $cta_html
        . '<p style="margin:28px 0 0;font-size:14px;line-height:1.5;color:#5a6b62;">Climate Action Office<br>'
        . '<a href="mailto:climateaction@kildarecoco.ie" style="color:' . $green . ';text-decoration:none;">climateaction@kildarecoco.ie</a></p>'
        . '</td></tr>'
        . '<tr><td style="padding:16px 28px;background:' . $forest . ';color:#ffffff;font-size:12px;line-height:1.5;text-align:center;">'
        . '&copy; ' . esc_html($year) . ' ' . esc_html($site) . ' · Kildare County Council'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/**
 * Send a branded HTML community email.
 */
function matrix_dz_community_mail(string $to, string $subject, string $html): bool
{
    $headers = ['Content-Type: text/html; charset=UTF-8'];
    return (bool) wp_mail($to, $subject, $html, $headers);
}

/**
 * Approve a pending community contributor and email them.
 */
function matrix_dz_approve_community_user(int $user_id): bool
{
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    $was = matrix_dz_user_is_approved($user_id);
    update_user_meta($user_id, 'community_account_approved', '1');
    // Ensure contributor role.
    $user->set_role('contributor');

    if ($was) {
        return true;
    }

    $login_url = wp_login_url();
    $reset     = network_site_url('wp-login.php?action=rp&key=0&login=' . rawurlencode($user->user_login), 'login');
    $key       = get_password_reset_key($user);
    if (!is_wp_error($key)) {
        $reset = network_site_url(
            'wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login),
            'login'
        );
    }

    $subject = sprintf(
        /* translators: %s: site name */
        __('[%s] Your community account is approved', 'matrix-starter'),
        wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
    );

    $html = matrix_dz_community_email_html(
        __('Your account is approved', 'matrix-starter'),
        sprintf(
            /* translators: %s: user display name */
            __('Hello %s — your Maynooth DZ community group account has been approved.', 'matrix-starter'),
            $user->display_name
        ),
        [
            __('Once signed in, you can draft community updates and events. An administrator will review and publish them.', 'matrix-starter'),
        ],
        [
            'label' => __('Set your password & sign in', 'matrix-starter'),
            'url'   => $reset,
        ],
        [
            ['label' => __('Sign-in page', 'matrix-starter'), 'value' => $login_url],
        ]
    );

    matrix_dz_community_mail($user->user_email, $subject, $html);

    return true;
}

/** Block login until approved. */
add_filter('wp_authenticate_user', function ($user) {
    if (is_wp_error($user) || !($user instanceof WP_User)) {
        return $user;
    }
    if (matrix_dz_user_is_approved((int) $user->ID)) {
        return $user;
    }
    return new WP_Error(
        'community_pending',
        __('Your community account is waiting for approval. You will receive an email when it is enabled.', 'matrix-starter')
    );
});

/** User profile: group name + approval checkbox. */
add_action('show_user_profile', 'matrix_dz_community_user_profile_fields');
add_action('edit_user_profile', 'matrix_dz_community_user_profile_fields');
function matrix_dz_community_user_profile_fields(WP_User $user): void
{
    if (!current_user_can('edit_users')) {
        return;
    }
    $group    = (string) get_user_meta($user->ID, 'community_group_name', true);
    $approved = matrix_dz_user_is_approved((int) $user->ID);
    $about    = (string) get_user_meta($user->ID, 'community_about', true);
    $activities = (string) get_user_meta($user->ID, 'community_activities', true);
    ?>
    <h2><?php esc_html_e('Community group', 'matrix-starter'); ?></h2>
    <table class="form-table" role="presentation">
        <tr>
            <th><label for="community_group_name"><?php esc_html_e('Group name', 'matrix-starter'); ?></label></th>
            <td><input type="text" class="regular-text" name="community_group_name" id="community_group_name" value="<?php echo esc_attr($group); ?>"></td>
        </tr>
        <tr>
            <th><?php esc_html_e('Account approved', 'matrix-starter'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="community_account_approved" value="1" <?php checked($approved); ?>>
                    <?php esc_html_e('Allow this contributor to sign in', 'matrix-starter'); ?>
                </label>
                <?php if ($about !== '') : ?>
                    <p class="description"><strong><?php esc_html_e('About:', 'matrix-starter'); ?></strong> <?php echo esc_html($about); ?></p>
                <?php endif; ?>
                <?php if ($activities !== '') : ?>
                    <p class="description"><strong><?php esc_html_e('Activities:', 'matrix-starter'); ?></strong> <?php echo esc_html($activities); ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
}

add_action('personal_options_update', 'matrix_dz_community_save_user_profile_fields');
add_action('edit_user_profile_update', 'matrix_dz_community_save_user_profile_fields');
function matrix_dz_community_save_user_profile_fields(int $user_id): void
{
    if (!current_user_can('edit_users')) {
        return;
    }
    if (isset($_POST['community_group_name'])) {
        update_user_meta($user_id, 'community_group_name', sanitize_text_field(wp_unslash((string) $_POST['community_group_name'])));
    }

    $want = !empty($_POST['community_account_approved']);
    $had  = matrix_dz_user_is_approved($user_id);
    if ($want && !$had) {
        matrix_dz_approve_community_user($user_id);
    } elseif (!$want && $had && !user_can($user_id, 'edit_others_posts')) {
        update_user_meta($user_id, 'community_account_approved', '0');
    }
}

/** Users list column. */
add_filter('manage_users_columns', function (array $cols): array {
    $cols['community_approved'] = __('Community', 'matrix-starter');
    return $cols;
});
add_filter('manage_users_custom_column', function ($value, string $col, int $user_id) {
    if ($col !== 'community_approved') {
        return $value;
    }
    if (!in_array('contributor', (array) get_userdata($user_id)->roles, true)
        && get_user_meta($user_id, 'community_group_name', true) === ''
        && get_user_meta($user_id, 'community_account_approved', true) === '') {
        return '—';
    }
    return matrix_dz_user_is_approved($user_id)
        ? esc_html__('Approved', 'matrix-starter')
        : esc_html__('Pending', 'matrix-starter');
}, 10, 3);

/**
 * Keep WordPress “Anyone can register” available for community sign-up.
 */
add_filter('pre_option_users_can_register', static function () {
    return '1';
});

/**
 * After native WP registration (wp-login.php?action=register):
 * Contributor role, pending until admin approval, notify admin + user.
 *
 * Priority 5 so pending meta / role exist before core’s priority-10
 * wp_send_new_user_notifications (otherwise the default admin mail still sends).
 */
add_action('register_new_user', 'matrix_dz_on_wp_community_register', 5);

function matrix_dz_on_wp_community_register(int $user_id): void
{
    $user = get_userdata($user_id);
    if (!$user) {
        return;
    }

    $user->set_role('contributor');
    update_user_meta($user_id, 'community_account_approved', '0');
    if ((string) get_user_meta($user_id, 'community_group_name', true) === '') {
        update_user_meta($user_id, 'community_group_name', $user->display_name ?: $user->user_login);
    }

    $admin_email = (string) get_option('admin_email');
    $edit_link   = admin_url('user-edit.php?user_id=' . $user_id);
    $group       = (string) get_user_meta($user_id, 'community_group_name', true);
    $email       = $user->user_email;
    $site        = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

    $admin_subject = sprintf(
        /* translators: %s: site name */
        __('[%s] New community group needs approval', 'matrix-starter'),
        $site
    );
    $admin_html = matrix_dz_community_email_html(
        __('New community registration', 'matrix-starter'),
        __('A community group has registered on the Maynooth DZ site and is waiting for approval.', 'matrix-starter'),
        [
            __('Open the user profile, tick “Allow this contributor to sign in”, then update the user.', 'matrix-starter'),
        ],
        [
            'label' => __('Review & approve account', 'matrix-starter'),
            'url'   => $edit_link,
        ],
        [
            ['label' => __('Username', 'matrix-starter'), 'value' => $user->user_login],
            ['label' => __('Group / name', 'matrix-starter'), 'value' => $group],
            ['label' => __('Email', 'matrix-starter'), 'value' => $email],
        ]
    );
    matrix_dz_community_mail($admin_email, $admin_subject, $admin_html);

    $user_subject = sprintf(
        /* translators: %s: site name */
        __('[%s] Registration received — pending approval', 'matrix-starter'),
        $site
    );
    $user_html = matrix_dz_community_email_html(
        __('Thanks for registering', 'matrix-starter'),
        sprintf(
            /* translators: %s: user display name */
            __('Hello %s — thanks for registering with the Maynooth Decarbonising Zone community.', 'matrix-starter'),
            $user->display_name
        ),
        [
            __('Your account has been created and is waiting for approval by the Climate Action Office.', 'matrix-starter'),
            __('You will receive another email when your account is enabled, with a link to sign in and set your password.', 'matrix-starter'),
        ]
    );
    matrix_dz_community_mail($email, $user_subject, $user_html);
}

/**
 * Pending community sign-ups: never send core’s default new-user emails.
 * (Admin gets our approval notice instead; user gets our pending notice.)
 */
add_filter('wp_send_new_user_notification_to_user', static function (bool $send, WP_User $user): bool {
    if (matrix_dz_is_pending_community_user($user)) {
        return false;
    }
    return $send;
}, 10, 2);

add_filter('wp_send_new_user_notification_to_admin', static function (bool $send, WP_User $user): bool {
    if (matrix_dz_is_pending_community_user($user)) {
        return false;
    }
    return $send;
}, 10, 2);

/**
 * Whether this user is a community registrant awaiting approval.
 */
function matrix_dz_is_pending_community_user(WP_User $user): bool
{
    if ((string) get_user_meta((int) $user->ID, 'community_account_approved', true) === '0') {
        return true;
    }
    return in_array('contributor', (array) $user->roles, true)
        && (string) get_user_meta((int) $user->ID, 'community_account_approved', true) !== '1';
}
