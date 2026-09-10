<?php

if (! defined('ABSPATH')) {
    exit;
}

define('MATRIX_DB_LOCAL_PID_META', '_matrix_db_local_pid');
define('MATRIX_DB_LOCAL_LOG_META', '_matrix_db_local_log');
define('MATRIX_DB_LOCAL_STARTED_META', '_matrix_db_local_started');
define('MATRIX_DB_LOCAL_ACTIVE_SECTIONS_META', '_matrix_db_local_active_sections');

/**
 * @return array{cursor_cli:string,api_key:string,auto:bool,wp_path:string}
 */
function matrix_db_local_runner_config() {
    $cursor = (string) get_option('matrix_db_cursor_cli', '');
    if ($cursor === '' || ! is_executable($cursor)) {
        $cursor = matrix_db_local_runner_detect_cursor_cli();
    }

    return array(
        'cursor_cli' => $cursor,
        'api_key'    => (string) get_option('matrix_db_agent_api_key', ''),
        'auto'       => get_option('matrix_db_local_auto', '1') === '1',
        'wp_path'    => (string) get_option('matrix_db_wp_path', ABSPATH),
    );
}

/**
 * @return string
 */
function matrix_db_local_runner_detect_cursor_cli() {
    $candidates = array(
        '/usr/local/bin/cursor',
        '/Applications/Cursor.app/Contents/Resources/app/bin/cursor',
    );

    foreach ($candidates as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }

    $which = trim((string) shell_exec('command -v cursor 2>/dev/null'));
    return ($which !== '' && is_executable($which)) ? $which : '';
}

/**
 * @return bool
 */
function matrix_db_local_runner_ready() {
    $cfg = matrix_db_local_runner_config();
    if (! $cfg['auto'] || $cfg['cursor_cli'] === '' || $cfg['api_key'] === '') {
        return false;
    }

    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    return function_exists('shell_exec') && ! in_array('shell_exec', $disabled, true);
}

/**
 * PATH/HOME for subprocesses spawned from PHP (Local/Docker use a minimal PATH).
 *
 * @return array{PATH:string,HOME:string}
 */
function matrix_db_local_runner_env() {
    $path = '/usr/local/bin:/usr/bin:/bin:/sbin:/opt/homebrew/bin';
    $home = getenv('HOME');
    if (! is_string($home) || $home === '') {
        $home = isset($_SERVER['HOME']) ? (string) $_SERVER['HOME'] : '';
    }
    if ($home === '' && function_exists('posix_getuid') && function_exists('posix_getpwuid')) {
        $info = posix_getpwuid(posix_getuid());
        if (is_array($info) && ! empty($info['dir'])) {
            $home = (string) $info['dir'];
        }
    }
    if ($home === '') {
        $home = '/tmp';
    }

    return array(
        'PATH' => $path,
        'HOME' => $home,
    );
}

/**
 * @param int $job_id
 * @return string
 */
function matrix_db_local_job_log_path($job_id) {
    return matrix_db_theme_root() . '/.cursor/figma-to-wordpress-jobs/job-' . absint($job_id) . '.log';
}

/**
 * @param int $job_id
 * @return string
 */
function matrix_db_local_job_runner_script_path($job_id) {
    return matrix_db_theme_root() . '/.cursor/figma-to-wordpress-jobs/run-job-' . absint($job_id) . '.sh';
}

/**
 * @param int $pid
 * @return bool
 */
function matrix_db_local_process_running($pid) {
    $pid = (int) $pid;
    if ($pid <= 0) {
        return false;
    }

    if (function_exists('posix_kill')) {
        return @posix_kill($pid, 0);
    }

    $out = shell_exec('/bin/ps -p ' . $pid . ' -o pid= 2>/dev/null');
    return is_string($out) && trim($out) !== '';
}

/**
 * Detect agent shell failures in the job log (e.g. exit 127).
 *
 * @param int $job_id
 * @return string Error message or empty string.
 */
function matrix_db_local_runner_log_agent_error($job_id) {
    $log = matrix_db_local_job_log_path($job_id);
    if (! is_file($log)) {
        return '';
    }

    $tail = (string) file_get_contents($log);
    if (preg_match('/Agent exited with code (\d+)/', $tail, $m)) {
        $code = (int) $m[1];
        if ($code !== 0) {
            if ($code === 127 || strpos($tail, 'command not found') !== false || strpos($tail, 'env: bash') !== false) {
                return 'Cursor agent could not start (shell PATH/bash). Retry after updating the plugin, or run the prompt manually in Cursor.';
            }
            return 'Cursor agent exited with code ' . $code . '. See job log.';
        }
    }

    return '';
}

