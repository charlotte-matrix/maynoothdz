<?php

/**
 * Created by PhpStorm.
 * User: Roma
 * Date: 24.10.18
 * Time: 10:52
 */

namespace MapSVG;

/**
 * Router class that registers routes for WP Rest API
 * and also does DB upgrades
 */
class Router
{

	public function __construct()
	{
		$this->run();
	}

	public function run()
	{
		// Legacy shortcode support
		add_action('parse_request', function ($wp) {
			if ($wp->request === 'mapsvg_sc') { // phpcs:ignore
				if (isset($_GET['mapsvg_shortcode'])) {
					$legacy = wp_unslash($_GET['mapsvg_shortcode']); // phpcs:ignore
					wp_redirect(home_url('/_mapsvg/shortcode/' . rawurlencode($legacy)), 301);
					exit;
				}
				if (isset($_GET['mapsvg_embed_post'])) {
					$legacy = wp_unslash($_GET['mapsvg_embed_post']); // phpcs:ignore
					wp_redirect(home_url('/_mapsvg/post/' . rawurlencode($legacy)), 301);
					exit;
				}
			}
		});

		// Register _mapsvg endpoint on both frontend and admin
		add_action('init', function () {
			if (!get_option('mapsvg_rewrite_flushed')) {
				static::addMapsvgEndpoint();
			}
		});
		add_action('parse_request', array($this, 'handleMapsvgRequest'));

		add_action('rest_api_init', function () {

			$this->registerMethodCheckRoutes();

			

			$this->registerMapRoutes();
			$this->registerMapV2Routes();

			$this->registerSchemaRoutes();

			$this->registerCollectionRoutes();
			$this->registerRegionRoutes();  // kept as backwards-compat aliases
			$this->registerObjectRoutes();  // kept as backwards-compat aliases

			$this->registerPostRoutes();

			$this->registerGeocodingRoutes();

			

			$this->registerGoogleApiRoutes();
			$this->registerSvgFileRoutes();
			
			$this->registerMarkerFileRoutes();
			
			$this->registerOptionsRoutes();

			

			

			$this->registerPostTypesRoutes();
			$this->registerImportLogsRoutes();
		});

		add_filter('rest_pre_serve_request', array($this, 'add_nocache_headers'), 11, 4);

		// add_action('send_headers', array($this, 'finish_loggeer'), 10);
	}


	public static function addMapsvgEndpoint()
	{
		if (!get_option('mapsvg_rewrite_flushed')) {
			add_rewrite_rule('^_mapsvg/([^/]+)/?$', 'index.php?_mapsvg=$matches[1]', 'top');
			add_rewrite_tag('%_mapsvg%', '([^&]+)');
			flush_rewrite_rules();
			update_option('mapsvg_rewrite_flushed', true);
		}
	}

	public static function removeMapsvgEndpoint()
	{
		flush_rewrite_rules();
		update_option('mapsvg_rewrite_flushed', false);
	}


	// function finish_loggeer($served)
	// {
	// 	Logger::sendHeaders();
	// }

	function add_nocache_headers($served, $response, $request, $server)
	{

		if (is_a($response, 'WP_REST_Response') && strpos($request->get_route(), 'mapsvg/v') !== false) {
			nocache_headers();
		}

		

		return $served;
	}


	public static function methodCheck()
	{
		return new \WP_REST_Response(array('message' => 'Method check is available'), 200);
	}

