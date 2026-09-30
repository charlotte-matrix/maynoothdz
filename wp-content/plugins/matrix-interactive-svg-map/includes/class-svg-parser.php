<?php
/**
 * SVG parsing and sanitization.
 *
 * @package Matrix_Interactive_SVG_Map
 */

namespace Matrix_Interactive_SVG_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Class Svg_Parser
 */
class Svg_Parser {

	/**
	 * Elements considered region shapes.
	 *
	 * @var string[]
	 */
	private const SHAPE_TAGS = array( 'path', 'polygon', 'polyline', 'rect', 'circle', 'ellipse', 'g' );

	/**
	 * Extract region candidates from SVG markup.
	 *
	 * Prefers elements with an id. Uses title / data-name / inkscape:label when present.
	 *
	 * @param string $svg SVG markup.
	 * @return array<int,array{region_key:string,name:string,sort_order:int}>
	 */
	public static function extract_regions( string $svg ): array {
		$svg = self::strip_xml_declaration( $svg );
		if ( '' === trim( $svg ) ) {
			return array();
		}

		$previous = libxml_use_internal_errors( true );
		$dom      = new \DOMDocument();
		$loaded   = $dom->loadXML( $svg, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return array();
		}

		$xpath = new \DOMXPath( $dom );
		$xpath->registerNamespace( 'svg', 'http://www.w3.org/2000/svg' );

		$nodes = $xpath->query( '//*[@id]' );
		if ( ! $nodes ) {
			return array();
		}

		$regions = array();
		$order   = 0;
		$seen    = array();

		foreach ( $nodes as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}

			$tag = strtolower( $node->localName ?: $node->tagName );
			// Skip the root <svg> and non-shape wrappers that aren't useful as regions.
			if ( 'svg' === $tag || 'defs' === $tag || 'clippath' === $tag || 'mask' === $tag || 'style' === $tag || 'script' === $tag ) {
				continue;
			}

			if ( ! in_array( $tag, self::SHAPE_TAGS, true ) ) {
				continue;
			}

			// Skip groups that only wrap other id'd shapes (prefer leaf shapes).
			if ( 'g' === $tag && self::group_has_shape_descendants_with_id( $node ) ) {
				continue;
			}

			$id = trim( $node->getAttribute( 'id' ) );
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				continue;
			}

			// Skip technical / decorative ids.
			if ( preg_match( '/^(layer|svg|defs|clip|mask|gradient|filter)/i', $id ) ) {
				continue;
			}

			$name = self::resolve_name( $node, $id );
			$seen[ $id ] = true;

