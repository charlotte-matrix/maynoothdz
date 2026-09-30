<?php

namespace MapSVG;

use MapSVG\Options;
use MapSVG\Shortcode;


/**
 * Class ShortcodeHandler
 * 
 * Main handler class for processing shortcode and post embed requests
 */
class ShortcodeHandler
{

	public static function loadBlankTemplate(): void
	{
		// blank template

		if (! function_exists('blank_slate_bootstrap')) {

			/**
			 * Initialize the plugin.
			 */
			function blank_slate_bootstrap()
			{

				// load_plugin_textdomain('blank-slate', false, __DIR__ . '/languages');

				// Register the blank slate template
				blank_slate_add_template(
					'blank-slate-template.php',
					'mapsvg-lite'
					
				);

				// Add our template(s) to the dropdown in the admin
				add_filter(
					'theme_page_templates',
					function (array $templates) {
						return array_merge($templates, blank_slate_get_templates());
					}
				);

				// Ensure our template is loaded on the front end
				add_filter(
					'template_include',
					function ($template) {

						if (is_singular()) {

							$assigned_template = get_post_meta(get_the_ID(), '_wp_page_template', true);

							if (blank_slate_get_template($assigned_template)) {

								if (file_exists($assigned_template)) {
									return $template;
								}

								//$file = wp_normalize_path( plugin_dir_path( __FILE__ ) . '/templates/' . $assigned_template );
								$file = wp_normalize_path(plugin_dir_path(__FILE__) .  $assigned_template);

								if (file_exists($file)) {
									return $template;
								}
							}
						}

						return $template;
					}
				);
			}
		}

		if (! function_exists('blank_slate_get_templates')) {

			/**
			 * Get all registered templates.
			 *
			 * @return array
			 */
			function blank_slate_get_templates()
			{
				return (array) apply_filters('blank_slate_templates', array());
			}
		}

		if (! function_exists('blank_slate_get_template')) {

			/**
			 * Get a registered template.
			 *
			 * @param string $file Template file/path
			 *
			 * @return string|null
			 */
			function blank_slate_get_template($file)
			{
				$templates = blank_slate_get_templates();

				return isset($templates[$file]) ? $templates[$file] : null;
			}
		}

		if (! function_exists('blank_slate_add_template')) {

			/**
			 * Register a new template.
			 *
			 * @param string $file Template file/path
			 * @param string $label Label for the template
			 */
			function blank_slate_add_template($file, $label)
			{
				add_filter(
					'blank_slate_templates',
					function (array $templates) use ($file, $label) {
						$templates[$file] = $label;

						return $templates;
					}
				);
			}
		}

		add_action('plugins_loaded', 'blank_slate_bootstrap');
	}

	/**
	 * Renders a shortcode on a custom page
	 * 
	 * @param string $shortcode The shortcode content to render
	 * @return void
	 */
	public static function renderShortcode(string $shortcode): void
	{

		self::loadBlankTemplate();

		// Validate shortcode
		if (empty($shortcode)) {
			wp_die('Invalid shortcode parameter');
		}

		$shortcodeName = Shortcode::getName($shortcode);
		$allowedShortcodes = Options::get('allowed_shortcodes');

		if (!in_array($shortcodeName, $allowedShortcodes)) {
			$shortcode = "Add \"$shortcodeName\" to the allowed shortcodes in the MapSVG settings.";
		}

		// Add CF7 parameters filter
		self::addContactForm7Filter();

		$args = [
			'slug' => '_mapsvg',
			'post_title' => '',
			'post_content' => $shortcode
		];
		// Redirect to template
		add_action('template_redirect', [self::class, 'redirectToTemplate']);

		new ShortcodePage($args);
	}

	/**
	 * Renders a post embed on a custom page
	 * 
	 * @param int $postId The ID of the post to embed
	 * @return void
	 */
	public static function renderPostEmbed(int $postId): void
	{

		self::loadBlankTemplate();
		$args = [
			'ID' => $postId,
			'slug' => '_mapsvg',
		];

		// Redirect to template
		add_action('template_redirect', [self::class, 'redirectToTemplate']);

		new ShortcodePage($args);
	}

	/**
	 * Adds Contact Form 7 shortcode attributes filter
	 * 
	 * @return void
	 */
	private static function addContactForm7Filter(): void
	{
		add_filter('shortcode_atts_wpcf7', function ($out, $pairs, $atts) {
			foreach ($atts as $key => $val) {
				$out[$key] = $atts[$key];
			}
			return $out;
		}, 10, 3);
	}

	/**
	 * Redirects to the blank template
	 * 
	 * @return void
	 */
	public static function redirectToTemplate(): void
	{

		$template_path = MAPSVG_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'Domain' . DIRECTORY_SEPARATOR . 'Shortcode' . DIRECTORY_SEPARATOR . 'blank-template.php';

		if (file_exists($template_path)) {
			include($template_path);
		} else {
			// Fallback: render the shortcode directly
			global $post;
			if ($post && !empty($post->post_content)) {
				echo do_shortcode($post->post_content);
			} else {
				echo 'Shortcode template not found';
			}
		}
		exit;
	}
}
