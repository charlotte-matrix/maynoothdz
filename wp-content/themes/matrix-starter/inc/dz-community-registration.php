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
    // Proper reset key.
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

    $message  = sprintf("Hello %s,\n\n", $user->display_name);
    $message .= "Your Maynooth DZ community group account has been approved.\n\n";
    $message .= "You can sign in here:\n{$login_url}\n\n";
    $message .= "Set or reset your password here:\n{$reset}\n\n";
    $message .= "Once signed in, you can draft community updates and events. An administrator will review and publish them.\n\n";
    $message .= "— Climate Action Office\n";

    wp_mail($user->user_email, $subject, $message);

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
 */
add_action('register_new_user', 'matrix_dz_on_wp_community_register');

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

    $admin_subject = sprintf(
        /* translators: %s: site name */
        __('[%s] New community group registration', 'matrix-starter'),
        wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
    );
    $admin_body  = "A community group has registered interest on the Maynooth DZ site.\n\n";
    $admin_body .= "Username: {$user->user_login}\n";
    $admin_body .= "Display name / group: {$group}\n";
    $admin_body .= "Email: {$email}\n\n";
    $admin_body .= "Review and approve the account:\n{$edit_link}\n";
    $admin_body .= "(Tick “Allow this contributor to sign in” and update the user.)\n";

    wp_mail($admin_email, $admin_subject, $admin_body);

    $user_subject = sprintf(
        /* translators: %s: site name */
        __('[%s] Registration received — pending approval', 'matrix-starter'),
        wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
    );
    $user_body  = "Hello {$user->display_name},\n\n";
    $user_body .= "Thanks for registering with the Maynooth Decarbonising Zone community.\n\n";
    $user_body .= "Your account has been created and is waiting for approval by the Climate Action Office.\n";
    $user_body .= "You will receive another email when your account is enabled, with a link to sign in and set your password.\n\n";
    $user_body .= "— Climate Action Office\n";
    $user_body .= "climateaction@kildarecoco.ie\n";

    wp_mail($email, $user_subject, $user_body);
}

/**
 * Don’t send WP’s default “set your password” mail until the account is approved.
 */
add_filter('wp_send_new_user_notification_to_user', static function (bool $send, WP_User $user): bool {
    if ((string) get_user_meta((int) $user->ID, 'community_account_approved', true) === '0') {
        return false;
    }
    return $send;
}, 10, 2);

/** Prefer our admin email over the default new-user notice for pending contributors. */
add_filter('wp_send_new_user_notification_to_admin', static function (bool $send, WP_User $user): bool {
    if ((string) get_user_meta((int) $user->ID, 'community_account_approved', true) === '0') {
        return false;
    }
    return $send;
}, 10, 2);
