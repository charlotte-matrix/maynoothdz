<?php

namespace MapSVG;

use MapSVG\Options;
use MapSVG\Shortcode;

/**
 * Class ShortcodePage
 * 
 * Handles rendering of MapSVG shortcodes and post embeds on custom pages
 */
class ShortcodePage
{
	private string $slug;
	private ?int $postId;
	private string $postContent;
	private array $args;

	/**
	 * Constructor
	 * 
	 * @param array $args Configuration arguments
	 */
	public function __construct(array $args)
	{

		$this->args = $args;
		$this->slug = $args['slug'] ?? '';
		$this->postId = $args['ID'] ?? null;
		$this->postContent = $args['post_content'] ?? '';

		// Add the filter only for the next query
		add_filter('the_posts', [$this, 'renderPage'], 10, 2);
	}

	/**
	 * Renders the page by intercepting the WordPress query
	 * 
	 * @param array $posts Array of posts from the query
	 * @param WP_Query|null $query The WordPress query object
	 * @return array Array of posts to display
	 */
	public function renderPage($posts, $query = null): array
	{
		global $wp;

		// Only run for the main query and check if we're handling a shortcode request
		if (
			$query && $query->is_main_query() &&
			(strpos($wp->request, $this->slug) === 0)
		) {

			// Get or create the post
			$post = $this->getOrCreatePost();

			// Ensure the post content is set for rendering
			if ($this->postContent) {
				$post->post_content = $this->postContent;
			}

			// Remove the filter so it doesn't affect other queries
			remove_filter('the_posts', [$this, 'renderPage'], 10);
			return [$post];
		}

		// Remove the filter for all other queries as well
		remove_filter('the_posts', [$this, 'renderPage'], 10);
		return $posts;
	}

	/**
	 * Gets an existing post or creates a new one
	 * 
	 * @return WP_Post The post object
	 */
	private function getOrCreatePost()
	{
		// Try to find an existing post of type mapsvg_shortcode with this slug
		if ($this->postId) {
			$existing = [get_post($this->postId)];
		} else {
			$existing = get_posts([
				'name' => $this->slug,
				'post_type' => 'mapsvg_shortcode',
				'post_status' => 'publish',
				'numberposts' => 1,
			]);
		}

		if ($existing) {
			$post = $existing[0];

			if ($this->postContent) {
				// Optionally, update content dynamically							
				$post->post_content = $this->postContent;
			}
		} else {
			$post = $this->createNewPost();
		}

		// Ensure the post content is set for rendering
		if ($this->postContent) {
			$post->post_content = $this->postContent;
		}

		return $post;
	}

	/**
	 * Creates a new post in the database
	 * 
	 * @return WP_Post The newly created post
	 */
	private function createNewPost()
	{
		$admin_users = get_users([
			'role'    => 'administrator',
			'orderby' => 'ID',
			'order'   => 'ASC',
			'number'  => 1,
		]);
		$admin_id = !empty($admin_users) ? $admin_users[0]->ID : 1;

		if ($this->postId) {
			$post_id = $this->postId;
		} else {
			// Create a new post in the DB
			$post_id = wp_insert_post([
				'post_title'   => '',
				'post_name'    => $this->slug,
				'post_type'    => 'mapsvg_shortcode',
				'post_status'  => 'publish',
				'post_content' => '',
				'post_author'  => $admin_id,
			]);
		}

		return get_post($post_id);
	}
}




/**
 * Legacy function for backward compatibility
 * 
 * @return void
 */
// function mapsvg_blank_template()
// {
// 	include("blank-template.php");
// 	exit;
// }

// // Legacy support for GET parameters (can be removed after migration)
// if (isset($_GET['mapsvg_shortcode'])) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
// 	$shortcode = sanitize_text_field(wp_unslash($_GET['mapsvg_shortcode']));  // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
// 	ShortcodeHandler::renderShortcode($shortcode);
// }

// if (isset($_GET['mapsvg_embed_post'])) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing
// 	$post_id = (int)$_GET['mapsvg_embed_post'];  // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing	
// 	ShortcodeHandler::renderPostEmbed($post_id);
// }
