<?php
/**
 * Email notifications — failure alerts and digests.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor;

defined('ABSPATH') || exit;

class Notifier {
    /**
     * Decide whether to alert, remind, or resolve after a stored tier run.
     *
     * @param array<string, mixed> $payload Run result payload.
     */
    public static function after_tier_run(array $payload): void {
        if (! empty($payload['skipped']) && empty($payload['results'])) {
            return;
        }

        $critical = self::critical_failures($payload['results'] ?? []);
        if ($critical === []) {
            self::maybe_send_recovery($payload);
            return;
        }

        self::send_failure_alert($payload);
    }

    /**
     * Send immediate failure alert for critical check failures.
     *
     * Same open issue is not emailed again on every cron run. A new mail goes out
     * when the failing-check set changes, after a 24h reminder, or if it clears.
     *
     * @param array<string, mixed> $payload Run result payload.
     */
    public static function send_failure_alert(array $payload): void {
        $recipients = Settings::alert_recipients();
        if (empty($recipients)) {
            return;
        }

        $failed = self::failed_results($payload['results'] ?? []);
        $critical = self::critical_failures($payload['results'] ?? []);
        if ($critical === []) {
            return;
        }

        $tier      = sanitize_key((string) ($payload['tier'] ?? 'unknown'));
        $ids       = self::failure_ids($critical);
        $state     = self::get_tier_state($tier);
        $decision  = self::alert_decision($ids, $state);

        if ($decision === 'skip') {
            return;
        }

        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $count     = count($failed);
        if ($decision === 'remind') {
            $subject = sprintf('[%s] Site Monitor: %d check(s) still failing', $site_name, $count);
        } else {
            $subject = sprintf('[%s] Site Monitor: %d check(s) failed', $site_name, $count);
        }
        $body = self::build_failure_html($payload, $failed, $decision);

        foreach ($recipients as $email) {
            wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
        }

        self::put_tier_state($tier, [
            'ids'           => $ids,
            'first_alerted' => (int) ($state['first_alerted'] ?? time()),
            'last_alerted'  => time(),
            'alert_count'   => (int) ($state['alert_count'] ?? 0) + 1,
        ]);

        self::maybe_send_webhook($payload);
    }

    /**
     * Send scheduled digest email.
     */
    public static function send_digest(): void {
        $recipients = Settings::digest_recipients();
        if (empty($recipients)) {
            return;
        }

        $light     = Storage::get_tier_result('light');
        $heavy     = Storage::get_tier_result('heavy');
        $synthetic = Storage::get_tier_result('synthetic');

        $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $subject   = sprintf('[%s] Site Monitor Weekly Digest', $site_name);
        $body      = self::build_digest_html($light, $heavy, $synthetic);

        foreach ($recipients as $email) {
            wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
        }
    }

    /**
     * Build failure alert HTML.
     *
     * @param array<string, mixed>            $payload  Full payload.
     * @param array<int, array<string,mixed>> $failed   Failed checks.
     * @param string                          $decision new|update|remind
     */
    private static function build_failure_html(array $payload, array $failed, string $decision = 'new'): string {
        $site_name = esc_html(wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        $site_url  = esc_url(home_url('/'));
        $admin_url = esc_url(admin_url('tools.php?page=matrix-site-monitor'));
        $time      = esc_html((string) ($payload['time'] ?? ''));
        $tier      = esc_html((string) ($payload['tier'] ?? ''));

        $rows = '';
        foreach ($failed as $result) {
            $label    = esc_html((string) ($result['label'] ?? ''));
            $message  = esc_html((string) ($result['message'] ?? ''));
            $severity = esc_html((string) ($result['severity'] ?? 'warning'));
            $rows    .= "<tr><td><strong>{$label}</strong></td><td>{$severity}</td><td>{$message}</td></tr>";
        }

        $note = 'You will not be emailed again for this same issue on every check. Another mail is sent if the failing checks change, if the issue clears, or if it is still failing after 24 hours.';
        if ($decision === 'remind') {
            $note = 'This is a 24-hour reminder. The same issue is still open. You will not get hourly repeats unless something changes or it is still failing after another 24 hours.';
        } elseif ($decision === 'update') {
            $note = 'The set of failing checks changed since the last alert.';
        }

        return <<<HTML
<!DOCTYPE html>
<html><body style="font-family:sans-serif;color:#1e293b;">
<h2 style="color:#b91c1c;">Site Monitor Alert</h2>
<p><strong>Site:</strong> <a href="{$site_url}">{$site_name}</a></p>
<p><strong>Tier:</strong> {$tier} &nbsp; <strong>Time:</strong> {$time}</p>
<table border="1" cellpadding="8" cellspacing="0" style="border-collapse:collapse;width:100%;">
<thead><tr style="background:#f1f5f9;"><th>Check</th><th>Severity</th><th>Detail</th></tr></thead>
<tbody>{$rows}</tbody>
</table>
<p style="color:#475569;font-size:13px;">{$note}</p>
<p><a href="{$admin_url}" style="background:#1d4ed8;color:#fff;padding:10px 16px;text-decoration:none;border-radius:4px;">Open Site Monitor</a></p>
</body></html>
HTML;
    }

    /**
     * Build digest HTML.
     *
     * @param array<string, mixed> $light     Light tier result.
     * @param array<string, mixed> $heavy     Heavy tier result.
     * @param array<string, mixed> $synthetic Synthetic tier result.
     */
    private static function build_digest_html(array $light, array $heavy, array $synthetic): string {
        $site_name = esc_html(wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        $site_url  = esc_url(home_url('/'));

        $sections = [
            'Light (hourly)'     => $light,
            'Heavy (daily)'      => $heavy,
            'WooCommerce'        => $synthetic,
        ];

        $html = "<!DOCTYPE html><html><body style=\"font-family:sans-serif;color:#1e293b;\">";
        $html .= "<h2>Site Monitor Digest</h2>";
        $html .= "<p><strong>Site:</strong> <a href=\"{$site_url}\">{$site_name}</a></p>";

        foreach ($sections as $title => $payload) {
            if (empty($payload['results'])) {
                continue;
            }
            $status = ! empty($payload['ok']) ? '<span style="color:#16a34a;">PASS</span>' : '<span style="color:#b91c1c;">FAIL</span>';
            $time   = esc_html((string) ($payload['time'] ?? 'never'));
            $html  .= "<h3>{$title} — {$status} <small>({$time})</small></h3>";
            $html  .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;margin-bottom:20px;">';
            $html  .= '<thead><tr style="background:#f1f5f9;"><th>Check</th><th>Status</th><th>Detail</th></tr></thead><tbody>';

            foreach ($payload['results'] as $result) {
                $label   = esc_html((string) ($result['label'] ?? ''));
                $message = esc_html((string) ($result['message'] ?? ''));
                $status  = (string) ($result['status'] ?? '');
                if ($status === '' && ! empty($result['skipped'])) {
                    $status = 'skip';
                }
                if ($status === '' ) {
                    $status = ! empty($result['ok']) ? 'pass' : 'fail';
                }

                if ($status === 'skip') {
                    $badge = 'SKIP';
                    $color = '#64748b';
                } elseif ($status === 'pass') {
                    $badge = '✓';
                    $color = '#16a34a';
                } else {
                    $badge = '✗';
                    $color = '#b91c1c';
                }
                $html .= "<tr><td>{$label}</td><td style=\"color:{$color};\">{$badge}</td><td>{$message}</td></tr>";
            }

            $html .= '</tbody></table>';
        }

        $html .= '</body></html>';
        return $html;
    }

    /**
     * POST failure payload to webhook if configured.
     *
     * @param array<string, mixed> $payload Run result.
     */
    private static function maybe_send_webhook(array $payload): void {
        $settings = Settings::get();
        $url      = esc_url_raw((string) ($settings['webhook_url'] ?? ''));

        if ($url === '') {
            return;
        }

        wp_remote_post($url, [
            'timeout' => 10,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode([
                'site'    => home_url('/'),
                'name'    => get_bloginfo('name'),
                'tier'    => $payload['tier'] ?? '',
                'ok'      => $payload['ok'] ?? false,
                'time'    => $payload['time'] ?? '',
                'results' => $payload['results'] ?? [],
            ]),
        ]);
    }

    /**
     * Email once when a previously alerted tier has no critical failures left.
     *
     * @param array<string, mixed> $payload Run result payload.
     */
    private static function maybe_send_recovery(array $payload): void {
        $tier  = sanitize_key((string) ($payload['tier'] ?? 'unknown'));
        $state = self::get_tier_state($tier);
        if ($state === []) {
            return;
        }

        $recipients = Settings::alert_recipients();
        if ($recipients !== []) {
            $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
            $subject   = sprintf('[%s] Site Monitor: issue resolved', $site_name);
            $body      = self::build_recovery_html($payload, $state);

            foreach ($recipients as $email) {
                wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
            }
        }

        self::clear_tier_state($tier);
    }

    /**
     * @param array<string, mixed> $payload Run result.
     * @param array<string, mixed> $state   Previous alert state.
     */
    private static function build_recovery_html(array $payload, array $state): string {
        $site_name = esc_html(wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
        $site_url  = esc_url(home_url('/'));
        $admin_url = esc_url(admin_url('tools.php?page=matrix-site-monitor'));
        $time      = esc_html((string) ($payload['time'] ?? ''));
        $tier      = esc_html((string) ($payload['tier'] ?? ''));
        $ids       = array_map('esc_html', (array) ($state['ids'] ?? []));
        $list      = $ids === [] ? 'previous failing checks' : implode(', ', $ids);

        return <<<HTML
<!DOCTYPE html>
<html><body style="font-family:sans-serif;color:#1e293b;">
<h2 style="color:#16a34a;">Site Monitor Recovered</h2>
<p><strong>Site:</strong> <a href="{$site_url}">{$site_name}</a></p>
<p><strong>Tier:</strong> {$tier} &nbsp; <strong>Time:</strong> {$time}</p>
<p>The previously failing check(s) are clear: <code>{$list}</code>.</p>
<p><a href="{$admin_url}" style="background:#1d4ed8;color:#fff;padding:10px 16px;text-decoration:none;border-radius:4px;">Open Site Monitor</a></p>
</body></html>
HTML;
    }

    /**
     * @param array<int, array<string, mixed>> $results Check results.
     * @return array<int, array<string, mixed>>
     */
    private static function failed_results(array $results): array {
        return array_values(array_filter($results, static function ($r): bool {
            if (! is_array($r)) {
                return false;
            }
            $status = (string) ($r['status'] ?? '');
            if ($status === 'skip' || ! empty($r['skipped'])) {
                return false;
            }
            if ($status === 'fail') {
                return true;
            }
            if ($status === 'pass') {
                return false;
            }
            return empty($r['ok']);
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $results Check results.
     * @return array<int, array<string, mixed>>
     */
    private static function critical_failures(array $results): array {
        return array_values(array_filter(self::failed_results($results), static function (array $r): bool {
            return ($r['severity'] ?? '') === 'critical';
        }));
    }

    /**
     * @param array<int, array<string, mixed>> $failures Failed checks.
     * @return array<int, string>
     */
    private static function failure_ids(array $failures): array {
        $ids = [];
        foreach ($failures as $result) {
            $id = sanitize_key((string) ($result['id'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        return $ids;
    }

    /**
     * @param array<int, string>   $ids   Current critical failure IDs.
     * @param array<string, mixed> $state Stored state for this tier.
     * @return 'new'|'update'|'remind'|'skip'
     */
    private static function alert_decision(array $ids, array $state): string {
        $previous = array_values((array) ($state['ids'] ?? []));
        sort($previous);

        if ($previous === []) {
            return 'new';
        }

        if ($ids !== $previous) {
            return 'update';
        }

        $last     = (int) ($state['last_alerted'] ?? 0);
        $debounce = (int) apply_filters('msm_alert_debounce_seconds', 10 * MINUTE_IN_SECONDS);
        if ($last > 0 && (time() - $last) < max(60, $debounce)) {
            return 'skip';
        }

        $reminder_hours = (int) apply_filters('msm_alert_reminder_hours', 24);
        if ($reminder_hours > 0 && $last > 0 && (time() - $last) >= ($reminder_hours * HOUR_IN_SECONDS)) {
            return 'remind';
        }

        return 'skip';
    }

    /**
     * @return array<string, mixed>
     */
    private static function get_tier_state(string $tier): array {
        $all = get_option(MSM_ALERT_STATE_OPTION, []);
        if (! is_array($all) || $tier === '') {
            return [];
        }
        $state = $all[$tier] ?? [];
        return is_array($state) ? $state : [];
    }

    /**
     * @param array<string, mixed> $state Tier alert state.
     */
    private static function put_tier_state(string $tier, array $state): void {
        if ($tier === '') {
            return;
        }
        $all = get_option(MSM_ALERT_STATE_OPTION, []);
        if (! is_array($all)) {
            $all = [];
        }
        $all[$tier] = $state;
        update_option(MSM_ALERT_STATE_OPTION, $all, false);
    }

    private static function clear_tier_state(string $tier): void {
        $all = get_option(MSM_ALERT_STATE_OPTION, []);
        if (! is_array($all) || $tier === '' || ! isset($all[$tier])) {
            return;
        }
        unset($all[$tier]);
        update_option(MSM_ALERT_STATE_OPTION, $all, false);
    }
}
