<?php

/**
 * Created by PhpStorm.
 * User: Roma
 * Date: 24.10.18
 * Time: 9:59
 */

namespace MapSVG;

class Front
{
	public $mapScripts;

	public function __construct()
	{
		$this->registerShortcode();
	}

	/**
	 * Add common JS & CSS
	 */
	public static function addJsCss($theme = "default")
	{

		// wp_register_style('mapsvg', MAPSVG_PLUGIN_URL . 'dist/mapsvg.css', null, MAPSVG_ASSET_VERSION);
		// wp_enqueue_style('mapsvg');
		// wp_register_style('mapsvg', MAPSVG_PLUGIN_URL . 'dist/mapsvg-bundle.css', null, MAPSVG_ASSET_VERSION);
		// wp_enqueue_style('mapsvg');

		// wp_register_style('nanoscroller', MAPSVG_PLUGIN_URL . 'js/vendor/nanoscroller/nanoscroller.css', null, '0.8.7');
		// wp_enqueue_style('nanoscroller');

		// wp_register_style('select2', MAPSVG_PLUGIN_URL . 'js/vendor/select2/select2.min.css', null, '4.0.31');
		// wp_enqueue_style('select2');

		// wp_register_script('jquery.mousewheel', MAPSVG_PLUGIN_URL . 'js/vendor/jquery-mousewheel/jquery.mousewheel.min.js', array('jquery'), '3.0.6');
		// wp_enqueue_script('jquery.mousewheel', null, '3.0.6');

		wp_register_script('mselect2', MAPSVG_PLUGIN_URL . 'js/vendor/select2/select2.full.min.js', array('jquery'), '4.0.31', true);
		wp_enqueue_script('mselect2');

		wp_register_script('nanoscroller', MAPSVG_PLUGIN_URL . 'js/vendor/nanoscroller/jquery.nanoscroller.min.js', null, '0.8.7', true);
		wp_enqueue_script('nanoscroller');

		wp_register_script('typeahead', MAPSVG_PLUGIN_URL . 'js/vendor/typeahead/typeahead.jquery.js', null, '0.11.1', true);
		wp_enqueue_script('typeahead');

		wp_register_script('bloodhound', MAPSVG_PLUGIN_URL . 'js/vendor/typeahead/bloodhound.js', null, '0.11.1', true);
		wp_enqueue_script('bloodhound');

		wp_register_script('handlebars', MAPSVG_PLUGIN_URL . 'js/vendor/handlebars/handlebars.min.js', null, '4.7.9', true);
		wp_enqueue_script('handlebars');
		wp_enqueue_script('handlebars-helpers', MAPSVG_PLUGIN_URL . 'js/vendor/handlebars/handlebars-helpers.js', null, MAPSVG_ASSET_VERSION, true);


		$mapsvgDeps = array('jquery', 'nanoscroller', 'mselect2', 'handlebars', 'handlebars-helpers');

		wp_register_script('mapsvg', MAPSVG_PLUGIN_URL . 'dist/mapsvg.js', $mapsvgDeps, MAPSVG_ASSET_VERSION, true);

		wp_localize_script('mapsvg', 'mapsvgFrontendParams', array(
			'routes' => array(
				'root'      => MAPSVG_PLUGIN_RELATIVE_URL,
				'api' => get_rest_url(null, 'mapsvg/v1/'),
				'templates' => MAPSVG_PLUGIN_RELATIVE_URL . 'js/mapsvg-admin/templates/',
				'maps'      => wp_parse_url(MAPSVG_MAPS_URL, PHP_URL_PATH),
				'uploads'   => wp_parse_url(MAPSVG_UPLOADS_URL, PHP_URL_PATH),
				'home' => home_url()
			),
			'styles' => array(
				[
					'name' => 'nanoscroller',
					'url' => MAPSVG_PLUGIN_URL . 'js/vendor/nanoscroller/nanoscroller.css',
					'version' => 	'0.8.7'
				],
				[
					'name' => 'select2',
					'url' => MAPSVG_PLUGIN_URL . 'js/vendor/select2/select2.min.css',
					'version' => 	'4.0.31'
				],
				[
					'name' => 'mapsvg',
					'url' => MAPSVG_PLUGIN_URL . 'dist/mapsvg-bundle.css',
					'version' => 	MAPSVG_ASSET_VERSION
				],
				// ADD THEME FILES
				// [
				// 	'name' => 'mapsvg-theme',
				// 	'url' => MAPSVG_PLUGIN_URL . 'themes/' . $theme . '/assets/css/styles.css',
				// 	'version' => 	MAPSVG_ASSET_VERSION
				// ],
			),
			'nonce' => wp_create_nonce('wp_rest'),
			'google_maps_api_key' => Options::get("google_api_key"),
		));

		add_filter("script_loader_tag", "\MapSVG\Front::add_module_to_mapsvg_script", 10, 3);
		// wp_add_inline_script('mapsvg', "window.addEventListener('MapSVGDefined', () => mapsvgCore.init(JSON.parse(mapsvgFrontendParams))); mapsvgFrontendParams = null; delete window.mapsvgFrontendParams;");
		wp_enqueue_script('mapsvg');


		add_filter('litespeed_optimize_js_excludes', '\MapSVG\Front::jsExclude');
		add_filter('litespeed_optm_js_defer_exc', '\MapSVG\Front::jsExclude');
		add_filter('litespeed_optm_gm_js_exc', '\MapSVG\Front::jsExclude');
	}

