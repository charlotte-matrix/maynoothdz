<?php

namespace MapSVG;

/**
 * Registers the mapsvg/map Gutenberg block and enqueues editor assets.
 */
class MapBlock
{
	/** @var bool */
	private static $parentAssetsEnqueued = false;

	/**
	 * @return void
	 */
	public function init(): void
	{
		// Called from PluginHooks during `init`, so register immediately.
		$this->registerScript();
		$this->register();

		// Parent editor window — mapsvg is an ES module and often does not load
		// inside the content iframe (Gutenberg #64482). Preview uses parent mapsvg
		// with an HTMLElement that lives in the iframe (shadow DOM styles OK).
		add_action('enqueue_block_editor_assets', array($this, 'enqueueParentAssets'), 1);
	}

	/**
	 * @return void
	 */
	public function registerScript(): void
	{
		$scriptPath = MAPSVG_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'mapsvg-block.build.js';
		if (!file_exists($scriptPath)) {
			return;
		}

		wp_register_script(
			'mapsvg-block',
			MAPSVG_PLUGIN_URL . 'dist/mapsvg-block.build.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-block-editor',
				'wp-components',
				'wp-i18n',
				'wp-api-fetch',
				'wp-compose',
			),
			MAPSVG_ASSET_VERSION,
			true
		);
	}

	/**
	 * @return void
	 */
	public function register(): void
	{
		$blockJsonPath = MAPSVG_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR
			. 'mapsvg-admin' . DIRECTORY_SEPARATOR . 'gutenberg' . DIRECTORY_SEPARATOR
			. 'block' . DIRECTORY_SEPARATOR . 'block.json';

		if (!file_exists($blockJsonPath)) {
			return;
		}

		if (!wp_script_is('mapsvg-block', 'registered')) {
			return;
		}

		register_block_type(
			$blockJsonPath,
			array(
				'render_callback' => array($this, 'render'),
			)
		);
	}

	/**
	 * Renders the block on the front end via the existing shortcode renderer.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string
	 */
	public function render(array $attributes): string
	{
		$mapId = isset($attributes['mapId']) ? absint($attributes['mapId']) : 0;
		if ($mapId <= 0) {
			return '';
		}

		$mapsRepo = RepositoryFactory::get('map');
		$map = $mapsRepo->findById($mapId);
		if (!$map) {
			return '<p>' . esc_html__('Map not found.', 'mapsvg') . '</p>';
		}

		$atts = array(
			'id' => (string) $mapId,
		);

		if (!empty($attributes['title'])) {
			$atts['title'] = sanitize_text_field((string) $attributes['title']);
		}

		if (!empty($attributes['selected'])) {
			$atts['selected'] = sanitize_text_field((string) $attributes['selected']);
		}

		if (!empty($attributes['lazy'])) {
			$atts['lazy'] = 'true';
		}

		$front = new Front();
		return $front->renderShortcode($atts);
	}

	/**
	 * Ensure MapSVG runtime + block script are available in the parent editor window.
	 *
	 * @return void
	 */
	public function enqueueParentAssets(): void
	{
		if (self::$parentAssetsEnqueued) {
			return;
		}
		self::$parentAssetsEnqueued = true;

		Front::addJsCss();

		// Block script must run after the mapsvg module initializes.
		$scripts = wp_scripts();
		if (isset($scripts->registered['mapsvg-block'])) {
			$scripts->registered['mapsvg-block']->deps[] = 'mapsvg';
		}

		wp_enqueue_script('mapsvg-block');
	}
}
