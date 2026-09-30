<?php

namespace MapSVG;

/**
 * Register WordPress actions, filters, and custom cron callbacks for MapSVG.
 *
 * Intended for lifecycle/bootstrap hooks kept separate from {@see Router}.
 * Call {@see PluginHooks::boot()} from the main plugin bootstrap after DB upgrade.
 */
final class PluginHooks
{
    /** @var self|null */
    private static $instance;

    /** @var bool */
    private $hooksRegistered = false;

    private function __construct() {}

    /**
     * Singleton instance (useful if something needs the same object reference WP holds).
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register hooks exactly once even if called multiple times.
     *
     * @return void
     */
    public static function boot(): void
    {
        self::instance()->registerHooks();
    }

    /**
     * @return void
     */
    private function registerHooks(): void
    {
        if ($this->hooksRegistered) {
            return;
        }
        $this->hooksRegistered = true;

        // This should be called before "rest_api_init"
        add_action('init', array($this, 'setupGutenberg'));
        add_action('init', array($this, 'setupElementor'));
        add_action('init', array($this, 'addShortcodePostType'));

        // Add query vars for custom endpoints
        add_filter('query_vars', array($this, 'addCustomQueryVars'));

        // WordPress cookie authentication creates rest_cookie_invalid_nonce at priority 100,
        // so this must run afterwards to downgrade only public MapSVG GETs to anonymous reads.
        add_filter('rest_authentication_errors', array(RestAuth::class, 'allowStaleNonceOnPublicGet'), 110);

        // Background geocoding cron — must be registered on every request so WP Cron can fire it.
        add_action('mapsvg_geocode_batch', '\MapSVG\GeocodingQueue::process');

        // Background CSV import cron — fallback for when the browser tab is closed mid-import.
        add_action('mapsvg_csv_process_batch', '\MapSVG\ObjectsController::importCsvCron');

        // Google Sheets auto-sync — recurring intervals are registered dynamically per hours (1–168).
        add_filter('cron_schedules', '\MapSVG\GoogleSheetSync::registerCronSchedules');
        // Google Sheets auto-sync cron — fires on the interval set per schema.
        add_action('mapsvg_gs_sync', '\MapSVG\GoogleSheetSync::process');

        // Stale import lock repair (debounced) and optional missing WP-Cron repair.
        add_action(
            'init',
            function () {
                if (get_transient('mapsvg_repair_stale_import')) {
                    return;
                }
                set_transient('mapsvg_repair_stale_import', 1, 60);
                \MapSVG\SchemaImportLifecycle::repairStaleImports();
            },
            20
        );
        add_action(
            'init',
            function () {
                if (get_transient('mapsvg_repair_gs_cron')) {
                    return;
                }
                set_transient('mapsvg_repair_gs_cron', 1, 120);
                \MapSVG\GoogleSheetSync::repairMissingScheduledEvents();
            },
            25
        );
    }

    public function setupGutenberg()
    {
        $postEditorMapLoader = new PostEditorMapLoader();
        $postEditorMapLoader->init();

        $mapBlock = new MapBlock();
        $mapBlock->init();
    }

    /**
     * @return void
     */
    public function setupElementor(): void
    {
        if (!did_action('elementor/loaded')) {
            add_action('elementor/loaded', function () {
                $integration = new ElementorIntegration();
                $integration->init();
            });
            return;
        }

        $integration = new ElementorIntegration();
        $integration->init();
    }

    public function addShortcodePostType()
    {
        register_post_type(
            'mapsvg_shortcode',
            array(
                'label'               => 'MapSVG Embeddable Shortcode Blank Page',
                'public'              => false,
                'show_ui'             => false,
                'exclude_from_search' => true,
                'supports'            => array('title', 'editor'),
            )
        );
    }

    /**
     * @param string[] $vars
     * @return string[]
     */
    public function addCustomQueryVars($vars)
    {
        $vars[] = '_mapsvg';
        return $vars;
    }
}
