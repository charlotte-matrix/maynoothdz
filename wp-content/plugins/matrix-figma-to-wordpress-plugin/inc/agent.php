<?php

if (! defined('ABSPATH')) {
    exit;
}

define('MATRIX_DB_AGENT_ENDPOINT', 'https://api.cursor.com/v1/agents');

/**
 * @return string
 */
function matrix_db_theme_root() {
    $configured = (string) get_option('matrix_db_theme_path', '');
    if ($configured !== '' && is_dir($configured)) {
        return wp_normalize_path($configured);
    }
    return wp_normalize_path(get_stylesheet_directory());
}

/**
 * @return string
 */
function matrix_db_agent_git_root() {
    $start = matrix_db_theme_root();
    $dir   = $start;
    for ($i = 0; $i < 8 && $dir && $dir !== '/' && $dir !== '.'; $i++) {
        if (is_dir($dir . '/.git')) {
            return $dir;
        }
        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }
    return '';
}

/**
 * @param string $url
 * @return string
 */
function matrix_db_normalize_remote($url) {
    $url = trim($url);
    if (preg_match('#^git@([^:]+):(.+?)(?:\.git)?$#', $url, $m)) {
        return 'https://' . $m[1] . '/' . $m[2];
    }
    if (preg_match('#^https?://#', $url)) {
        return preg_replace('#\.git$#', '', $url);
    }
    return $url;
}

/**
 * @return string
 */
function matrix_db_detect_repo() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = '';
    $root   = matrix_db_agent_git_root();
    if ($root === '') {
        return $cached;
    }
    $config = $root . '/.git/config';
    if (! is_readable($config)) {
        return $cached;
    }
    $contents = (string) file_get_contents($config);
    if (preg_match('#\[remote "origin"\][^\[]*?url\s*=\s*(\S+)#s', $contents, $m)) {
        $cached = matrix_db_normalize_remote($m[1]);
    }
    return $cached;
}

/**
 * @return array<string,mixed>
 */
function matrix_db_agent_config() {
    return array(
        'api_key' => (string) get_option('matrix_db_agent_api_key', ''),
        'repo'    => (string) get_option('matrix_db_agent_repo', matrix_db_detect_repo()),
        'ref'     => (string) get_option('matrix_db_agent_ref', 'main'),
        'model'   => (string) get_option('matrix_db_agent_model', ''),
        'auto_pr' => get_option('matrix_db_agent_autopr', '1') === '1',
    );
}

/**
 * @return bool
 */
function matrix_db_agent_ready() {
    $cfg = matrix_db_agent_config();
    return $cfg['api_key'] !== '' && $cfg['repo'] !== '';
}

/**
 * @param string $prompt
 * @return array<string,mixed>|WP_Error
 */
function matrix_db_agent_create($prompt) {
    $cfg = matrix_db_agent_config();
    if ($cfg['api_key'] === '') {
        return new WP_Error('no_key', 'Set the Cursor API key in Figma to WordPress → Settings.');
    }

    $body = array(
        'prompt'       => array('text' => $prompt),
        'repos'        => array(array('url' => $cfg['repo'], 'startingRef' => $cfg['ref'])),
        'autoCreatePR' => (bool) $cfg['auto_pr'],
    );
    if ($cfg['model'] !== '') {
        $body['model'] = array('id' => $cfg['model']);
    }

    $resp = wp_remote_post(
        MATRIX_DB_AGENT_ENDPOINT,
        array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($cfg['api_key'] . ':'),
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($body),
        )
    );

    if (is_wp_error($resp)) {
        return $resp;
    }
    $code = wp_remote_retrieve_response_code($resp);
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    if ($code < 200 || $code >= 300) {
        return new WP_Error('api_error', 'Cursor API ' . $code . ': ' . wp_remote_retrieve_body($resp));
    }
    return is_array($data) ? $data : array();
}

/**
 * @param array<string,mixed> $res
 * @return array<string,mixed>
 */