			$regions[] = array(
				'region_key' => $id,
				'name'       => $name,
				'sort_order' => $order++,
			);
		}

		return $regions;
	}

	/**
	 * Whether a <g> contains descendant shapes that already have ids.
	 *
	 * @param \DOMElement $group Group element.
	 * @return bool
	 */
	private static function group_has_shape_descendants_with_id( \DOMElement $group ): bool {
		foreach ( $group->getElementsByTagName( '*' ) as $child ) {
			if ( ! $child instanceof \DOMElement || $child === $group ) {
				continue;
			}
			$tag = strtolower( $child->localName ?: $child->tagName );
			if ( in_array( $tag, array( 'path', 'polygon', 'polyline', 'rect', 'circle', 'ellipse' ), true )
				&& $child->hasAttribute( 'id' )
			) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resolve a human-readable name for a region node.
	 *
	 * @param \DOMElement $node Element.
	 * @param string      $id   Fallback id.
	 * @return string
	 */
	private static function resolve_name( \DOMElement $node, string $id ): string {
		$candidates = array(
			$node->getAttribute( 'title' ),
			$node->getAttribute( 'data-name' ),
			$node->getAttribute( 'data-title' ),
			$node->getAttributeNS( 'http://www.inkscape.org/namespaces/inkscape', 'label' ),
		);

		foreach ( $candidates as $candidate ) {
			$candidate = trim( (string) $candidate );
			if ( '' !== $candidate ) {
				return $candidate;
			}
		}

		// Child <title> element.
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof \DOMElement && 'title' === strtolower( $child->localName ?: $child->tagName ) ) {
				$text = trim( $child->textContent ?? '' );
				if ( '' !== $text ) {
					return $text;
				}
			}
		}

		return $id;
	}

	/**
	 * Sanitize SVG for storage/display: strip scripts and event handlers.
	 *
	 * @param string $svg Raw SVG.
	 * @return string
	 */
	public static function sanitize( string $svg ): string {
		$svg = self::strip_xml_declaration( $svg );

		$previous = libxml_use_internal_errors( true );
		$dom      = new \DOMDocument();
		$loaded   = $dom->loadXML( $svg, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return '';
		}

		$xpath = new \DOMXPath( $dom );

		foreach ( array( 'script', 'foreignObject' ) as $dangerous ) {
			$nodes = $xpath->query( '//*[local-name()="' . $dangerous . '"]' );
			if ( $nodes ) {
				foreach ( iterator_to_array( $nodes ) as $node ) {
					if ( $node->parentNode ) {
						$node->parentNode->removeChild( $node );
					}
				}
			}
		}

		$all = $xpath->query( '//*' );
		if ( $all ) {
			foreach ( $all as $node ) {
				if ( ! $node instanceof \DOMElement ) {
					continue;
				}
				$remove = array();
				foreach ( $node->attributes as $attr ) {
					$name = strtolower( $attr->name );
					if ( 0 === strpos( $name, 'on' ) || 'xlink:href' === $name && preg_match( '/^\s*javascript:/i', $attr->value ) ) {
						$remove[] = $attr->name;
					}
				}
				foreach ( $remove as $attr_name ) {
					$node->removeAttribute( $attr_name );
				}
			}
		}

		$out = $dom->saveXML( $dom->documentElement );
		return is_string( $out ) ? $out : '';
	}

	/**
	 * Inject region data attributes and fill colors into SVG markup.
	 *
	 * @param string               $svg     SVG markup.
	 * @param array<object>        $regions Region rows from DB.
	 * @param string               $default Default fill color.
	 * @return string
	 */
	public static function prepare_for_frontend( string $svg, array $regions, string $default ): string {
		$svg = self::sanitize( $svg );
		if ( '' === $svg ) {
			return '';
		}

		$by_key = array();
		foreach ( $regions as $region ) {
			$by_key[ $region->region_key ] = $region;
		}

		$previous = libxml_use_internal_errors( true );
		$dom      = new \DOMDocument();
		$loaded   = $dom->loadXML( $svg, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return '';
		}

		$xpath = new \DOMXPath( $dom );
		$nodes = $xpath->query( '//*[@id]' );

		if ( $nodes ) {
			foreach ( $nodes as $node ) {
				if ( ! $node instanceof \DOMElement ) {
					continue;
				}
				$id = $node->getAttribute( 'id' );
				if ( ! isset( $by_key[ $id ] ) ) {
					continue;
				}

				$region = $by_key[ $id ];
				$url    = trim( (string) $region->url );
				$name   = (string) $region->name;
				$color  = $region->color ? (string) $region->color : $default;

				$node->setAttribute( 'fill', $color );
				$node->setAttribute( 'data-misvm-key', $id );
				$node->setAttribute( 'data-misvm-name', $name );

				if ( $url ) {
					$node->setAttribute( 'data-misvm-url', $url );
					$node->setAttribute( 'class', trim( $node->getAttribute( 'class' ) . ' misvm-region misvm-region--linked' ) );
					$node->setAttribute( 'tabindex', '0' );
					$node->setAttribute( 'role', 'link' );
					$node->setAttribute( 'aria-label', $name );
				} else {
					$node->setAttribute( 'class', trim( $node->getAttribute( 'class' ) . ' misvm-region' ) );
					// Ensure no leftover interactive attrs.
					$node->removeAttribute( 'data-misvm-url' );
				}
			}
		}

		$root = $dom->documentElement;
		if ( $root ) {
			$root->setAttribute( 'class', trim( $root->getAttribute( 'class' ) . ' misvm-svg' ) );
			if ( ! $root->hasAttribute( 'role' ) ) {
				$root->setAttribute( 'role', 'img' );
			}
		}

		$out = $dom->saveXML( $dom->documentElement );
		return is_string( $out ) ? $out : '';
	}

	/**
	 * Remove XML declaration / DOCTYPE noise that confuses browsers inline.
	 *
	 * @param string $svg SVG string.
	 * @return string
	 */
	private static function strip_xml_declaration( string $svg ): string {
		$svg = preg_replace( '/<\?xml[^>]*\?>/i', '', $svg ) ?? $svg;
		$svg = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $svg ) ?? $svg;
		// Handle HTML-encoded declaration from some exports.
		$svg = preg_replace( '/<!--\?xml[^>]*\?-->/i', '', $svg ) ?? $svg;
		return trim( $svg );
	}

	/**
	 * Read SVG contents from an attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public static function read_attachment( int $attachment_id ): string {
		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			return '';
		}
		$contents = file_get_contents( $path );
		return is_string( $contents ) ? $contents : '';
	}
}
