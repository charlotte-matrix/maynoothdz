<?php
/**
 * Admin settings and results view.
 *
 * @package Matrix_Site_Monitor
 */

defined('ABSPATH') || exit;

use Matrix_Site_Monitor\Admin\Admin_View;
use Matrix_Site_Monitor\Report;

/** @var array<string, mixed> $settings */
/** @var array<string, mixed> $light */
/** @var array<string, mixed> $heavy */
/** @var array<string, mixed> $synthetic */
/** @var array<int, \Matrix_Site_Monitor\Check_Interface> $catalog */
/** @var array<string, array<string, mixed>> $results_map */

$days            = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
$catalog         = isset($catalog) && is_array($catalog) ? $catalog : [];
$results_map     = isset($results_map) && is_array($results_map) ? $results_map : [];
$disabled_checks = isset($settings['disabled_checks']) && is_array($settings['disabled_checks']) ? $settings['disabled_checks'] : [];
$failing         = Admin_View::failing_checks($catalog, $results_map, $disabled_checks);
$fail_total      = count($failing);

$tab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : '';
if ($tab === '') {
    // Land on Failures when there are any — easiest path for support.
    $tab = $fail_total > 0 ? 'failures' : 'overview';
}
if (! in_array($tab, ['failures', 'overview', 'results', 'settings'], true)) {
    $tab = 'overview';
}

$ordered   = Admin_View::checks_by_category($catalog);
$light_sum = Admin_View::tier_summary(is_array($light ?? null) ? $light : []);
$heavy_sum = Admin_View::tier_summary(is_array($heavy ?? null) ? $heavy : []);
$synth_sum = Admin_View::tier_summary(is_array($synthetic ?? null) ? $synthetic : []);
$pass_total = 0;
foreach ($results_map as $row) {
    if (is_array($row) && Admin_View::status_key($row) === 'pass') {
        $pass_total++;
    }
}