/**
 * Resolve nohup binary (Docker/Local PHP often has a minimal PATH).
 *
 * @return string
 */
function matrix_db_local_runner_nohup_path() {
    foreach (array('/usr/bin/nohup', '/bin/nohup') as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }

    $which = trim((string) shell_exec('command -v nohup 2>/dev/null'));
    return ($which !== '' && is_executable($which)) ? $which : '';
}

/**
 * Spawn runner script in background; return child PID or 0.
 *
 * @param string $script
 * @param string $log
 * @return int
 */
function matrix_db_local_runner_spawn_background($script, $log) {
    $env    = matrix_db_local_runner_env();
    $nohup  = matrix_db_local_runner_nohup_path();
    $env_prefix = sprintf(
        'PATH=%s HOME=%s ',
        escapeshellarg($env['PATH']),
        escapeshellarg($env['HOME'])
    );

    if ($nohup !== '') {
        $cmd = sprintf(
            '/usr/bin/env %s %s %s >> %s 2>&1 & echo $!',
            'PATH=' . escapeshellarg($env['PATH']),
            escapeshellarg($nohup),
            escapeshellarg($script),
            escapeshellarg($log)
        );
        $pid = trim((string) shell_exec($cmd));
        if ($pid !== '' && ctype_digit($pid)) {
            return (int) $pid;
        }
        file_put_contents($log, "Spawn via nohup failed; trying bash fallback.\n", FILE_APPEND);
    }

    $inner = $env_prefix . escapeshellarg($script) . ' >> ' . escapeshellarg($log) . ' 2>&1 & echo $!';
    $pid   = trim((string) shell_exec('/bin/bash -c ' . escapeshellarg($inner)));
    if ($pid !== '' && ctype_digit($pid)) {
        return (int) $pid;
    }

    return 0;
}

/**
 * Ensure theme .cursor/mcp.json includes matrix-starter (and Figma when configured globally).
 *
 * @param string $theme
 * @return void
 */
