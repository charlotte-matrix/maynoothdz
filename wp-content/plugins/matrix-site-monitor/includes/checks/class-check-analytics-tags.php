<?php
/**
 * Analytics checks from docs/Analytics.md — homepage HTML sample + line coverage.
 *
 * @package Matrix_Site_Monitor
 */

namespace Matrix_Site_Monitor\Checks;

use Matrix_Site_Monitor\Check_Base;
use Matrix_Site_Monitor\Settings;


defined('ABSPATH') || exit;

class Check_Analytics_Tags extends Check_Base {
    public function id(): string { return 'analytics_tags'; }
    public function label(): string { return 'Analytics tags & tracking'; }
    public function severity(): string { return 'info'; }
    public function tier(): string { return 'heavy'; }

    public function run(): array {
        $start    = microtime(true);
        $settings = Settings::get();
        $expect   = ! empty($settings['expect_analytics']);

        $res = wp_remote_get(home_url('/'), [
            'timeout'     => 15,
            'redirection' => 3,
            'sslverify'   => false,
            'headers'     => ['User-Agent' => 'matrix-site-monitor/' . MSM_VERSION],
        ]);

        if (is_wp_error($res)) {
            return $this->fail('Could not fetch homepage: ' . $res->get_error_message(), $start, [
                'coverage' => $this->error_coverage($res->get_error_message()),
            ]);
        }

        $code = (int) wp_remote_retrieve_response_code($res);
        if ($code < 200 || $code >= 400) {
            return $this->fail('Homepage returned HTTP ' . $code . '.', $start, [
                'coverage' => $this->error_coverage('HTTP ' . $code),
            ]);
        }

        $body     = (string) wp_remote_retrieve_body($res);
        $detected = $this->detect($body);
        $coverage = $this->build_coverage($detected, $expect);

        // Optional configured ID enforcement (MGPC parity).
        $ga4_ids = Settings::parse_csv_list((string) ($settings['ga4_measurement_ids'] ?? ''));
        $gtm_ids = Settings::parse_csv_list((string) ($settings['gtm_container_ids'] ?? ''));
        $missing_ids = [];
        foreach (array_merge($ga4_ids, $gtm_ids) as $id) {
            if (strpos($body, $id) === false) {
                $missing_ids[] = $id;
            }
        }
        if ($missing_ids) {
            $coverage[] = [
                'line'   => 'Configured GA4/GTM IDs',
                'mode'   => 'automated',
                'status' => 'fail',
                'detail' => 'Missing on homepage: ' . implode(', ', $missing_ids),
            ];
        } elseif ($ga4_ids || $gtm_ids) {
            $coverage[] = [
                'line'   => 'Configured GA4/GTM IDs',
                'mode'   => 'automated',
                'status' => 'pass',
                'detail' => 'Configured measurement/container IDs found',
            ];
        }

        $failed = array_filter($coverage, static function (array $row): bool {
            return ($row['status'] ?? '') === 'fail';
        });

        $extra = ['coverage' => $coverage, 'detected' => $detected];

        if ($failed) {
            $msgs = array_map(static function (array $row): string {
                return $row['line'] . (! empty($row['detail']) ? ' — ' . $row['detail'] : '');
            }, array_values($failed));
            return $this->fail(implode('; ', $msgs) . '.', $start, $extra);
        }

        $found_labels = [];
        foreach ($detected as $key => $on) {
            if ($on) {
                $found_labels[] = $key;
            }
        }

        if ($found_labels) {
            return $this->pass('Detected: ' . implode(', ', $found_labels) . '.', $start, $extra);
        }

        if (! $expect) {
            return $this->skip(
                'No core analytics tags detected. Enable "Expect analytics" to fail when GA4/GTM are missing.',
                $start,
                $extra
            );
        }

        return $this->fail('Expected analytics tags but none found.', $start, $extra);
    }

    /**
     * Every Analytics.md issue line.
     *
     * @return array<int, array{line:string,mode:string,key?:string}>
     */
    public static function doc_lines(): array {
        return [
            ['line' => 'GA4 missing', 'mode' => 'automated', 'key' => 'ga4'],
            ['line' => 'GTM missing', 'mode' => 'automated', 'key' => 'gtm'],
            ['line' => 'Cookie banner blocking analytics incorrectly', 'mode' => 'partial', 'key' => 'cookie_banner'],
            ['line' => 'Facebook Pixel broken', 'mode' => 'automated', 'key' => 'facebook'],
            ['line' => 'LinkedIn Insight missing', 'mode' => 'automated', 'key' => 'linkedin'],
            ['line' => 'Consent Mode issues', 'mode' => 'partial', 'key' => 'consent_mode'],
            ['line' => 'Search Console disconnected', 'mode' => 'partial', 'key' => 'search_console'],
        ];
    }

