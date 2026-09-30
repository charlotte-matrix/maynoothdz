<?php
/**
 * Frontend shortcode.
 *
 * @package Matrix_Interactive_SVG_Map
 */

namespace Matrix_Interactive_SVG_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Class Shortcode
 */
class Shortcode {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Whether assets were enqueued.
	 *
	 * @var bool
	 */
	private $assets_enqueued = false;

	/**
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register shortcodes.
	 */
	public function hooks(): void {
		add_shortcode( 'svg_map', array( $this, 'render' ) );
		add_shortcode( 'matrix_svg_map', array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string,string>|string $atts Attributes.
	 * @return string
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'class' => '',
			),
			$atts,
			'svg_map'
		);

		$map_id = absint( $atts['id'] );
		if ( ! $map_id ) {
			return '';
		}

		$map = Database::get_map( $map_id );
		if ( ! $map || ! $map->attachment_id ) {
			return current_user_can( 'manage_options' )
				? '<!-- misvm: map not found -->'
				: '';
		}

		$svg = Svg_Parser::read_attachment( (int) $map->attachment_id );
		if ( '' === $svg ) {
			return '';
		}

		$regions = Database::get_regions( $map_id );
		$markup  = Svg_Parser::prepare_for_frontend( $svg, $regions, $map->default_color );
		if ( '' === $markup ) {
			return '';
		}

		$this->enqueue_assets();

		$classes = array( 'misvm-map' );
		if ( $atts['class'] ) {
			$classes[] = sanitize_html_class( $atts['class'] );
		}

		ob_start();
		?>
		<div
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			data-misvm-id="<?php echo (int) $map_id; ?>"
			data-misvm-hover="<?php echo esc_attr( $map->hover_color ); ?>"
			style="--misvm-hover-color: <?php echo esc_attr( $map->hover_color ); ?>;"
		>
			<div class="misvm-map__canvas">
				<?php
				// Sanitized SVG with injected data attributes.
				echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</div>
			<div class="misvm-tooltip" hidden role="tooltip"></div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Enqueue front-end CSS/JS once.
	 */
	private function enqueue_assets(): void {
		if ( $this->assets_enqueued ) {
			return;
		}
		$this->assets_enqueued = true;

		wp_enqueue_style(
			'misvm-frontend',
			MISVM_URL . 'assets/css/frontend.css',
			array(),
			MISVM_VERSION
		);
		wp_enqueue_script(
			'misvm-frontend',
			MISVM_URL . 'assets/js/frontend.js',
			array(),
			MISVM_VERSION,
			true
		);
	}
}
