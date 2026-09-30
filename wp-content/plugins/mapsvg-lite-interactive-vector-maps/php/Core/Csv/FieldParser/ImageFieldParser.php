<?php

namespace MapSVG;

/**
 * Parses image fields from CSV.
 *
 * CSV cell may contain a single remote URL or a comma-separated list of URLs.
 * Each URL is:
 *   1. Checked against the WP Media Library by the custom meta key `mapsvg_source_url`
 *      to avoid downloading the same file twice on repeated imports.
 *   2. If not found, downloaded and sideloaded via media_handle_sideload(), which
 *      also generates all registered thumbnail sizes.
 *
 * Result is stored as a JSON array of image objects matching the format produced
 * by the WP media uploader inside MapSVG:
 *   [{"thumbnail":"//…","medium":"//…","full":"//…","sizes":{"thumbnail":{…},…}}]
 */
class ImageFieldParser implements FieldParserInterface {

	public function supports( string $fieldType ): bool {
		return $fieldType === 'image';
	}

	public function parse( string $rawValue, object $field, array &$context = [] ): array {
		if ( '' === trim( $rawValue ) ) {
			return [ $field->name => null ];
		}

		// These helpers are only autoloaded inside wp-admin.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$urls   = array_filter( array_map( 'trim', str_getcsv( $rawValue ) ) );
		$images = [];

		foreach ( $urls as $url ) {
			$imageData = $this->resolveImage( $url );
			if ( $imageData ) {
				$images[] = $imageData;
			}
		}

		if ( empty( $images ) ) {
			return [ $field->name => null ];
		}

		return [ $field->name => wp_json_encode( $images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ];
	}

	/**
	 * Returns the image data array for the given URL, either by reusing an existing
	 * attachment or by sideloading the remote file into the WP Media Library.
	 */
	private function resolveImage( string $url ): ?array {
		// Reuse existing attachment by the original source URL.
		$existing = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_key'       => 'mapsvg_source_url',
			'meta_value'     => $url,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );

		if ( ! empty( $existing ) ) {
			return $this->buildImageData( (int) $existing[0] );
		}

		// Download to a temp file then sideload into wp-content/uploads.
		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return null;
		}

		$filename = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' === $filename ) {
			$filename = 'image';
		}

		$attachment_id = media_handle_sideload(
			[ 'name' => $filename, 'tmp_name' => $tmp ],
			0
		);

		if ( is_wp_error( $attachment_id ) ) {
			// media_handle_sideload() already deleted $tmp on failure, but be safe.
			if ( file_exists( $tmp ) ) {
				@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
			return null;
		}

		update_post_meta( $attachment_id, 'mapsvg_source_url', $url );

		return $this->buildImageData( $attachment_id );
	}

	/**
	 * Builds the MapSVG image data object from a WP attachment ID.
	 * URLs are stored protocol-relative to match the WP media uploader behaviour.
	 *
	 * @return array{thumbnail:string,medium:string,full:string,sizes:array}|null
	 */
	private function buildImageData( int $attachment_id ): ?array {
		$extra_sizes = array_keys( wp_get_additional_image_sizes() );
		$all_sizes   = array_unique( array_merge( [ 'thumbnail', 'medium', 'large', 'full' ], $extra_sizes ) );

		$image = [ 'sizes' => [] ];

		foreach ( $all_sizes as $size ) {
			$src = wp_get_attachment_image_src( $attachment_id, $size );
			if ( ! $src ) {
				continue;
			}
			$protocol_relative_url  = preg_replace( '#^https?:#', '', $src[0] );
			$image[ $size ]          = $protocol_relative_url;
			$image['sizes'][ $size ] = [ 'width' => (int) $src[1], 'height' => (int) $src[2] ];
		}

		if ( empty( $image['full'] ) ) {
			return null;
		}

		// Fall back to 'full' for sizes that weren't generated (e.g. small originals).
		if ( empty( $image['thumbnail'] ) ) {
			$image['thumbnail']          = $image['full'];
			$image['sizes']['thumbnail'] = $image['sizes']['full'];
		}
		if ( empty( $image['medium'] ) ) {
			$image['medium']          = $image['full'];
			$image['sizes']['medium'] = $image['sizes']['full'];
		}

		return $image;
	}
}