	static function jsExclude($excludes = array())
	{
		$my_custom_excludes = array(
			'mapsvg.js',
			'handlebars-helpers.js',
			'handlebars.min.js',
			'bloodhound.js',
			'typeahead.jquery.js',
			'select2.full.min.js',
			'jquery.nanoscroller.min.js'
		);
		return array_merge($excludes, $my_custom_excludes);
	}




	static function add_module_to_mapsvg_script($tag, $handle, $src)
	{
		$defer_scripts = ['mapsvg', 'nanoscroller', 'mselect2', 'typeahead', 'bloodhound', 'handlebars', 'handlebars-helpers'];
		if (in_array($handle, $defer_scripts)) {
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- This script needs to be loaded as a module			
			$tag = '<script ';
			$tag .= ' data-no-minify="1" ';
			if ($handle === "mapsvg") {
				$tag .= ' type="module" ';
			}
			$tag .= 'src="' . esc_url($src) . '" id="' . $handle . '-js" defer></script>';
		}
		return $tag;
	}


	

	public function registerShortcode()
	{
		add_shortcode('mapsvg', array($this, 'renderShortcode'));
	}

	/**
	 *  Renders [mapsvg id="xxx"] shortcode.
	 *
	 *  Shortcode returns a <div id="mapsvg-XXX" class="mapsvg"> container with an
	 *  inline preloader, then JS mounts the map into that container.
	 *
	 * @param $atts
	 * Attributes from the shortcode
	 *
	 * @return string
	 * String that replaces the [mapsvg id="xxx"] shortcode
	 */
	function renderShortcode($atts)
	{
		// Sanitize all attributes to prevent XSS
		$atts = array_map('sanitize_text_field', $atts);

		if (!isset($atts['id'])) {
			return 'Error: no ID in mapsvg shortcode.';
		}

		// Sanitize ID as integer
		$atts['id'] = absint($atts['id']);

		$mapsRepo = RepositoryFactory::get("map");
		$map = $mapsRepo->findById($atts['id']);

		// Load JS/CSS files
		$theme = isset($map->options["theme"]) && isset($map->options["theme"]["name"]) ? $map->options["theme"]["name"] : "default";
		static::addJsCss($theme);
		do_action('mapsvg_shortcode');

		$width = isset($map->options["width"]) ? round((float)$map->options["width"], 2) : 1280;
		$height = isset($map->options["height"]) ? round((float)$map->options["height"], 2) : 800;
		$mapPadding =  round($height * 100 / $width, 2);

		$loadingText = isset($map->options["loadingText"]) ? Map::sanitizeLoadingTextForOutput($map->options["loadingText"]) : "";

		// Generate DIV container with a CSS-ready preloader (visible before JS/CSS bundles).
		$attributes = [
			'id' => "mapsvg-" . $this->generateContainerId($map->id),
			'data-id' => $map->id,
			'class' => 'mapsvg',
			'data-autoload' => 'true',
			'data-load-db' => isset($map->options["database"]) && isset($map->options["database"]["loadOnStart"]) && $map->options["database"]["loadOnStart"] === true ? "true" : "false",
			'data-loading-text' => $loadingText,
			'style' => 'width: 100%; height: 0; padding-bottom: ' . $mapPadding . '%; position: relative;'
		];
		if (isset($atts['selected']) && !empty($atts['selected'])) {
			$attributes["selected"] = str_replace(' ', '_', $atts['selected']);
		}
		if (isset($atts['lazy']) && $atts["lazy"] === "true") {
			$attributes["data-lazy"] = "true";
		}

		$divElement = new DOMElement('div', $attributes, MapPreloader::innerHtml($loadingText));

		return $divElement->render();
	}

	


	/**
	 * Generate container ID for the map
	 */
	function generateContainerId($mapId, $iteration = 0)
	{

		$iteration_str = '';

		if ($iteration !== 0) {
			$iteration_str = '-' . $iteration;
		}
		if (isset($this->mapScripts[$mapId . $iteration_str])) {
			$iteration++;
			return $this->generateContainerId($mapId, $iteration);
		} else {
			return $mapId . $iteration_str;
		}
	}

	/**
	 * Output MapSVG scripts
	 */
	function outputJsScript()
	{
		foreach ($this->mapScripts as $m) {
			echo esc_html($m);
		}
	}
}