function matrix_db_agent_obj($res) {
    if (isset($res['agent']) && is_array($res['agent'])) {
        return $res['agent'];
    }
    return is_array($res) ? $res : array();
}

/**
 * @param array<string,mixed> $agent
 * @return string
 */
function matrix_db_agent_url($agent) {
    if (! empty($agent['url'])) {
        return (string) $agent['url'];
    }
    if (! empty($agent['id'])) {
        return 'https://cursor.com/agents/' . rawurlencode((string) $agent['id']);
    }
    return '';
}

/**
 * @param string $agent_id
 * @return array<string,mixed>|WP_Error
 */
function matrix_db_agent_get($agent_id) {
    $cfg = matrix_db_agent_config();
    if ($cfg['api_key'] === '') {
        return new WP_Error('no_key', 'No API key');
    }
    $resp = wp_remote_get(
        MATRIX_DB_AGENT_ENDPOINT . '/' . rawurlencode($agent_id),
        array(
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($cfg['api_key'] . ':'),
            ),
        )
    );
    if (is_wp_error($resp)) {
        return $resp;
    }
    $data = json_decode(wp_remote_retrieve_body($resp), true);
    return is_array($data) ? $data : array();
}

/**
 * @param array<string,mixed> $info
 * @return string
 */
function matrix_db_agent_extract_pr($info) {
    if (! empty($info['target']['prUrl'])) {
        return (string) $info['target']['prUrl'];
    }
    if (! empty($info['git']['branches']) && is_array($info['git']['branches'])) {
        foreach ($info['git']['branches'] as $branch) {
            if (! empty($branch['prUrl'])) {
                return (string) $branch['prUrl'];
            }
        }
    }
    return '';
}

/**
 * @param int    $job_id
 * @param string $runtime cloud|local
 * @param array<int,string>|null $section_ids
 * @param array<string,mixed> $options
 * @return array<string,mixed>|WP_Error
 */
function matrix_db_dispatch_job($job_id, $runtime = 'cloud', $section_ids = null, $options = array()) {
    $post = get_post($job_id);
    if (! $post || $post->post_type !== MATRIX_DB_CPT) {
        return new WP_Error('not_found', 'Build job not found.');
    }

    $prompt = matrix_db_build_job_prompt($job_id, $section_ids, $options);
    update_post_meta($job_id, MATRIX_DB_PROMPT_META, $prompt);
    update_post_meta($job_id, MATRIX_DB_RUNTIME_META, $runtime);

    if ($runtime === 'local') {
        matrix_db_write_local_prompt_file($job_id, $prompt);

        if (matrix_db_local_runner_ready()) {
            $started = matrix_db_local_runner_start($job_id, $section_ids);
            if (is_wp_error($started)) {
                matrix_db_set_job_status($job_id, 'queued_local');
                $sections = matrix_db_get_sections($job_id);
                foreach ($sections as $section) {
                    if ($section_ids === null || in_array($section['id'], $section_ids, true)) {
                        matrix_db_update_section($job_id, $section['id'], array('status' => 'queued_local'));
                    }
                }
                return $started;
            }
            return array(
                'success' => true,
                'runtime' => 'local',
                'auto'    => true,
            );
        }

        matrix_db_set_job_status($job_id, 'queued_local');
        $sections = matrix_db_get_sections($job_id);
        foreach ($sections as $section) {
            if ($section_ids === null || in_array($section['id'], $section_ids, true)) {
                matrix_db_update_section($job_id, $section['id'], array('status' => 'queued_local'));
            }
        }
        return array('success' => true, 'runtime' => 'local', 'auto' => false);
    }

    $res = matrix_db_agent_create($prompt);
    if (is_wp_error($res)) {
        matrix_db_set_job_status($job_id, 'failed');
        return $res;
    }

    $agent    = matrix_db_agent_obj($res);
    $agent_id = isset($agent['id']) ? (string) $agent['id'] : '';
    update_post_meta($job_id, MATRIX_DB_AGENT_ID, $agent_id);
    update_post_meta($job_id, MATRIX_DB_AGENT_URL, matrix_db_agent_url($agent));
    matrix_db_set_job_status($job_id, 'generating');

    $sections = matrix_db_get_sections($job_id);
    foreach ($sections as $section) {
        if ($section_ids === null || in_array($section['id'], $section_ids, true)) {
            matrix_db_update_section(
                $job_id,
                $section['id'],
                array(
                    'status'   => 'generating',
                    'agent_id' => $agent_id,
                )
            );
        }
    }

    matrix_db_agent_ensure_cron();
    return $res;
}

