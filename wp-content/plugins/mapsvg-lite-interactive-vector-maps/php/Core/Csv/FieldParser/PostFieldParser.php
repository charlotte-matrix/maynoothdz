<?php

namespace MapSVG;

/**
 * Parses "post" fields from CSV.
 *
 * Accepted CSV formats:
 *   "42"           → post ID (numeric) — verified to exist, stored as-is
 *   "my-post-slug" → post slug — resolved to post ID via a WP query
 *
 * The DB column is always "post" (not the schema field name), storing
 * an integer post ID — matching what encodeParams does for this type.
 *
 * Context keys used:
 *   - (none required; post_type is read from $field->post_type)
 */
class PostFieldParser implements FieldParserInterface {

	public function supports( string $fieldType ): bool {
		return $fieldType === 'post';
	}

	public function parse( string $rawValue, object $field, array &$context = [] ): array {
		$rawValue = trim( $rawValue );

		if ( $rawValue === '' ) {
			return [ 'post' => null ];
		}

		$postType = ! empty( $field->post_type ) ? $field->post_type : 'any';

		if ( is_numeric( $rawValue ) ) {
			$post = get_post( (int) $rawValue );
			return [ 'post' => $post instanceof \WP_Post ? (int) $rawValue : null ];
		}

		// Slug: try specified post type first, then fall back to any
		$posts = get_posts( [
			'name'           => $rawValue,
			'post_type'      => $postType,
			'post_status'    => 'any',
			'numberposts'    => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );

		if ( empty( $posts ) && $postType !== 'any' ) {
			$posts = get_posts( [
				'name'          => $rawValue,
				'post_type'     => 'any',
				'post_status'   => 'any',
				'numberposts'   => 1,
				'fields'        => 'ids',
				'no_found_rows' => true,
			] );
		}

		return [ 'post' => ! empty( $posts ) ? (int) $posts[0] : null ];
	}
}
