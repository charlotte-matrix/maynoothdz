<?php

namespace MapSVG;

/**
 * Boots MapSVG Elementor integration when Elementor is available.
 */
class ElementorIntegration
{
	/** @var bool */
	private static $previewAssetsEnqueued = false;

	/**
	 * @return void
	 */
	public function init(): void
	{
		add_action('elementor/widgets/register', array($this, 'registerWidget'));
		add_action('elementor/elements/categories_registered', array($this, 'registerCategory'));
		add_action('elementor/preview/enqueue_scripts', array($this, 'enqueuePreviewAssets'));
		add_action('elementor/editor/after_enqueue_styles', array($this, 'enqueueEditorStyles'));
	}

	/**
	 * @param \Elementor\Widgets_Manager $widgets_manager Widgets manager.
	 * @return void
	 */
	public function registerWidget($widgets_manager): void
	{
		if (!class_exists('\\Elementor\\Widget_Base')) {
			return;
		}
		$widgets_manager->register(new ElementorMapWidget());
	}

	/**
	 * @param \Elementor\Elements_Manager $elements_manager Elements manager.
	 * @return void
	 */
	public function registerCategory($elements_manager): void
	{
		$elements_manager->add_category(
			'mapsvg',
			array(
				'title' => 'MapSVG',
				'icon'  => 'fa fa-map',
			)
		);
	}

	/**
	 * Load MapSVG + preview bridge inside the Elementor preview iframe.
	 *
	 * @return void
	 */
	public function enqueuePreviewAssets(): void
	{
		if (self::$previewAssetsEnqueued) {
			return;
		}
		self::$previewAssetsEnqueued = true;

		$scriptPath = MAPSVG_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'dist' . DIRECTORY_SEPARATOR . 'mapsvg-elementor.build.js';
		if (!file_exists($scriptPath)) {
			return;
		}

		Front::addJsCss();

		wp_enqueue_script(
			'mapsvg-elementor',
			MAPSVG_PLUGIN_URL . 'dist/mapsvg-elementor.build.js',
			array('jquery', 'mapsvg', 'elementor-frontend'),
			MAPSVG_ASSET_VERSION,
			true
		);
	}

	/**
	 * Custom widget icon in the Elementor panel.
	 *
	 * @return void
	 */
	public function enqueueEditorStyles(): void
	{
		$iconUrl = MAPSVG_PLUGIN_URL . 'img/logo-icon.svg';
		$css     = '.elementor-element .icon .mapsvg-el-icon,'
			. '.elementor-panel .elementor-element .icon .mapsvg-el-icon{'
			. 'width:28px;height:28px;display:inline-block;'
			. 'background:url(' . esc_url($iconUrl) . ') center/contain no-repeat;'
			. '}'
			. '.elementor-element .icon .mapsvg-el-icon:before,'
			. '.elementor-panel .elementor-element .icon .mapsvg-el-icon:before{content:"";}';

		wp_register_style('mapsvg-elementor-editor', false, array(), MAPSVG_ASSET_VERSION);
		wp_enqueue_style('mapsvg-elementor-editor');
		wp_add_inline_style('mapsvg-elementor-editor', $css);
	}
}