    /**
     * Detect tracking snippets in HTML.
     *
     * @param string $body HTML body.
     * @return array<string, bool|string>
     */
    private function detect(string $body): array {
        $gtm = (bool) preg_match('/GTM-[A-Z0-9]+/i', $body);
        $ga4 = (bool) (
            preg_match('/\bG-[A-Z0-9]{6,}\b/', $body)
            || preg_match('/gtag\s*\(\s*[\'"]config[\'"]\s*,\s*[\'"]G-/i', $body)
            || preg_match('/googletagmanager\.com\/gtag\/js\?id=G-/i', $body)
        );
        // Avoid counting UA- only as GA4.
        $facebook = (bool) (
            preg_match('/\bfbq\s*\(/i', $body)
            || preg_match('/connect\.facebook\.net\/.+\/fbevents\.js/i', $body)
        );
        $linkedin = (bool) (
            preg_match('/snap\.licdn\.com\/li\.lms-analytics/i', $body)
            || preg_match('/_linkedin_partner_id/i', $body)
            || preg_match('/linkedin\.com\/px/i', $body)
        );
        $consent = (bool) (
            preg_match('/gtag\s*\(\s*[\'"]consent[\'"]/i', $body)
            || preg_match('/ads_data_redaction|ad_storage|analytics_storage/i', $body)
            || preg_match('/TCF|__tcfapi|Cookiebot|cookieyes|complianz|cookie_notice/i', $body)
        );
        $cookie_banner = (bool) (
            preg_match('/cookiebot|cookieyes|complianz|cookielaw|cookie-notice|cookie_banner|onetrust|osano/i', $body)
            || preg_match('/id=["\'](?:cookie|consent)[^"\']*["\']/i', $body)
        );
        $search_console = (bool) (
            preg_match('/name=["\']google-site-verification["\']/i', $body)
            || preg_match('/content=["\'][^"\']+["\']\s+name=["\']google-site-verification["\']/i', $body)
        );

        return [
            'ga4'            => $ga4,
            'gtm'            => $gtm,
            'facebook'       => $facebook,
            'linkedin'       => $linkedin,
            'consent_mode'   => $consent,
            'cookie_banner'  => $cookie_banner,
            'search_console' => $search_console,
        ];
    }

    /**
     * Build line coverage for Analytics.md.
     *
     * @param array<string, bool|string> $detected Detection map.
     * @param bool                       $expect   Expect analytics setting.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function build_coverage(array $detected, bool $expect): array {
        $coverage = [];

        foreach (self::doc_lines() as $row) {
            $key  = $row['key'] ?? '';
            $mode = $row['mode'];
            $line = $row['line'];
            $on   = ! empty($detected[$key]);

            switch ($key) {
                case 'ga4':
                case 'gtm':
                    if ($on) {
                        $status = 'pass';
                        $detail = $key === 'ga4' ? 'GA4 / gtag detected' : 'GTM container detected';
                    } elseif ($expect) {
                        $status = 'fail';
                        $detail = 'Not found on homepage';
                    } else {
                        $status = 'skip';
                        $detail = 'Not found — enable "Expect analytics" to fail';
                    }
                    break;

                case 'facebook':
                    if ($on) {
                        $status = 'pass';
                        $detail = 'Facebook Pixel (fbq) detected';
                    } elseif ($expect) {
                        // Only fail Facebook if expect_analytics AND we want strict — keep as skip unless setting
                        $status = 'skip';
                        $detail = 'Pixel not found (optional unless required for this site)';
                    } else {
                        $status = 'skip';
                        $detail = 'Pixel not found';
                    }
                    break;

                case 'linkedin':
                    if ($on) {
                        $status = 'pass';
                        $detail = 'LinkedIn Insight tag detected';
                    } else {
                        $status = 'skip';
                        $detail = 'Not found (optional for most sites)';
                    }
                    break;

                case 'cookie_banner':
                    if ($on) {
                        $status = 'pass';
                        $detail = 'Cookie/consent banner markup detected — verify it does not block tags before consent incorrectly';
                    } else {
                        $status = 'manual';
                        $detail = 'No common cookie banner detected — verify CMP behaviour manually if you use one';
                    }
                    break;

                case 'consent_mode':
                    if ($on) {
                        $status = 'pass';
                        $detail = 'Consent Mode / CMP signals detected in HTML';
                    } else {
                        $status = 'manual';
                        $detail = 'No Consent Mode markers found — verify gtag consent defaults if using GA4/GTM in EU';
                    }
                    break;

                case 'search_console':
                    if ($on) {
                        $status = 'pass';
                        $detail = 'google-site-verification meta tag present';
                    } else {
                        $status = 'manual';
                        $detail = 'No verification meta tag — property may use DNS/file verify; check Search Console manually';
                    }
                    break;

                default:
                    $status = 'manual';
                    $detail = 'Not classified';
            }

            $coverage[] = [
                'line'   => $line,
                'mode'   => $mode,
                'status' => $status,
                'detail' => $detail,
            ];
        }

        return $coverage;
    }

    /**
     * @param string $reason Error reason.
     * @return array<int, array{line:string,mode:string,status:string,detail:string}>
     */
    private function error_coverage(string $reason): array {
        $rows = [];
        foreach (self::doc_lines() as $row) {
            $rows[] = [
                'line'   => $row['line'],
                'mode'   => $row['mode'],
                'status' => ($row['mode'] === 'manual') ? 'manual' : 'fail',
                'detail' => $reason,
            ];
        }
        return $rows;
    }
}
