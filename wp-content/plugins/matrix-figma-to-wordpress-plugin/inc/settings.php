<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Register settings.
 */
function matrix_db_register_settings() {
    register_setting('matrix_db_settings', 'matrix_db_agent_api_key', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    register_setting('matrix_db_settings', 'matrix_db_agent_repo', array(
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
    ));
    register_setting('matrix_db_settings', 'matrix_db_agent_ref', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    register_setting('matrix_db_settings', 'matrix_db_agent_model', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    register_setting('matrix_db_settings', 'matrix_db_agent_autopr', array(
        'type'              => 'string',
        'sanitize_callback' => function ($v) {
            return $v === '1' ? '1' : '0';
        },
    ));
    register_setting('matrix_db_settings', 'matrix_db_theme_path', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    register_setting('matrix_db_settings', 'matrix_db_figma_token', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    register_setting('matrix_db_settings', 'matrix_db_local_auto', array(
        'type'              => 'string',
        'sanitize_callback' => function ($v) {
            return $v === '1' ? '1' : '0';
        },
    ));
    register_setting('matrix_db_settings', 'matrix_db_cursor_cli', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    register_setting('matrix_db_settings', 'matrix_db_wp_path', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ));
}
add_action('admin_init', 'matrix_db_register_settings');

/**
 * Settings page render.
 */
function matrix_db_render_settings_page() {
    if (! matrix_db_user_can_manage()) {
        wp_die('Forbidden');
    }
    $cfg = matrix_db_agent_config();
    $local = matrix_db_local_runner_config();
    ?>
    <div class="wrap">
        <h1>Figma to WordPress — Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('matrix_db_settings'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="matrix_db_agent_api_key">Cursor API key</label></th>
                    <td><input type="password" class="regular-text" id="matrix_db_agent_api_key" name="matrix_db_agent_api_key" value="<?php echo esc_attr($cfg['api_key']); ?>" autocomplete="off" /></td>
                </tr>
                <tr>
                    <th><label for="matrix_db_agent_repo">GitHub repo URL</label></th>
                    <td><input type="url" class="large-text" id="matrix_db_agent_repo" name="matrix_db_agent_repo" value="<?php echo esc_attr($cfg['repo']); ?>" /></td>
                </tr>
                <tr>
                    <th><label for="matrix_db_agent_ref">Starting branch</label></th>
                    <td><input type="text" class="regular-text" id="matrix_db_agent_ref" name="matrix_db_agent_ref" value="<?php echo esc_attr($cfg['ref']); ?>" /></td>
                </tr>
                <tr>
                    <th><label for="matrix_db_agent_model">Model ID (optional)</label></th>
                    <td><input type="text" class="regular-text" id="matrix_db_agent_model" name="matrix_db_agent_model" value="<?php echo esc_attr($cfg['model']); ?>" /></td>
                </tr>
                <tr>
                    <th>Auto-create PR</th>
                    <td><label><input type="checkbox" name="matrix_db_agent_autopr" value="1" <?php checked($cfg['auto_pr']); ?> /> Open PR when agent finishes</label></td>
                </tr>
                <tr>
                    <th><label for="matrix_db_figma_token">Figma access token</label></th>
                    <td>
                        <input type="password" class="regular-text" id="matrix_db_figma_token" name="matrix_db_figma_token" value="<?php echo esc_attr(matrix_db_figma_token()); ?>" autocomplete="off" />
                        <p class="description">Optional. Used to auto-import logo/image assets when seeding <code>/flexi/</code> (Figma → Settings → Personal access tokens).</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="matrix_db_theme_path">Theme root path</label></th>
                    <td>
                        <input type="text" class="large-text" id="matrix_db_theme_path" name="matrix_db_theme_path" value="<?php echo esc_attr(get_option('matrix_db_theme_path', matrix_db_theme_root())); ?>" />
                        <p class="description">For MCP preflight CLI and local prompt files. Defaults to active theme directory.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="matrix_db_wp_path">WordPress root (ABSPATH)</label></th>
                    <td>
                        <input type="text" class="large-text" id="matrix_db_wp_path" name="matrix_db_wp_path" value="<?php echo esc_attr($local['wp_path']); ?>" />
                        <p class="description">Site root for WP-CLI and local automation. Defaults to this WordPress install.</p>
                    </td>
                </tr>
                <tr>
                    <th>Local auto-run</th>
                    <td>
                        <label><input type="checkbox" name="matrix_db_local_auto" value="1" <?php checked($local['auto']); ?> /> Run <code>cursor agent</code> headlessly when you click Generate (Local)</label>
                        <p class="description">Requires Cursor CLI + API key. Builds theme files, runs <code>npm run build</code>, and seeds/resyncs <code>/flexi/</code> when the agent finishes.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="matrix_db_cursor_cli">Cursor CLI path</label></th>
                    <td>
                        <input type="text" class="large-text" id="matrix_db_cursor_cli" name="matrix_db_cursor_cli" value="<?php echo esc_attr(get_option('matrix_db_cursor_cli', $local['cursor_cli'])); ?>" placeholder="/usr/local/bin/cursor" />
                        <p class="description">Detected: <?php echo $local['cursor_cli'] !== '' ? esc_html($local['cursor_cli']) : 'not found'; ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