$base_url     = admin_url('tools.php?page=matrix-site-monitor');
$report_url   = Report::download_url();
$has_results  = ! empty($results_map);
?>
<div class="wrap msm-wrap">
    <div class="msm-header">
        <div class="msm-header__row">
            <div>
                <h1><?php esc_html_e('Matrix Site Monitor', 'matrix-site-monitor'); ?></h1>
                <p class="msm-header-meta"><?php
                    $resolved = \Matrix_Site_Monitor\Settings::qa_profile();
                    $stored   = (string) (($settings['qa_profile'] ?? 'auto') ?: 'auto');
                    echo esc_html(sprintf(
                        /* translators: 1: resolved profile (live|development), 2: stored setting */
                        __('CLI is the primary runner — this screen is for overview and settings. QA profile: %1$s (setting: %2$s).', 'matrix-site-monitor'),
                        $resolved,
                        $stored
                    ));
                ?></p>
            </div>
            <p class="msm-header__actions">
                <a class="button button-secondary<?php echo $has_results ? '' : ' disabled'; ?>" href="<?php echo $has_results ? esc_url($report_url) : '#'; ?>" <?php echo $has_results ? '' : ' aria-disabled="true" onclick="return false;"'; ?> title="<?php echo $has_results ? esc_attr__('Download HTML triage report', 'matrix-site-monitor') : esc_attr__('Run checks first to generate a report', 'matrix-site-monitor'); ?>">
                    <?php esc_html_e('Download report', 'matrix-site-monitor'); ?>
                </a>
            </p>
        </div>
    </div>

    <?php if (! empty($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'matrix-site-monitor'); ?></p></div>
    <?php endif; ?>

    <?php if (! empty($_GET['ran'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php
            if (sanitize_key((string) $_GET['ran']) === 'single' && ! empty($_GET['check'])) {
                echo esc_html(sprintf(__('Ran check: %s', 'matrix-site-monitor'), sanitize_key((string) $_GET['check'])));
            } else {
                echo esc_html(sprintf(__('Ran %s tier.', 'matrix-site-monitor'), sanitize_key((string) $_GET['ran'])));
            }
        ?></p></div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper msm-tabs" aria-label="<?php esc_attr_e('Site Monitor sections', 'matrix-site-monitor'); ?>">
        <a href="<?php echo esc_url($base_url . '&tab=failures'); ?>" class="nav-tab <?php echo $tab === 'failures' ? 'nav-tab-active' : ''; ?><?php echo $fail_total ? ' msm-tab-fail' : ''; ?>" data-tab="failures">
            <?php esc_html_e('Failures', 'matrix-site-monitor'); ?>
            <?php if ($fail_total) : ?>
                <span class="msm-tab-count"><?php echo (int) $fail_total; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo esc_url($base_url . '&tab=overview'); ?>" class="nav-tab <?php echo $tab === 'overview' ? 'nav-tab-active' : ''; ?>" data-tab="overview"><?php esc_html_e('Overview', 'matrix-site-monitor'); ?></a>
        <a href="<?php echo esc_url($base_url . '&tab=results'); ?>" class="nav-tab <?php echo $tab === 'results' ? 'nav-tab-active' : ''; ?>" data-tab="results"><?php esc_html_e('All results', 'matrix-site-monitor'); ?></a>
        <a href="<?php echo esc_url($base_url . '&tab=settings'); ?>" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>" data-tab="settings"><?php esc_html_e('Settings', 'matrix-site-monitor'); ?></a>
    </nav>

    <!-- Failures (primary) -->
    <div class="msm-panel <?php echo $tab === 'failures' ? 'is-active' : ''; ?>" data-panel="failures">
        <?php if ($fail_total === 0) : ?>
            <div class="msm-fail-empty">
                <p class="msm-fail-empty__title"><?php esc_html_e('No failing checks', 'matrix-site-monitor'); ?></p>
                <p class="description"><?php esc_html_e('Everything that has been run is currently passing, skipped, or not yet run. Open Overview to run checks, or All results for the full list.', 'matrix-site-monitor'); ?></p>
            </div>
        <?php else : ?>
            <div class="msm-fail-banner">
                <strong><?php echo esc_html(sprintf(_n('%d failing check', '%d failing checks', $fail_total, 'matrix-site-monitor'), $fail_total)); ?></strong>
                <span class="description"><?php esc_html_e('Critical first. Expand a row for failed coverage lines.', 'matrix-site-monitor'); ?></span>
                <a class="button button-small" href="<?php echo esc_url($report_url); ?>"><?php esc_html_e('Download report', 'matrix-site-monitor'); ?></a>
            </div>
            <table class="msm-table msm-fail-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Check', 'matrix-site-monitor'); ?></th>
                        <th><?php esc_html_e('Severity', 'matrix-site-monitor'); ?></th>
                        <th><?php esc_html_e('What failed', 'matrix-site-monitor'); ?></th>
                        <th><?php esc_html_e('When', 'matrix-site-monitor'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($failing as $i => $fail) :
                    $detail_id = 'msm-fail-detail-' . (int) $i;
                    ?>
                    <tr class="is-fail">
                        <td>
                            <span class="msm-check-name"><?php echo esc_html($fail['label']); ?></span>
                            <span class="msm-check-meta"><?php echo esc_html($fail['category']); ?> · <code><?php echo esc_html($fail['id']); ?></code></span>
                        </td>
                        <td>
                            <?php
                            $sev = $fail['severity'];
                            if ($sev === 'critical') {
                                echo '<span class="msm-badge msm-badge--fail">CRITICAL</span>';
                            } elseif ($sev === 'warning') {
                                echo '<span class="msm-badge msm-badge--warn">WARNING</span>';
                            } else {
                                echo '<span class="msm-badge msm-badge--muted">INFO</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="msm-msg"><?php echo esc_html($fail['message']); ?></div>
                            <?php if (! empty($fail['coverage_fails'])) : ?>
                                <button type="button" class="button-link msm-coverage-toggle" aria-controls="<?php echo esc_attr($detail_id); ?>" aria-expanded="false" data-label-show="<?php echo esc_attr(sprintf(__('Show %d failed lines', 'matrix-site-monitor'), count($fail['coverage_fails']))); ?>" data-label-hide="<?php esc_attr_e('Hide lines', 'matrix-site-monitor'); ?>">
                                    <?php echo esc_html(sprintf(__('Show %d failed lines', 'matrix-site-monitor'), count($fail['coverage_fails']))); ?>
                                </button>
                                <div class="msm-coverage" id="<?php echo esc_attr($detail_id); ?>">
                                    <ul class="msm-fail-lines">
                                        <?php foreach ($fail['coverage_fails'] as $line) : ?>
                                            <li>
                                                <strong><?php echo esc_html($line['line']); ?></strong>
                                                <?php if ($line['detail'] !== '') : ?>
                                                    — <?php echo esc_html($line['detail']); ?>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($fail['time'] !== '' ? $fail['time'] : '—'); ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('msm_run_checks'); ?>
                                <input type="hidden" name="action" value="msm_run_checks">
                                <input type="hidden" name="tier" value="single">
                                <input type="hidden" name="check_id" value="<?php echo esc_attr($fail['id']); ?>">
                                <button type="submit" class="button button-small"><?php esc_html_e('Re-run', 'matrix-site-monitor'); ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Overview -->
    <div class="msm-panel <?php echo $tab === 'overview' ? 'is-active' : ''; ?>" data-panel="overview">
        <?php if ($fail_total > 0) : ?>
            <div class="notice notice-error inline msm-fail-notice">
                <p>
                    <strong><?php echo esc_html(sprintf(_n('%d check is failing.', '%d checks are failing.', $fail_total, 'matrix-site-monitor'), $fail_total)); ?></strong>
                    <a href="<?php echo esc_url($base_url . '&tab=failures'); ?>" class="msm-js-tab" data-tab="failures"><?php esc_html_e('View failures', 'matrix-site-monitor'); ?></a>
                </p>
            </div>
        <?php endif; ?>
        <div class="msm-cards">
            <?php
            $tier_cards = [
                'light'     => [$light_sum, __('Light', 'matrix-site-monitor')],
                'heavy'     => [$heavy_sum, __('Heavy', 'matrix-site-monitor')],
                'synthetic' => [$synth_sum, __('WooCommerce', 'matrix-site-monitor')],
            ];
            foreach ($tier_cards as $key => $pair) :
                if ($key === 'synthetic' && ! class_exists('WooCommerce')) {
                    continue;
                }
                /** @var array{ok:?bool,time:string,fail:int,total:int} $sum */
                $sum   = $pair[0];
                $label = $pair[1];
                $mod   = $sum['ok'] === null ? 'empty' : ($sum['ok'] ? 'ok' : 'fail');
                $value = $sum['ok'] === null ? __('No run', 'matrix-site-monitor') : ($sum['ok'] ? __('OK', 'matrix-site-monitor') : __('Issues', 'matrix-site-monitor'));
                ?>
                <div class="msm-card msm-card--<?php echo esc_attr($mod); ?>">
                    <p class="msm-card__label"><?php echo esc_html($label); ?></p>
                    <p class="msm-card__value"><?php echo esc_html($value); ?></p>
                    <p class="msm-card__meta">
                        <?php
                        if ($sum['total'] > 0) {
                            echo esc_html(sprintf(
                                /* translators: 1: fails, 2: total, 3: time */
                                __('%1$d fail / %2$d checks · %3$s', 'matrix-site-monitor'),
                                $sum['fail'],
                                $sum['total'],
                                $sum['time'] !== '' ? $sum['time'] : '—'
                            ));
                        } else {
                            esc_html_e('Not run yet', 'matrix-site-monitor');
                        }
                        ?>
                    </p>
                </div>
            <?php endforeach; ?>
            <div class="msm-card <?php echo $fail_total ? 'msm-card--fail' : 'msm-card--ok'; ?> msm-card--clickable" data-tab-link="failures" role="link" tabindex="0">
                <p class="msm-card__label"><?php esc_html_e('Failures', 'matrix-site-monitor'); ?></p>
                <p class="msm-card__value"><?php echo esc_html((string) $fail_total); ?></p>
                <p class="msm-card__meta"><?php echo $fail_total ? esc_html__('Click to review', 'matrix-site-monitor') : esc_html__('All clear', 'matrix-site-monitor'); ?></p>
            </div>
        </div>

        <details class="msm-cli">
            <summary><?php esc_html_e('WP-CLI commands (recommended)', 'matrix-site-monitor'); ?></summary>
            <div class="msm-cli__body">
                <p class="description" style="margin-top:0;"><?php esc_html_e('Prefer CLI for heavy / all / preflight to avoid PHP timeouts.', 'matrix-site-monitor'); ?></p>
                <pre>wp msm run --tier=light
wp msm run --tier=heavy
wp msm run --tier=all
wp msm preflight --coverage
wp msm preflight --profile=live
wp msm run --check=golive --profile=live --coverage
wp msm run --check=banned_plugins
wp msm list</pre>
            </div>
        </details>

        <h2 class="title"><?php esc_html_e('Run from admin', 'matrix-site-monitor'); ?></h2>
        <div class="msm-toolbar">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('msm_run_checks'); ?>
                <input type="hidden" name="action" value="msm_run_checks">
                <input type="hidden" name="tier" value="light">
                <input type="hidden" name="redirect_tab" value="overview">
                <button type="submit" class="button button-primary"><?php esc_html_e('Run light', 'matrix-site-monitor'); ?></button>
            </form>
            <?php if (class_exists('WooCommerce')) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('msm_run_checks'); ?>
                <input type="hidden" name="action" value="msm_run_checks">
                <input type="hidden" name="tier" value="synthetic">
                <button type="submit" class="button"><?php esc_html_e('Run WooCommerce', 'matrix-site-monitor'); ?></button>
            </form>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Heavy suite can time out in the browser. Prefer: wp msm run --tier=heavy. Continue?', 'matrix-site-monitor')); ?>');">
                <?php wp_nonce_field('msm_run_checks'); ?>
                <input type="hidden" name="action" value="msm_run_checks">
                <input type="hidden" name="tier" value="heavy">
                <button type="submit" class="button"><?php esc_html_e('Heavy…', 'matrix-site-monitor'); ?></button>
            </form>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="msm-toolbar">
            <?php wp_nonce_field('msm_run_checks'); ?>
            <input type="hidden" name="action" value="msm_run_checks">
            <input type="hidden" name="tier" value="single">
            <label for="msm-check-id" class="screen-reader-text"><?php esc_html_e('Check', 'matrix-site-monitor'); ?></label>
            <select id="msm-check-id" name="check_id">
                <?php foreach ($catalog as $check) : ?>
                    <option value="<?php echo esc_attr($check->id()); ?>">
                        <?php echo esc_html($check->label() . ' — ' . $check->id()); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button"><?php esc_html_e('Run one check', 'matrix-site-monitor'); ?></button>
        </form>
    </div>

    <!-- Results -->
    <div class="msm-panel <?php echo $tab === 'results' ? 'is-active' : ''; ?>" data-panel="results">
        <div class="msm-filter">
            <label for="msm-results-filter" class="screen-reader-text"><?php esc_html_e('Filter checks', 'matrix-site-monitor'); ?></label>
            <input type="search" id="msm-results-filter" placeholder="<?php esc_attr_e('Filter by name or ID…', 'matrix-site-monitor'); ?>">
            <label>
                <input type="checkbox" id="msm-fail-only">
                <?php esc_html_e('Failures only', 'matrix-site-monitor'); ?>
            </label>
            <?php if ($has_results) : ?>
                <a class="button button-small" href="<?php echo esc_url($report_url); ?>"><?php esc_html_e('Download report', 'matrix-site-monitor'); ?></a>
            <?php endif; ?>
            <span class="description"><?php esc_html_e('Categories collapse by default. Expand a section, then open coverage details when needed.', 'matrix-site-monitor'); ?></span>
        </div>

        <?php foreach ($ordered as $category => $checks) :
            $counts   = Admin_View::category_counts($checks, $results_map, $disabled_checks);
            $open_fail = $counts['fail'] > 0;
            ?>
            <section class="msm-section <?php echo $open_fail ? 'is-open' : ''; ?>">
                <button type="button" class="msm-section__head" aria-expanded="<?php echo $open_fail ? 'true' : 'false'; ?>">
                    <span class="msm-section__title">
                        <span class="msm-section__chevron" aria-hidden="true">▸</span>
                        <?php echo esc_html($category); ?>
                        <span class="description">(<?php echo esc_html((string) count($checks)); ?>)</span>
                    </span>
                    <span class="msm-section__counts">
                        <?php if ($counts['fail']) : ?><?php echo Admin_View::badge('fail'); ?> <?php echo (int) $counts['fail']; ?><?php endif; ?>
                        <?php if ($counts['warn']) : ?><?php echo Admin_View::badge('warn'); ?> <?php echo (int) $counts['warn']; ?><?php endif; ?>
                        <?php if ($counts['pass']) : ?><?php echo Admin_View::badge('pass'); ?> <?php echo (int) $counts['pass']; ?><?php endif; ?>
                    </span>
                </button>
                <div class="msm-section__body">
                    <table class="msm-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Check', 'matrix-site-monitor'); ?></th>
                                <th><?php esc_html_e('Status', 'matrix-site-monitor'); ?></th>
                                <th><?php esc_html_e('Detail', 'matrix-site-monitor'); ?></th>
                                <th><?php esc_html_e('When', 'matrix-site-monitor'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($checks as $check) :
                            $id       = $check->id();
                            $disabled = in_array($id, $disabled_checks, true);
                            $result   = $results_map[ $id ] ?? [];
                            if ($disabled) {
                                $row_status = 'disabled';
                                $message    = __('Disabled in settings.', 'matrix-site-monitor');
                                $last_run   = '—';
                            } elseif (empty($result)) {
                                $row_status = 'not_run';
                                $message    = __('Not run yet.', 'matrix-site-monitor');
                                $last_run   = '—';
                            } else {
                                $row_status = Admin_View::status_key($result);
                                $message    = (string) ($result['message'] ?? '');
                                $last_run   = (string) ($result['time'] ?? '—');
                                if (isset($result['duration'])) {
                                    $last_run .= ' · ' . $result['duration'] . 's';
                                }
                            }
                            $coverage = Admin_View::coverage_rows($id, is_array($result) ? $result : []);
                            $cov_id   = 'msm-cov-' . $id;
                            $search   = strtolower($check->label() . ' ' . $id . ' ' . $category);
                            ?>
                            <tr class="<?php echo $row_status === 'fail' ? 'is-fail' : ''; ?>" data-check="<?php echo esc_attr($id); ?>" data-search="<?php echo esc_attr($search); ?>">
                                <td>
                                    <span class="msm-check-name"><?php echo esc_html($check->label()); ?></span>
                                    <span class="msm-check-meta"><code><?php echo esc_html($id); ?></code> · <?php echo esc_html($check->tier()); ?> · <?php echo esc_html($check->severity()); ?></span>
                                </td>
                                <td><?php echo Admin_View::badge($row_status); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
                                <td>
                                    <div class="msm-msg"><?php echo esc_html($message); ?></div>
                                    <?php if ($coverage) : ?>
                                        <button type="button" class="button-link msm-coverage-toggle" aria-controls="<?php echo esc_attr($cov_id); ?>" aria-expanded="false" data-label-show="<?php echo esc_attr(sprintf(__('Show %d lines', 'matrix-site-monitor'), count($coverage))); ?>" data-label-hide="<?php esc_attr_e('Hide lines', 'matrix-site-monitor'); ?>">
                                            <?php echo esc_html(sprintf(__('Show %d lines', 'matrix-site-monitor'), count($coverage))); ?>
                                        </button>
                                        <div class="msm-coverage" id="<?php echo esc_attr($cov_id); ?>">
                                            <?php foreach ($coverage as $cov) : ?>
                                                <div class="msm-coverage-row">
                                                    <div class="msm-coverage-line"><?php echo esc_html($cov['line']); ?><?php if ($cov['mode'] !== '') : ?> <span class="description">(<?php echo esc_html($cov['mode']); ?>)</span><?php endif; ?></div>
                                                    <div><?php echo Admin_View::badge($cov['status']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                                    <div class="msm-coverage-detail"><?php echo esc_html($cov['detail']); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($last_run); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <!-- Settings -->
    <div class="msm-panel <?php echo $tab === 'settings' ? 'is-active' : ''; ?>" data-panel="settings">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="msm-settings-grid">
            <?php wp_nonce_field('msm_save_settings'); ?>
            <input type="hidden" name="action" value="msm_save_settings">

            <div class="msm-settings-block">
                <h2><?php esc_html_e('QA profile', 'matrix-site-monitor'); ?></h2>
                <p class="description"><?php esc_html_e('Development skips live-only rules (public debug, file editor, XML-RPC, Redis/WP Rocket). Live applies those rules — use this for go-live even on Local. Auto follows WP_ENVIRONMENT_TYPE.', 'matrix-site-monitor'); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="qa_profile"><?php esc_html_e('Profile', 'matrix-site-monitor'); ?></label></th>
                        <td>
                            <?php $qa_profile = (string) ($settings['qa_profile'] ?? 'auto'); ?>
                            <select name="qa_profile" id="qa_profile">
                                <option value="auto" <?php selected($qa_profile, 'auto'); ?>><?php esc_html_e('Auto (follow WordPress environment)', 'matrix-site-monitor'); ?></option>
                                <option value="development" <?php selected($qa_profile, 'development'); ?>><?php esc_html_e('Development', 'matrix-site-monitor'); ?></option>
                                <option value="live" <?php selected($qa_profile, 'live'); ?>><?php esc_html_e('Live / go-live', 'matrix-site-monitor'); ?></option>
                            </select>
                            <p class="description"><?php echo esc_html(sprintf(
                                /* translators: %s: live or development */
                                __('Currently applying: %s. Override one run with: wp msm preflight --profile=live', 'matrix-site-monitor'),
                                \Matrix_Site_Monitor\Settings::qa_profile()
                            )); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="msm-settings-block">
                <h2><?php esc_html_e('Enabled checks', 'matrix-site-monitor'); ?></h2>
                <p class="description"><?php esc_html_e('Untick checks to skip on cron and CLI (unless you run a single check by ID).', 'matrix-site-monitor'); ?></p>
                <p>
                    <button type="button" class="button" id="msm-select-all-checks"><?php esc_html_e('Select all', 'matrix-site-monitor'); ?></button>
                    <button type="button" class="button" id="msm-deselect-all-checks"><?php esc_html_e('Deselect all', 'matrix-site-monitor'); ?></button>
                </p>
                <fieldset id="msm-enabled-checks" class="msm-enabled-list">
                    <?php
                    $by_tier = ['light' => [], 'heavy' => [], 'synthetic' => []];
                    foreach ($catalog as $check) {
                        $by_tier[ $check->tier() ][] = $check;
                    }
                    foreach ($by_tier as $tier_label => $checks) :
                        if (empty($checks)) {
                            continue;
                        }
                        echo '<p><strong>' . esc_html(ucfirst($tier_label)) . '</strong></p>';
                        foreach ($checks as $check) :
                            $checked = ! in_array($check->id(), $disabled_checks, true);
                            ?>
                            <label>
                                <input type="checkbox" class="msm-check-toggle" name="enabled_checks[]" value="<?php echo esc_attr($check->id()); ?>" <?php checked($checked); ?>>
                                <?php echo esc_html($check->label()); ?>
                                <code><?php echo esc_html($check->id()); ?></code>
                            </label>
                            <?php
                        endforeach;
                    endforeach;
                    ?>
                </fieldset>
            </div>

            <div class="msm-settings-block">
                <h2><?php esc_html_e('Alerts & schedule', 'matrix-site-monitor'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="alert_emails"><?php esc_html_e('Alert emails', 'matrix-site-monitor'); ?></label></th>
                        <td>
                            <input type="text" class="large-text" id="alert_emails" name="alert_emails" value="<?php echo esc_attr((string) $settings['alert_emails']); ?>">
                            <p class="description"><?php esc_html_e('Comma-separated. Emails as soon as a critical check fails. The same open issue is not re-sent on every hourly run — only if it changes, clears, or is still failing after 24 hours.', 'matrix-site-monitor'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="digest_emails"><?php esc_html_e('Digest emails', 'matrix-site-monitor'); ?></label></th>
                        <td>
                            <input type="text" class="large-text" id="digest_emails" name="digest_emails" value="<?php echo esc_attr((string) $settings['digest_emails']); ?>">
                            <label><input type="checkbox" name="digest_enabled" value="1" <?php checked(! empty($settings['digest_enabled'])); ?>> <?php esc_html_e('Enable weekly digest', 'matrix-site-monitor'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Digest schedule', 'matrix-site-monitor'); ?></th>
                        <td>
                            <select name="digest_day">
                                <?php foreach ($days as $day) : ?>
                                    <option value="<?php echo esc_attr($day); ?>" <?php selected($settings['digest_day'] ?? 'monday', $day); ?>><?php echo esc_html(ucfirst($day)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="time" name="digest_time" value="<?php echo esc_attr((string) ($settings['digest_time'] ?? '09:00')); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="heavy_schedule_time"><?php esc_html_e('Heavy checks time', 'matrix-site-monitor'); ?></label></th>
                        <td><input type="time" id="heavy_schedule_time" name="heavy_schedule_time" value="<?php echo esc_attr((string) ($settings['heavy_schedule_time'] ?? '03:00')); ?>"></td>
                    </tr>
                </table>
            </div>

            <div class="msm-settings-block">
                <h2><?php esc_html_e('Smoke & thresholds', 'matrix-site-monitor'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="smoke_urls"><?php esc_html_e('Extra smoke URLs', 'matrix-site-monitor'); ?></label></th>
                        <td>
                            <textarea class="large-text" rows="3" id="smoke_urls" name="smoke_urls"><?php echo esc_textarea((string) $settings['smoke_urls']); ?></textarea>
                            <p>
                                <label><?php esc_html_e('Page sample', 'matrix-site-monitor'); ?>
                                    <input type="number" name="smoke_page_sample" value="<?php echo esc_attr((string) ($settings['smoke_page_sample'] ?? 8)); ?>" min="0" max="50" style="width:5em;">
                                </label>
                                <label><?php esc_html_e('Post sample', 'matrix-site-monitor'); ?>
                                    <input type="number" name="smoke_post_sample" value="<?php echo esc_attr((string) ($settings['smoke_post_sample'] ?? 3)); ?>" min="0" max="20" style="width:5em;">
                                </label>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Disk alert', 'matrix-site-monitor'); ?></th>
                        <td>
                            <select name="disk_threshold_mode">
                                <option value="percent" <?php selected($settings['disk_threshold_mode'] ?? 'percent', 'percent'); ?>><?php esc_html_e('Percent free', 'matrix-site-monitor'); ?></option>
                                <option value="gb" <?php selected($settings['disk_threshold_mode'] ?? 'percent', 'gb'); ?>><?php esc_html_e('GB free', 'matrix-site-monitor'); ?></option>
                            </select>
                            <input type="number" name="disk_threshold_percent" value="<?php echo esc_attr((string) ($settings['disk_threshold_percent'] ?? 15)); ?>" min="1" max="95"> %
                            <input type="number" name="disk_threshold_gb" value="<?php echo esc_attr((string) ($settings['disk_threshold_gb'] ?? 5)); ?>" min="0.5" step="0.5"> GB
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ssl_warn_days"><?php esc_html_e('SSL warn days', 'matrix-site-monitor'); ?></label></th>
                        <td><input type="number" id="ssl_warn_days" name="ssl_warn_days" value="<?php echo esc_attr((string) ($settings['ssl_warn_days'] ?? 14)); ?>" min="1"></td>
                    </tr>
                </table>
            </div>

            <div class="msm-settings-block">
                <h2><?php esc_html_e('Preflight', 'matrix-site-monitor'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="critical_paths"><?php esc_html_e('Critical paths', 'matrix-site-monitor'); ?></label></th>
                        <td><textarea class="large-text" rows="3" id="critical_paths" name="critical_paths"><?php echo esc_textarea((string) ($settings['critical_paths'] ?? '')); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="contact_path"><?php esc_html_e('Contact path', 'matrix-site-monitor'); ?></label></th>
                        <td><input type="text" class="regular-text" id="contact_path" name="contact_path" value="<?php echo esc_attr((string) ($settings['contact_path'] ?? '/contact/')); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Limits', 'matrix-site-monitor'); ?></th>
                        <td>
                            <label><?php esc_html_e('Response ms', 'matrix-site-monitor'); ?>
                                <input type="number" name="response_time_ms" value="<?php echo esc_attr((string) ($settings['response_time_ms'] ?? 1500)); ?>" min="200" max="30000" style="width:6em;">
                            </label>
                            <label><?php esc_html_e('Max links', 'matrix-site-monitor'); ?>
                                <input type="number" name="max_link_checks" value="<?php echo esc_attr((string) ($settings['max_link_checks'] ?? 60)); ?>" min="1" max="200" style="width:5em;">
                            </label>
                            <label><?php esc_html_e('Timeout s', 'matrix-site-monitor'); ?>
                                <input type="number" name="request_timeout" value="<?php echo esc_attr((string) ($settings['request_timeout'] ?? 10)); ?>" min="3" max="60" style="width:5em;">
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sitemap_paths"><?php esc_html_e('Sitemap paths', 'matrix-site-monitor'); ?></label></th>
                        <td><textarea class="large-text" rows="2" id="sitemap_paths" name="sitemap_paths"><?php echo esc_textarea((string) ($settings['sitemap_paths'] ?? '')); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="redirect_rules"><?php esc_html_e('Redirect rules', 'matrix-site-monitor'); ?></label></th>
                        <td>
                            <textarea class="large-text" rows="3" id="redirect_rules" name="redirect_rules"><?php echo esc_textarea((string) ($settings['redirect_rules'] ?? '')); ?></textarea>
                            <p class="description"><?php esc_html_e('One per line: /old => /new', 'matrix-site-monitor'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Analytics IDs', 'matrix-site-monitor'); ?></th>
                        <td>
                            <input type="text" class="regular-text" name="ga4_measurement_ids" value="<?php echo esc_attr((string) ($settings['ga4_measurement_ids'] ?? '')); ?>" placeholder="G-XXXXXXXX">
                            <input type="text" class="regular-text" name="gtm_container_ids" value="<?php echo esc_attr((string) ($settings['gtm_container_ids'] ?? '')); ?>" placeholder="GTM-XXXXXXX">
                            <p>
                                <label>
                                    <input type="checkbox" name="expect_analytics" value="1" <?php checked(! empty($settings['expect_analytics'])); ?>>
                                    <?php esc_html_e('Fail if GA4/GTM missing', 'matrix-site-monitor'); ?>
                                </label>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="msm-settings-block">
                <h2><?php esc_html_e('Security & integrations', 'matrix-site-monitor'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="banned_plugins"><?php esc_html_e('Banned plugins', 'matrix-site-monitor'); ?></label></th>
                        <td>
                            <textarea class="large-text" rows="3" id="banned_plugins" name="banned_plugins"><?php echo esc_textarea((string) ($settings['banned_plugins'] ?? '')); ?></textarea>
                            <p class="description"><?php esc_html_e('Extra denylist (File Manager family always banned). One slug per line.', 'matrix-site-monitor'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wpscan_api_token"><?php esc_html_e('WPScan API token', 'matrix-site-monitor'); ?></label></th>
                        <td><input type="password" class="regular-text" id="wpscan_api_token" name="wpscan_api_token" value="<?php echo esc_attr((string) ($settings['wpscan_api_token'] ?? '')); ?>" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('REST API', 'matrix-site-monitor'); ?></th>
                        <td>
                            <label><input type="checkbox" name="enable_rest" value="1" <?php checked(! empty($settings['enable_rest'])); ?>> <?php esc_html_e('Enable status endpoint', 'matrix-site-monitor'); ?></label><br>
                            <input type="text" class="regular-text" name="api_token" value="<?php echo esc_attr((string) ($settings['api_token'] ?? '')); ?>" readonly>
                            <?php if (! empty($settings['enable_rest']) && ! empty($settings['api_token'])) : ?>
                                <p><code><?php echo esc_html(rest_url('matrix-site-monitor/v1/status')); ?></code></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="webhook_url"><?php esc_html_e('Failure webhook', 'matrix-site-monitor'); ?></label></th>
                        <td><input type="url" class="large-text" id="webhook_url" name="webhook_url" value="<?php echo esc_attr((string) ($settings['webhook_url'] ?? '')); ?>"></td>
                    </tr>
                </table>
            </div>

            <?php if (class_exists('WooCommerce')) : ?>
            <div class="msm-settings-block">
                <h2><?php esc_html_e('WooCommerce', 'matrix-site-monitor'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Synthetic checks', 'matrix-site-monitor'); ?></th>
                        <td><label><input type="checkbox" name="wc_enabled" value="1" <?php checked(! empty($settings['wc_enabled'])); ?>> <?php esc_html_e('Enable', 'matrix-site-monitor'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="synthetic_interval"><?php esc_html_e('Interval', 'matrix-site-monitor'); ?></label></th>
                        <td>
                            <select id="synthetic_interval" name="synthetic_interval">
                                <option value="hourly" <?php selected($settings['synthetic_interval'] ?? 'hourly', 'hourly'); ?>><?php esc_html_e('Hourly', 'matrix-site-monitor'); ?></option>
                                <option value="four_hours" <?php selected($settings['synthetic_interval'] ?? 'hourly', 'four_hours'); ?>><?php esc_html_e('Every 4 hours', 'matrix-site-monitor'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wc_coupon_code"><?php esc_html_e('Test coupon', 'matrix-site-monitor'); ?></label></th>
                        <td><input type="text" id="wc_coupon_code" name="wc_coupon_code" value="<?php echo esc_attr((string) ($settings['wc_coupon_code'] ?? '')); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Product IDs', 'matrix-site-monitor'); ?></th>
                        <td>
                            <label><?php esc_html_e('Simple', 'matrix-site-monitor'); ?> <input type="number" name="wc_simple_product_id" value="<?php echo esc_attr((string) ($settings['wc_simple_product_id'] ?? 0)); ?>" min="0"></label>
                            <label><?php esc_html_e('Variation', 'matrix-site-monitor'); ?> <input type="number" name="wc_variation_id" value="<?php echo esc_attr((string) ($settings['wc_variation_id'] ?? 0)); ?>" min="0"></label>
                            <p class="description"><?php esc_html_e('0 = auto-discover', 'matrix-site-monitor'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Skip if light fails', 'matrix-site-monitor'); ?></th>
                        <td><label><input type="checkbox" name="wc_skip_if_light_failed" value="1" <?php checked(! empty($settings['wc_skip_if_light_failed'])); ?>> <?php esc_html_e('Enabled', 'matrix-site-monitor'); ?></label></td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>

            <?php submit_button(__('Save settings', 'matrix-site-monitor')); ?>
        </form>
    </div>
</div>