function matrix_db_local_runner_ensure_mcp_config($theme) {
    $cursor_dir = rtrim($theme, '/') . '/.cursor';
    $mcp_path   = $cursor_dir . '/mcp.json';
    $mcp_entry  = rtrim($theme, '/') . '/mcp-server/dist/index.js';

    if (! is_dir($cursor_dir)) {
        wp_mkdir_p($cursor_dir);
    }

    $servers = array();
    if (is_file($mcp_path)) {
        $decoded = json_decode((string) file_get_contents($mcp_path), true);
        if (is_array($decoded['mcpServers'] ?? null)) {
            $servers = $decoded['mcpServers'];
        }
    }

    $home_mcp = matrix_db_local_runner_env()['HOME'] . '/.cursor/mcp.json';
    if (is_file($home_mcp)) {
        $global = json_decode((string) file_get_contents($home_mcp), true);
        if (is_array($global['mcpServers']['Figma'] ?? null) && empty($servers['Figma'])) {
            $servers['Figma'] = $global['mcpServers']['Figma'];
        }
    }

    if (is_file($mcp_entry)) {
        $servers['matrix-starter'] = array(
            'command' => 'node',
            'args'    => array($mcp_entry),
        );
    }

    if (empty($servers)) {
        return;
    }

    $payload = array('mcpServers' => $servers);
    file_put_contents($mcp_path, wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

/**
 * @return string
 */
function matrix_db_local_runner_wp_cli() {
    foreach (array('/usr/local/bin/wp', '/opt/homebrew/bin/wp') as $path) {
        if (is_executable($path)) {
            return $path;
        }
    }

    $which = trim((string) shell_exec('command -v wp 2>/dev/null'));
    return ($which !== '' && is_executable($which)) ? $which : '';
}

/**
 * Section ids included in the current local agent run (new build or redo).
 *
 * @param int $job_id
 * @return array<int,string>
 */
function matrix_db_local_runner_active_section_ids($job_id) {
    $raw = get_post_meta($job_id, MATRIX_DB_LOCAL_ACTIVE_SECTIONS_META, true);
    return is_array($raw) ? array_values(array_filter(array_map('strval', $raw))) : array();
}

/**
 * Build shell runner with explicit PATH (Local PHP subprocesses strip it).
 *
 * @param string $theme
 * @param string $prompt_path
 * @param string $log
 * @param string $cursor_cli
 * @param string $api_key
 * @param int    $job_id
 * @param string $wp_path
 * @param string $wp_cli
 * @return string
 */
function matrix_db_local_runner_build_script($theme, $prompt_path, $log, $cursor_cli, $api_key, $job_id, $wp_path, $wp_cli) {
    $env = matrix_db_local_runner_env();

    return '#!/bin/bash
set -uo pipefail
export PATH=' . escapeshellarg($env['PATH']) . '
export HOME=' . escapeshellarg($env['HOME']) . '
THEME=' . escapeshellarg($theme) . '
PROMPT_FILE=' . escapeshellarg($prompt_path) . '
LOG=' . escapeshellarg($log) . '
CURSOR=' . escapeshellarg($cursor_cli) . '
export CURSOR_API_KEY=' . escapeshellarg($api_key) . '
cd "$THEME" || exit 1
echo "=== Figma to WordPress local agent started $(/bin/date -u +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || echo unknown) ===" >> "$LOG"
echo "MCP servers (before agent):" >> "$LOG"
"$CURSOR" agent mcp list >> "$LOG" 2>&1 || true
"$CURSOR" agent mcp enable Figma >> "$LOG" 2>&1 || true
"$CURSOR" agent mcp enable matrix-starter >> "$LOG" 2>&1 || true
PROMPT=$(<"$PROMPT_FILE")
(
  while true; do
    sleep 20
    echo "[$(/bin/date -u +%H:%M:%SZ 2>/dev/null || echo ?)] Agent still running (log $(/usr/bin/wc -c < "$LOG" 2>/dev/null | /usr/bin/tr -d " ") bytes)…" >> "$LOG"
  done
) &
HEARTBEAT_PID=$!
trap "kill $HEARTBEAT_PID 2>/dev/null || true" EXIT
echo "Launching cursor agent (streamed output)…" >> "$LOG"
"$CURSOR" agent --print --trust --approve-mcps --force --workspace "$THEME" --output-format stream-json --stream-partial-output "$PROMPT" 2>&1 | while IFS= read -r line; do
  echo "$line" >> "$LOG"
done
EXIT=${PIPESTATUS[0]}
kill $HEARTBEAT_PID 2>/dev/null || true
echo "=== Agent exited with code $EXIT $(/bin/date -u +%Y-%m-%dT%H:%M:%SZ 2>/dev/null || echo unknown) ===" >> "$LOG"
if [ "$EXIT" -eq 0 ]; then
  echo "=== Running finish pipeline (build + seed /flexi/) ===" >> "$LOG"
  WP_CLI=' . escapeshellarg($wp_cli) . '
  WP_PATH=' . escapeshellarg($wp_path) . '
  JOB_ID=' . (int) $job_id . '
  if [ -n "$WP_CLI" ] && [ -x "$WP_CLI" ] && [ -n "$WP_PATH" ]; then
    "$WP_CLI" eval "matrix_db_local_runner_after_agent($JOB_ID);" --path="$WP_PATH" >> "$LOG" 2>&1 || true
  else
    echo "WP-CLI not found — open the job in WP Admin or wait for cron to seed /flexi/." >> "$LOG"
  fi
fi
exit $EXIT
';
}

/**
 * Start headless Cursor agent for a local job.
 *
 * @param int                    $job_id
 * @param array<int,string>|null $section_ids
 * @return true|WP_Error
 */
function matrix_db_local_runner_start($job_id, $section_ids = null) {
    $cfg = matrix_db_local_runner_config();
    if (! $cfg['auto']) {
        return new WP_Error('local_manual', 'Local auto-run is disabled in Settings.');
    }
    if ($cfg['cursor_cli'] === '') {
        return new WP_Error('no_cursor', 'Cursor CLI not found. Install Cursor or set the CLI path in Settings.');
    }
    if ($cfg['api_key'] === '') {
        return new WP_Error('no_key', 'Set the Cursor API key in Settings (used for local headless agent).');
    }

    $prompt_path = matrix_db_local_prompt_path($job_id);
    if (! is_file($prompt_path)) {
        return new WP_Error('no_prompt', 'Prompt file missing for this job.');
    }

    $theme   = matrix_db_theme_root();
    $log     = matrix_db_local_job_log_path($job_id);
    $script  = matrix_db_local_job_runner_script_path($job_id);
    $jobs_dir = dirname($prompt_path);

    if (! is_dir($jobs_dir)) {
        wp_mkdir_p($jobs_dir);
    }

    matrix_db_local_runner_ensure_mcp_config($theme);

    $script_body = matrix_db_local_runner_build_script(
        $theme,
        $prompt_path,
        $log,
        $cfg['cursor_cli'],
        $cfg['api_key'],
        $job_id,
        $cfg['wp_path'],
        matrix_db_local_runner_wp_cli()
    );

    if (file_put_contents($script, $script_body) === false) {
        return new WP_Error('script_write', 'Could not write local runner script.');
    }
    chmod($script, 0755);

    file_put_contents($log, "=== Queued local agent for job {$job_id} ===\n");

    $pid = matrix_db_local_runner_spawn_background($script, $log);

    if ($pid <= 0) {
        file_put_contents($log, "ERROR: Failed to spawn background agent process.\n", FILE_APPEND);
        return new WP_Error('spawn_failed', 'Failed to start Cursor agent process. Check job log for details.');
    }

    update_post_meta($job_id, MATRIX_DB_LOCAL_PID_META, $pid);
    update_post_meta($job_id, MATRIX_DB_LOCAL_LOG_META, $log);
    update_post_meta($job_id, MATRIX_DB_LOCAL_STARTED_META, gmdate('c'));
    matrix_db_set_job_status($job_id, 'generating_local');

    $sections = matrix_db_get_sections($job_id);
    $active   = array();
    foreach ($sections as $section) {
        $sid = (string) ($section['id'] ?? '');
        if ($section_ids !== null && ! in_array($sid, $section_ids, true)) {
            continue;
        }
        if ($section_ids === null && ! in_array($section['status'] ?? '', array('queued', 'queued_local', 'redo_queued', 'generating'), true)) {
            continue;
        }
        matrix_db_update_section($job_id, $sid, array('status' => 'generating_local'));
        $active[] = $sid;
    }
    if (empty($active)) {
        return new WP_Error('no_sections', 'No sections queued for this local run.');
    }
    update_post_meta($job_id, MATRIX_DB_LOCAL_ACTIVE_SECTIONS_META, $active);
    delete_post_meta($job_id, '_matrix_db_local_finish_message');

    matrix_db_local_runner_ensure_cron();

    return true;
}

/**
 * Tail log file for admin UI.
 *
 * @param int $job_id
 * @param int $max_bytes
 * @return string
 */
function matrix_db_local_runner_log_tail($job_id, $max_bytes = 12000) {
    $log = (string) get_post_meta($job_id, MATRIX_DB_LOCAL_LOG_META, true);
    if ($log === '' || ! is_file($log)) {
        $log = matrix_db_local_job_log_path($job_id);
    }
    if (! is_file($log)) {
        return '';
    }

    $size = filesize($log);
    if ($size <= $max_bytes) {
        return (string) file_get_contents($log);
    }

    $fp = fopen($log, 'rb');
    if (! $fp) {
        return '';
    }
    fseek($fp, -$max_bytes, SEEK_END);
    $chunk = fread($fp, $max_bytes);
    fclose($fp);

    return '…' . $chunk;
}

/**
 * Run build + seed immediately after the Cursor agent exits (called from runner script).
 *
 * @param int $job_id
 * @return void
 */
function matrix_db_local_runner_after_agent($job_id) {
    $job_id = (int) $job_id;
    if ($job_id <= 0 || matrix_db_job_status($job_id) !== 'generating_local') {
        return;
    }

    $result = matrix_db_local_runner_finish_pipeline($job_id);
    update_post_meta($job_id, '_matrix_db_local_finish_message', $result['message']);

    $log = matrix_db_local_job_log_path($job_id);
    file_put_contents($log, '=== Finish pipeline: ' . $result['message'] . " ===\n", FILE_APPEND);
}

/**
 *
 * @param int $job_id
 * @return array{success:bool,message:string}
 */
function matrix_db_local_runner_finish_pipeline($job_id) {
    $sections   = matrix_db_get_sections($job_id);
    $active_ids = matrix_db_local_runner_active_section_ids($job_id);
    $missing    = array();

    foreach ($sections as $section) {
        $section_id = (string) ($section['id'] ?? '');
        if (! empty($active_ids) && ! in_array($section_id, $active_ids, true)) {
            continue;
        }
        if (empty($active_ids)) {
            continue;
        }

        if (in_array($section['section_type'] ?? 'flexi', array('flexi', 'form_block'), true)) {
            $check = matrix_db_qc_files_exist($section);
            if (! $check['valid']) {
                $missing[] = $section['layout'] ?? 'unknown';
            }
        }
    }

    if (! empty($missing)) {
        matrix_db_set_job_status($job_id, 'local_failed');
        foreach ($sections as $section) {
            if (($section['status'] ?? '') === 'generating_local') {
                matrix_db_update_section($job_id, $section['id'], array('status' => 'local_failed'));
            }
        }
        delete_post_meta($job_id, MATRIX_DB_LOCAL_ACTIVE_SECTIONS_META);
        return array(
            'success' => false,
            'message' => 'Local agent finished but theme files are missing: ' . implode(', ', $missing),
        );
    }

    matrix_db_local_runner_theme_build();

    $seed_options = array(
        'resync_existing' => true,
    );
    if (! empty($active_ids)) {
        $seed_options['section_ids'] = $active_ids;
    }

    $seed = matrix_db_seed_job_flexi($job_id, $seed_options);

    matrix_db_set_job_status($job_id, 'done');
    foreach ($sections as $section) {
        $section_id = (string) ($section['id'] ?? '');
        if (! empty($active_ids) && ! in_array($section_id, $active_ids, true)) {
            continue;
        }
        if (in_array($section['status'] ?? '', array('generating_local', 'queued_local', 'redo_queued', 'queued'), true)) {
            matrix_db_update_section(
                $job_id,
                $section_id,
                array(
                    'status' => 'done',
                )
            );
        }
    }

    delete_post_meta($job_id, MATRIX_DB_LOCAL_PID_META);
    delete_post_meta($job_id, MATRIX_DB_LOCAL_ACTIVE_SECTIONS_META);

    return array(
        'success' => true,
        'message' => 'Local build complete. ' . ($seed['message'] ?? ''),
    );
}

/**
 * Run npm theme build when Tailwind may have changed.
 */
function matrix_db_local_runner_theme_build() {
    $theme = matrix_db_theme_root();
    $pkg   = $theme . '/package.json';
    if (! is_file($pkg)) {
        return;
    }

    $env = matrix_db_local_runner_env();
    $cmd = sprintf(
        'cd %s && PATH=%s npm run build 2>&1',
        escapeshellarg($theme),
        escapeshellarg($env['PATH'])
    );
    exec($cmd, $output, $code);
    $log = matrix_db_theme_root() . '/.cursor/figma-to-wordpress-jobs/theme-build.log';
    file_put_contents($log, implode("\n", $output) . "\nExit: {$code}\n", FILE_APPEND);
}

/**
 * Live progress for admin UI / AJAX.
 *
 * @param int $job_id
 * @return array<string,mixed>
 */
function matrix_db_local_runner_progress($job_id) {
    $pid       = (int) get_post_meta($job_id, MATRIX_DB_LOCAL_PID_META, true);
    $started   = (string) get_post_meta($job_id, MATRIX_DB_LOCAL_STARTED_META, true);
    $log       = matrix_db_local_job_log_path($job_id);
    $running   = $pid > 0 && matrix_db_local_process_running($pid);
    $log_bytes = is_file($log) ? (int) filesize($log) : 0;
    $log_age   = is_file($log) ? max(0, time() - (int) filemtime($log)) : 0;
    $elapsed   = 0;

    if ($started !== '') {
        $ts = strtotime($started);
        if ($ts) {
            $elapsed = max(0, time() - $ts);
        }
    }

    $sections  = matrix_db_get_sections($job_id);
    $file_rows = array();
    $all_ready = true;

    foreach ($sections as $section) {
        $type = $section['section_type'] ?? 'flexi';
        if (! in_array($type, array('flexi', 'form_block'), true)) {
            continue;
        }
        $check = matrix_db_qc_files_exist($section);
        $ready = $check['valid'];
        if (! $ready) {
            $all_ready = false;
        }
        $file_rows[] = array(
            'layout'   => $section['layout'] ?? '',
            'acf'      => $ready,
            'template' => $ready,
        );
    }

    $hint = 'Typical builds take 5–15 minutes. Output streams into the log every ~20s.';
    if ($all_ready && $running) {
        $hint = 'Theme files are on disk — waiting for the agent process to finish, then /flexi/ will be seeded automatically.';
    } elseif ($all_ready && ! $running) {
        $hint = 'Theme files found — finalizing build…';
    } elseif (! $running && $elapsed > 60 && $log_bytes < 200) {
        $hint = 'Agent may have failed to start. Check the log or click Retry local agent.';
    } elseif ($log_age > 120 && $running) {
        $hint = 'Agent is still running but the log has been quiet — this is normal while it reads Figma and writes files.';
    }

    return array(
        'pid'         => $pid,
        'running'     => $running,
        'started_at'  => $started,
        'elapsed'     => $elapsed,
        'log_bytes'   => $log_bytes,
        'log_age'     => $log_age,
        'files_ready' => $all_ready,
        'sections'    => $file_rows,
        'hint'        => $hint,
    );
}

/**
 * Poll generating_local jobs.
 */
function matrix_db_local_runner_poll() {
    $query = new WP_Query(
        array(
            'post_type'      => MATRIX_DB_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'no_found_rows'  => true,
            'meta_query'     => array(
                array(
                    'key'   => MATRIX_DB_JOB_STATUS,
                    'value' => 'generating_local',
                ),
            ),
        )
    );

    foreach ($query->posts as $post) {
        matrix_db_local_runner_poll_job((int) $post->ID);
    }
}
add_action(MATRIX_DB_AGENT_CRON, 'matrix_db_local_runner_poll', 5);

/**
 * @param int $job_id
 */
function matrix_db_local_runner_poll_job($job_id) {
    if (matrix_db_job_status($job_id) !== 'generating_local') {
        return;
    }

    $progress = matrix_db_local_runner_progress($job_id);
    $pid      = (int) $progress['pid'];

    if ($progress['running']) {
        if (! empty($progress['files_ready'])) {
            // Files exist but agent still running — wait for exit unless stale (30m).
            if ((int) $progress['elapsed'] < 1800) {
                return;
            }
        } else {
            return;
        }
    }

    $log = matrix_db_local_job_log_path($job_id);
    if ($pid <= 0 && is_file($log) && (time() - filemtime($log)) < 30) {
        return;
    }

    $agent_error = matrix_db_local_runner_log_agent_error($job_id);
    if ($agent_error !== '' && empty($progress['files_ready'])) {
        matrix_db_set_job_status($job_id, 'local_failed');
        update_post_meta($job_id, '_matrix_db_local_finish_message', $agent_error);
        delete_post_meta($job_id, MATRIX_DB_LOCAL_PID_META);
        $sections = matrix_db_get_sections($job_id);
        foreach ($sections as $section) {
            if (($section['status'] ?? '') === 'generating_local') {
                matrix_db_update_section($job_id, $section['id'], array('status' => 'local_failed'));
            }
        }
        return;
    }

    $result = matrix_db_local_runner_finish_pipeline($job_id);
    update_post_meta($job_id, '_matrix_db_local_finish_message', $result['message']);
}

/**
 * Schedule cron if needed.
 */
function matrix_db_local_runner_ensure_cron() {
    if (function_exists('matrix_db_agent_ensure_cron')) {
        matrix_db_agent_ensure_cron();
    }
}

/**
 * AJAX: job status + log for admin polling.
 */
function matrix_db_local_runner_ajax_status() {
    if (! matrix_db_user_can_manage()) {
        wp_send_json_error(array('message' => 'Forbidden'), 403);
    }

    check_ajax_referer('matrix_db_local_status', 'nonce');

    $job_id = isset($_POST['job_id']) ? absint($_POST['job_id']) : 0;
    if (! $job_id) {
        wp_send_json_error(array('message' => 'Missing job id'));
    }

    $status = matrix_db_job_status($job_id);
    $progress = matrix_db_local_runner_progress($job_id);
    $running = ! empty($progress['running']);

    if ($status === 'generating_local' && ! $running) {
        matrix_db_local_runner_poll_job($job_id);
        $status = matrix_db_job_status($job_id);
        $progress = matrix_db_local_runner_progress($job_id);
        $running = ! empty($progress['running']);
    }

    wp_send_json_success(
        array(
            'status'      => $status,
            'running'     => $running,
            'log'         => matrix_db_local_runner_log_tail($job_id),
            'message'     => (string) get_post_meta($job_id, '_matrix_db_local_finish_message', true),
            'progress'    => $progress,
        )
    );
}
add_action('wp_ajax_matrix_db_local_job_status', 'matrix_db_local_runner_ajax_status');
