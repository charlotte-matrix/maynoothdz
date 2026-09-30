<?php

namespace MapSVG;

/**
 * Class PostTypesController
 * @package MapSVG
 */
class PostTypesController extends Controller
{

	/**
	 * Returns the list of WP post types.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function index($request)
	{
		/** @var \MapSVG\PostTypesRepository $repo */
		$repo = RepositoryFactory::get('postType');

		$post_types = $repo->find();
		return self::render($post_types);
	}

	/**
	 * Returns the list of WP post types.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function get($request)
	{
		$post_type = isset($request['_post_type']) ? $request['_post_type'] : null;
		if (!$post_type) {
			return self::render(['error' => 'No post_type specified.']);
		}
		/** @var \MapSVG\PostTypesRepository $repo */
		$repo = RepositoryFactory::get('postType');
		$data = $repo->get($post_type);

		return self::render($data);
	}

	/**
	 * Returns distinct values for a given field name for a post type.
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function getFieldValues($request)
	{
		$post_type = isset($request['_post_type']) ? $request['_post_type'] : null;
		$field = isset($request['_field_name']) ? $request['_field_name'] : null;
		if (!$post_type || !$field) {
			return self::render(['error' => 'No post_type or field specified.']);
		}
		if (!PublicPostColumns::isAllowed($field)) {
			return self::render(['error' => 'Field is not allowed.', 'items' => []], 403);
		}
		/** @var \MapSVG\PostTypesRepository $repo */
		$repo = RepositoryFactory::get('postType');
		$values = $repo->getFieldValues($field, $post_type);
		return self::render(['items' => $values]);
	}

	/**
	 * Returns unique taxonomy term names for a given taxonomy and post type.
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function getTaxonomyValues($request)
	{
		$post_type = isset($request['_post_type']) ? $request['_post_type'] : null;
		$taxonomy = isset($request['_taxonomy_name']) ? $request['_taxonomy_name'] : null;
		if (!$post_type || !$taxonomy) {
			return self::render(['error' => 'No post_type or taxonomy specified.']);
		}
		/** @var \MapSVG\PostTypesRepository $repo */
		$repo = RepositoryFactory::get('postType');
		$values = $repo->getTaxonomyValues($taxonomy, $post_type);
		return self::render(['items' => $values]);
	}

	/**
	 * Returns unique meta values for a given meta key and post type.
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function getMetaValues($request)
	{
		$post_type = isset($request['_post_type']) ? $request['_post_type'] : null;
		$meta = isset($request['_meta_name']) ? $request['_meta_name'] : null;
		if (!$post_type || !$meta) {
			return self::render(['error' => 'No post_type or meta specified.']);
		}
		/** @var \MapSVG\PostTypesRepository $repo */
		$repo = RepositoryFactory::get('postType');
		$values = $repo->getMetaValues($meta, $post_type);
		return self::render(['items' => $values]);
	}
}