	public function registerMethodCheckRoutes()
	{
		$baseRoute = '/method-check/';
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'PUT',
				'callback' => '\MapSVG\Router::methodCheck',
				'permission_callback' => function () {
					return true;
				}
			),
			array(
				'methods' => 'DELETE',
				'callback' => '\MapSVG\Router::methodCheck',
				'permission_callback' => function () {
					return true;
				}
			)
		));
	}

	private function requireAdmin(): void
	{
		if (!is_user_logged_in() || !current_user_can('manage_options')) {
			wp_redirect(admin_url()); // or home_url()
			exit;
		}
	}


	public function handleMapsvgRequest($wp)
	{
		if (strpos($wp->request, '_mapsvg/') === 0) {
			$path = trim($wp->request, '/');
			$parts = explode('/', $path);

			if (count($parts) > 1) {
				$action = $parts[1];
				switch ($action) {
										
							
					default:
						wp_redirect(home_url());
						break;
				}
			} else {
				wp_redirect(home_url());
			}

			return;
		}
	}

	/**
	 * Handle shortcode requests for /_mapsvg/shortcode/...
	 */
	private function handleShortcodeRequest($wp, $parts)
	{
		if (count($parts) > 1) {
			// Get the full shortcode from the URL parts and decode it
			// Check for shortcode in query parameter
			if (isset($_GET['s'])) {
				$shortcode = sanitize_text_field(wp_unslash($_GET['s']));
			} else {
				// Fallback to URL parts for backward compatibility
				$shortcode = implode('/', array_slice($parts, 2));
				$shortcode = urldecode($shortcode);
			}

			// Ensure the shortcode has proper brackets
			if (!str_starts_with($shortcode, '[')) {
				$shortcode = '[' . $shortcode;
			}
			if (!str_ends_with($shortcode, ']')) {
				$shortcode = $shortcode . ']';
			}

			// Include the necessary files
			\MapSVG\ShortcodeHandler::renderShortcode($shortcode);

			// Don't exit - let WordPress continue to process the template_redirect action
			return;
		} else {
			wp_redirect(home_url());
		}
		return;
	}

	/**
	 * Handle post embed requests for /_mapsvg/post/...
	 */
	private function handlePostRequest($wp, $parts)
	{
		if (count($parts) > 2) {
			$post_id = (int)$parts[2];

			// Include the post embed renderer
			$post_file = MAPSVG_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . 'Domain' . DIRECTORY_SEPARATOR . 'Shortcode' . DIRECTORY_SEPARATOR . 'ShortcodePage.php';
			if (file_exists($post_file)) {
				include($post_file);
				// Use the new OOP approach
				\MapSVG\ShortcodeHandler::renderPostEmbed($post_id);
			} else {
				// Fallback if file doesn't exist
				echo 'Post embed renderer not found';
			}
		} else {
			wp_redirect(home_url());
		}
		return;
	}



	

	

	

	
	public function registerOptionsRoutes()
	{
		$baseRoute = '/options/';
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\OptionsController::update',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
	}

	public function registerMapRoutes()
	{
		$baseRoute = '/maps/';
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\MapController::index',
				'permission_callback' => function () {
					return true;
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '(?P<id>\d+)', array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\MapController::get',
				'permission_callback' => function () {
					return true;
				}
			)
		));
		$routeAdded = register_rest_route('mapsvg/v1', $baseRoute . '(?P<id>\d+)/svg', array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\MapController::getSvg',
				'permission_callback' => function () {
					return true;
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '(?P<id>\d+)/copy', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\MapController::copy',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '(?P<id>\d+)', array(
			array(
				'methods' => 'PUT',
				'callback' => '\MapSVG\MapController::update',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '(?P<id>\d+)', array(
			array(
				'methods' => 'DELETE',
				'callback' => '\MapSVG\MapController::delete',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/createFromV2', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\MapController::createFromV2',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\MapController::create',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
	}

	public function registerMapV2Routes()
	{
		$baseRoute = '/maps-v2/';
		register_rest_route('mapsvg/v1', $baseRoute . '(?P<id>\d+)', array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\MapV2Controller::get',
				'permission_callback' => function () {
					return true;
				}
			)
		));
	}

	public function registerPostRoutes()
	{
		register_rest_route('mapsvg/v1', '/posts', array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\PostController::index',
				'permission_callback' => function () {
					return true;
				}
			)
		));
	}

	/**
	 * Unified collection routes — the canonical API for both objects and regions.
	 * Old /objects/ and /regions/ paths are kept below as backwards-compat aliases.
	 */
	public function registerCollectionRoutes()
	{
		$baseRoute = '/collection/(?P<_collection_name>[a-zA-Z0-9-_]+)';

		register_rest_route('mapsvg/v1', $baseRoute, array(
			array('methods' => 'GET', 'callback' => '\\MapSVG\\CollectionController::index', 'permission_callback' => '__return_true')
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/(?P<id>[^/]+)', array(
			array('methods' => 'GET', 'callback' => '\\MapSVG\\CollectionController::get', 'permission_callback' => '__return_true')
		));
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array('methods' => 'POST', 'callback' => '\\MapSVG\\CollectionController::create', 'permission_callback' => function () {
				return current_user_can('edit_posts');
			})
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/(?P<id>[^/]+)', array(
			array('methods' => 'PUT', 'callback' => '\\MapSVG\\CollectionController::update', 'permission_callback' => function () {
				return current_user_can('edit_posts');
			})
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/(?P<id>[^/]+)', array(
			array('methods' => 'DELETE', 'callback' => '\\MapSVG\\CollectionController::delete', 'permission_callback' => function () {
				return current_user_can('edit_posts');
			})
		));
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array('methods' => 'DELETE', 'callback' => '\\MapSVG\\CollectionController::clear', 'permission_callback' => function () {
				return current_user_can('edit_posts');
			})
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/orphans', array(
			array('methods' => 'DELETE', 'callback' => '\\MapSVG\\CollectionController::deleteOrphans', 'permission_callback' => function () {
				return current_user_can('edit_posts');
			})
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/import', array(
			array('methods' => 'POST', 'callback' => '\\MapSVG\\CollectionController::import', 'permission_callback' => function () {
				return current_user_can('edit_posts');
			})
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/import-csv', array(
			array('methods' => 'POST', 'callback' => '\\MapSVG\\CollectionController::importCsv', 'permission_callback' => function () {
				return current_user_can('edit_posts');
			})
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/import-csv/preflight', array(
			array('methods' => 'POST', 'callback' => '\\MapSVG\\CollectionController::preflightCsv', 'permission_callback' => function () {
				return current_user_can('edit_posts');
			})
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/import-csv/process', array(
			array('methods' => 'POST', 'callback' => '\\MapSVG\\CollectionController::importCsvProcess', 'permission_callback' => function () {
				return current_user_can('edit_posts');
			})
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/import-csv-url', array(
			array('methods' => 'POST', 'callback' => '\\MapSVG\\CollectionController::importCsvFromUrl', 'permission_callback' => function () {
				return current_user_can('edit_posts');
			})
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/distinct/(?P<_field_name>.+)', array(
			array('methods' => 'GET', 'callback' => '\\MapSVG\\CollectionController::getDistinctValues', 'permission_callback' => function () {
				return current_user_can('edit_posts');
			})
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/sync', array(
			array('methods' => 'POST', 'callback' => '\\MapSVG\\CollectionController::sync', 'permission_callback' => '__return_true') // HMAC-verified inside
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/setup-appscript', array(
			array('methods' => 'POST', 'callback' => '\\MapSVG\\CollectionController::setupAppScript', 'permission_callback' => function () {
				return current_user_can('edit_posts');
			})
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/reset-appscript', array(
			array('methods' => 'POST', 'callback' => '\\MapSVG\\CollectionController::resetAppScript', 'permission_callback' => function () {
				return current_user_can('edit_posts');
			})
		));
	}

	public function registerRegionRoutes()
	{
		$baseRoute = '/regions/(?P<_collection_name>[a-zA-Z0-9-_]+)';
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\RegionsController::index',
				'permission_callback' => function () {
					return true;
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/(?P<id>.+)', array(
			array(
				'methods' => 'PUT',
				'callback' => '\MapSVG\RegionsController::update',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\RegionsController::create',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/import', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\RegionsController::import',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/import-csv', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\RegionsController::importCsv',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/import-csv-url', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\RegionsController::importCsvFromUrl',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/distinct/(?P<_field_name>.+)', array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\RegionsController::getDistinctValues',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/(?P<id>.+)', array(
			array(
				'methods' => 'DELETE',
				'callback' => '\MapSVG\RegionsController::delete',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/(?P<id>[^/]+)', array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\RegionsController::get',
				'permission_callback' => function () {
					return true;
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/sync', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\RegionsController::sync',
				'permission_callback' => '__return_true', // HMAC-verified inside the controller
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/setup-appscript', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\RegionsController::setupAppScript',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/reset-appscript', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\RegionsController::resetAppScript',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
	}

	public function registerObjectRoutes()
	{
		$baseRoute = '/objects/(?P<_collection_name>[a-zA-Z0-9-_]+)';
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\ObjectsController::index',
				'permission_callback' => function () {
					return true;
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/(?P<id>.+)', array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\ObjectsController::get',
				'permission_callback' => function () {
					return true;
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/(?P<id>.+)', array(
			array(
				'methods' => 'PUT',
				'callback' => '\MapSVG\ObjectsController::update',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/(?P<id>.+)', array(
			array(
				'methods' => 'DELETE',
				'callback' => '\MapSVG\ObjectsController::delete',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'DELETE',
				'callback' => '\MapSVG\ObjectsController::clear',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\ObjectsController::create',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/import', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\ObjectsController::import',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/import-csv', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\ObjectsController::importCsv',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/import-csv-url', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\ObjectsController::importCsvFromUrl',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/import-csv/process', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\ObjectsController::importCsvProcess',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/distinct/(?P<_field_name>.+)', array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\ObjectsController::getDistinctValues',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/sync', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\ObjectsController::sync',
				'permission_callback' => '__return_true', // HMAC-verified inside the controller
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/setup-appscript', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\ObjectsController::setupAppScript',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/reset-appscript', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\ObjectsController::resetAppScript',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
	}

	public function registerGeocodingRoutes()
	{
		$baseRoute = '/geocoding';
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods'  => 'GET',
				'callback' => '\MapSVG\GeocodingController::index',
				'permission_callback' => function () {
					return true;
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/queue', array(
			array(
				'methods'  => 'GET',
				'callback' => '\MapSVG\ObjectsController::geocodeStatus',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
	}

	public function registerSchemaRoutes()
	{
		$baseRoute = '/schemas';
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\SchemaController::index',
				'permission_callback' => function () {
					return true;
				}
			)
		));
		
		register_rest_route('mapsvg/v1', $baseRoute . '/(?P<id>\d+)', array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\SchemaController::get',
				'permission_callback' => function () {
					return true;
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/(?P<id>\d+)', array(
			array(
				'methods' => 'PUT',
				'callback' => '\MapSVG\SchemaController::update',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/(?P<id>\d+)', array(
			array(
				'methods' => 'DELETE',
				'callback' => '\MapSVG\SchemaController::delete',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\SchemaController::create',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
	}

	

	public function registerGoogleApiRoutes()
	{
		$baseRoute = '/googleapikeys';
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'PUT',
				'callback' => '\MapSVG\GoogleApiKeysController::update',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
	}

	

	public function registerSvgFileRoutes()
	{
		$baseRoute = '/svgfile';
		register_rest_route('mapsvg/v1', $baseRoute . '/download', array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\SVGFileController::download',
				'permission_callback' => function () {
					return true;
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\SVGFileController::create',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/update', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\SVGFileController::update',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/copy', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\SVGFileController::copy',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '/reload', array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\SVGFileController::reload',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
	}

	public function registerMarkerFileRoutes()
	{
		$baseRoute = '/markers';
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'POST',
				'callback' => '\MapSVG\MarkersController::create',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
	}

	

	
	

	public function registerPostTypesRoutes()
	{
		$baseRoute = '/post-types/';
		register_rest_route('mapsvg/v1', $baseRoute, array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\PostTypesController::index',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '(?P<_post_type>[a-zA-Z0-9_-]+)', array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\PostTypesController::get',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '(?P<_post_type>[a-zA-Z0-9_-]+)/field/(?P<_field_name>[a-zA-Z0-9_-]+)', array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\PostTypesController::getFieldValues',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				},
				'args' => array(
					'_field_name' => array(
						'required' => true,
						'validate_callback' => function ($param) {
							return PublicPostColumns::isAllowed($param);
						},
					),
				),
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '(?P<_post_type>[a-zA-Z0-9_-]+)/taxonomy/(?P<_taxonomy_name>[a-zA-Z0-9_-]+)', array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\PostTypesController::getTaxonomyValues',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
		register_rest_route('mapsvg/v1', $baseRoute . '(?P<_post_type>[a-zA-Z0-9_-]+)/meta/(?P<_meta_name>[a-zA-Z0-9_-]+)', array(
			array(
				'methods' => 'GET',
				'callback' => '\MapSVG\PostTypesController::getMetaValues',
				'permission_callback' => function () {
					return current_user_can('edit_posts');
				}
			)
		));
	}

	public function registerImportLogsRoutes(): void
	{
		register_rest_route('mapsvg/v1', '/import-logs', [
			[
				'methods'             => 'GET',
				'callback'            => '\MapSVG\ImportLogController::index',
				'permission_callback' => static fn() => current_user_can('manage_options'),
			],
		]);
	}
}