/**
 * @param int    $job_id
 * @param string $prompt
 */
function matrix_db_write_local_prompt_file($job_id, $prompt) {
    $path = matrix_db_local_prompt_path($job_id);
    $dir  = dirname($path);
    if (! is_dir($dir)) {
        wp_mkdir_p($dir);
    }
    file_put_contents($path, "# Figma to WordPress job {$job_id}\n\n" . $prompt);
    return $path;
}

/**
 * @param int $job_id
 * @return string
 */
function matrix_db_local_prompt_path($job_id) {
    return matrix_db_theme_root() . '/.cursor/figma-to-wordpress-jobs/job-' . absint($job_id) . '.md';
}

/**
 * Poll in-progress jobs for PR URLs.
 */
function matrix_db_agent_poll() {
    if (! matrix_db_agent_ready()) {
        return;
    }

    $query = new WP_Query(
        array(
            'post_type'      => MATRIX_DB_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'no_found_rows'  => true,
            'meta_query'     => array(
                array(
                    'key'     => MATRIX_DB_JOB_STATUS,
                    'value'   => array('generating', 'redo_queued'),
                    'compare' => 'IN',
                ),
            ),
        )
    );

    foreach ($query->posts as $post) {
        $agent_id = (string) get_post_meta($post->ID, MATRIX_DB_AGENT_ID, true);
        if ($agent_id === '') {
            continue;
        }
        $info = matrix_db_agent_get($agent_id);
        if (is_wp_error($info)) {
            continue;
        }
        $pr = matrix_db_agent_extract_pr($info);
        if ($pr !== '') {
            update_post_meta($post->ID, MATRIX_DB_PR_URL, $pr);
        }

        $status = isset($info['status']) ? (string) $info['status'] : '';
        if (in_array(strtoupper($status), array('FINISHED', 'COMPLETED', 'DONE'), true)) {
            matrix_db_set_job_status($post->ID, 'done');
            $sections = matrix_db_get_sections($post->ID);
            foreach ($sections as $section) {
                if (($section['status'] ?? '') === 'generating') {
                    matrix_db_update_section(
                        $post->ID,
                        $section['id'],
                        array(
                            'status' => 'done',
                            'pr_url' => $pr,
                        )
                    );
                }
            }
        }
    }
}
add_action(MATRIX_DB_AGENT_CRON, 'matrix_db_agent_poll');

/**
 * @param array<string,array<string,mixed>> $schedules
 * @return array<string,array<string,mixed>>
 */
function matrix_db_agent_cron_interval($schedules) {
    $schedules['matrix_db_5min'] = array(
        'interval' => 300,
        'display'  => 'Every 5 minutes (Figma to WordPress)',
    );
    return $schedules;
}
add_filter('cron_schedules', 'matrix_db_agent_cron_interval');

/**
 * Ensure cron scheduled.
 */
function matrix_db_agent_ensure_cron() {
    if (! wp_next_scheduled(MATRIX_DB_AGENT_CRON)) {
        wp_schedule_event(time() + 120, 'matrix_db_5min', MATRIX_DB_AGENT_CRON);
    }
}

/**
 * @param string $url
 * @param string $message
 */
function matrix_db_redirect_notice($url, $message) {
    $url = add_query_arg('matrix_db_notice', rawurlencode($message), $url);
    wp_safe_redirect($url);
    exit;
}
