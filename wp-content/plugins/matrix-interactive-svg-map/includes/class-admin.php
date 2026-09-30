<?php
/**
 * Admin screens for SVG maps.
 *
 * @package Matrix_Interactive_SVG_Map
 */

namespace Matrix_Interactive_SVG_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin
 */
class Admin {

	/**
	 * Singleton.
	 *
	 * @var self|null
	 */
	private static $instance = null;

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
	 * Register hooks.
	 */
	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_misvm_save_map', array( $this, 'handle_save_map' ) );
		add_action( 'admin_post_misvm_delete_map', array( $this, 'handle_delete_map' ) );
		add_action( 'admin_post_misvm_save_regions', array( $this, 'handle_save_regions' ) );
		add_action( 'admin_post_misvm_reparse_svg', array( $this, 'handle_reparse_svg' ) );
	}

	/**
	 * Admin menu.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'SVG Maps', 'matrix-interactive-svg-map' ),
			__( 'SVG Maps', 'matrix-interactive-svg-map' ),
			'manage_options',
			'misvm-maps',
			array( $this, 'render_page' ),
			'dashicons-location-alt',
			58
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function enqueue( string $hook ): void {
		if ( false === strpos( $hook, 'misvm-maps' ) ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'misvm-admin',
			MISVM_URL . 'assets/css/admin.css',
			array(),
			MISVM_VERSION
		);
		wp_enqueue_script(
			'misvm-admin',
			MISVM_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			MISVM_VERSION,
			true
		);
	}

	/**
	 * Route admin views.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'matrix-interactive-svg-map' ) );
		}

		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id     = isset( $_GET['map_id'] ) ? absint( $_GET['map_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap misvm-admin">';

		if ( isset( $_GET['misvm_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notice = sanitize_key( wp_unslash( $_GET['misvm_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$messages = array(
				'saved'   => __( 'Map saved.', 'matrix-interactive-svg-map' ),
				'regions' => __( 'Regions updated.', 'matrix-interactive-svg-map' ),
				'deleted' => __( 'Map deleted.', 'matrix-interactive-svg-map' ),
				'reparsed'=> __( 'SVG re-parsed; regions synced.', 'matrix-interactive-svg-map' ),
				'error'   => __( 'Something went wrong. Please try again.', 'matrix-interactive-svg-map' ),
			);
			if ( isset( $messages[ $notice ] ) ) {
				$class = 'error' === $notice ? 'notice-error' : 'notice-success';
				printf(
					'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
					esc_attr( $class ),
					esc_html( $messages[ $notice ] )
				);
			}
		}

		switch ( $action ) {
			case 'edit':
			case 'new':
				$this->render_edit( $id );
				break;
			case 'regions':
				$this->render_regions( $id );
				break;
			default:
				$this->render_list();
				break;
		}

		echo '</div>';
	}

	/**
	 * Maps list view.
	 */
	private function render_list(): void {
		$maps = Database::get_maps();
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'SVG Maps', 'matrix-interactive-svg-map' ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=misvm-maps&action=new' ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New', 'matrix-interactive-svg-map' ); ?>
		</a>
		<hr class="wp-header-end" />

		<p class="description">
			<?php esc_html_e( 'Upload an SVG with one path (or shape) per region. Use the shortcode shown below to embed a map anywhere.', 'matrix-interactive-svg-map' ); ?>
		</p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'matrix-interactive-svg-map' ); ?></th>
					<th><?php esc_html_e( 'Title', 'matrix-interactive-svg-map' ); ?></th>
					<th><?php esc_html_e( 'Regions', 'matrix-interactive-svg-map' ); ?></th>
					<th><?php esc_html_e( 'Shortcode', 'matrix-interactive-svg-map' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'matrix-interactive-svg-map' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $maps ) ) : ?>
				<tr>
					<td colspan="5"><?php esc_html_e( 'No maps yet. Click “Add New” to upload an SVG.', 'matrix-interactive-svg-map' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $maps as $map ) : ?>
					<?php $count = count( Database::get_regions( (int) $map->id ) ); ?>
					<tr>
						<td><?php echo (int) $map->id; ?></td>
						<td>
							<strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=misvm-maps&action=edit&map_id=' . (int) $map->id ) ); ?>">
									<?php echo esc_html( $map->title ); ?>
								</a>
							</strong>
						</td>
						<td><?php echo (int) $count; ?></td>
						<td><code>[svg_map id="<?php echo (int) $map->id; ?>"]</code></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=misvm-maps&action=edit&map_id=' . (int) $map->id ) ); ?>">
								<?php esc_html_e( 'Edit', 'matrix-interactive-svg-map' ); ?>
							</a>
							|
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=misvm-maps&action=regions&map_id=' . (int) $map->id ) ); ?>">
								<?php esc_html_e( 'Regions', 'matrix-interactive-svg-map' ); ?>
							</a>
							|
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=misvm_delete_map&map_id=' . (int) $map->id ), 'misvm_delete_map_' . (int) $map->id ) ); ?>"
								onclick="return confirm('<?php echo esc_js( __( 'Delete this map and all its region settings?', 'matrix-interactive-svg-map' ) ); ?>');"
								class="misvm-danger">
								<?php esc_html_e( 'Delete', 'matrix-interactive-svg-map' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Create / edit map form.
	 *
	 * @param int $id Map ID (0 = new).
	 */
	private function render_edit( int $id ): void {
		$map = $id ? Database::get_map( $id ) : null;
		if ( $id && ! $map ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Map not found.', 'matrix-interactive-svg-map' ) . '</p></div>';
			return;
		}

		$title         = $map ? $map->title : '';
		$attachment_id = $map ? (int) $map->attachment_id : 0;
		$default_color = $map ? $map->default_color : '#333333';
		$hover_color   = $map ? $map->hover_color : '#c8102e';
		$preview_url   = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
		?>
		<h1>
			<?php
			echo $map
				? esc_html__( 'Edit Map', 'matrix-interactive-svg-map' )
				: esc_html__( 'Add New Map', 'matrix-interactive-svg-map' );
			?>
		</h1>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="misvm-form">
			<input type="hidden" name="action" value="misvm_save_map" />
			<input type="hidden" name="map_id" value="<?php echo (int) $id; ?>" />
			<?php wp_nonce_field( 'misvm_save_map', 'misvm_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th><label for="misvm_title"><?php esc_html_e( 'Title', 'matrix-interactive-svg-map' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="misvm_title" name="title" value="<?php echo esc_attr( $title ); ?>" required />
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'SVG file', 'matrix-interactive-svg-map' ); ?></th>
					<td>
						<input type="hidden" id="misvm_attachment_id" name="attachment_id" value="<?php echo (int) $attachment_id; ?>" />
						<button type="button" class="button" id="misvm_upload_btn">
							<?php esc_html_e( 'Select / Upload SVG', 'matrix-interactive-svg-map' ); ?>
						</button>
						<button type="button" class="button" id="misvm_clear_btn" <?php disabled( ! $attachment_id ); ?>>
							<?php esc_html_e( 'Clear', 'matrix-interactive-svg-map' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Each region should be a separate shape with an id (and optionally a title attribute). Example: France départements/regions SVG.', 'matrix-interactive-svg-map' ); ?>
						</p>
						<div id="misvm_preview" class="misvm-preview">
							<?php if ( $preview_url ) : ?>
								<img src="<?php echo esc_url( $preview_url ); ?>" alt="" />
								<p class="description"><?php echo esc_html( basename( (string) get_attached_file( $attachment_id ) ) ); ?></p>
							<?php endif; ?>
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="misvm_default_color"><?php esc_html_e( 'Default region color', 'matrix-interactive-svg-map' ); ?></label></th>
					<td>
						<input type="color" id="misvm_default_color" name="default_color" value="<?php echo esc_attr( $default_color ); ?>" />
						<p class="description"><?php esc_html_e( 'Used when a region has no custom color.', 'matrix-interactive-svg-map' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="misvm_hover_color"><?php esc_html_e( 'Hover color', 'matrix-interactive-svg-map' ); ?></label></th>
					<td>
						<input type="color" id="misvm_hover_color" name="hover_color" value="<?php echo esc_attr( $hover_color ); ?>" />
						<p class="description"><?php esc_html_e( 'Applied only to regions that have a URL.', 'matrix-interactive-svg-map' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( $map ? __( 'Save Map', 'matrix-interactive-svg-map' ) : __( 'Create Map', 'matrix-interactive-svg-map' ) ); ?>

			<?php if ( $map ) : ?>
				<p>
					<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=misvm-maps&action=regions&map_id=' . (int) $map->id ) ); ?>">
						<?php esc_html_e( 'Edit regions →', 'matrix-interactive-svg-map' ); ?>
					</a>
					<span class="description" style="margin-left:8px;">
						<?php
						printf(
							/* translators: %d: map id */
							esc_html__( 'Shortcode: [svg_map id="%d"]', 'matrix-interactive-svg-map' ),
							(int) $map->id
						);
						?>
					</span>
				</p>
			<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * Regions editor.
	 *
	 * @param int $id Map ID.
	 */
	private function render_regions( int $id ): void {
		$map = Database::get_map( $id );
		if ( ! $map ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Map not found.', 'matrix-interactive-svg-map' ) . '</p></div>';
			return;
		}

		$regions = Database::get_regions( $id );
		?>
		<h1>
			<?php
			printf(
				/* translators: %s: map title */
				esc_html__( 'Regions — %s', 'matrix-interactive-svg-map' ),
				esc_html( $map->title )
			);
			?>
		</h1>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=misvm-maps&action=edit&map_id=' . $id ) ); ?>">
				&larr; <?php esc_html_e( 'Back to map settings', 'matrix-interactive-svg-map' ); ?>
			</a>
			|
			<code>[svg_map id="<?php echo (int) $id; ?>"]</code>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
			<input type="hidden" name="action" value="misvm_reparse_svg" />
			<input type="hidden" name="map_id" value="<?php echo (int) $id; ?>" />
			<?php wp_nonce_field( 'misvm_reparse_svg_' . $id, 'misvm_nonce' ); ?>
			<?php submit_button( __( 'Re-parse SVG', 'matrix-interactive-svg-map' ), 'secondary', 'submit', false ); ?>
		</form>

		<?php if ( empty( $regions ) ) : ?>
			<div class="notice notice-warning"><p>
				<?php esc_html_e( 'No regions found. Make sure the SVG has shapes with id attributes, then re-parse.', 'matrix-interactive-svg-map' ); ?>
			</p></div>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="misvm-form">
				<input type="hidden" name="action" value="misvm_save_regions" />
				<input type="hidden" name="map_id" value="<?php echo (int) $id; ?>" />
				<?php wp_nonce_field( 'misvm_save_regions_' . $id, 'misvm_nonce' ); ?>

				<table class="wp-list-table widefat fixed striped misvm-regions-table">
					<thead>
						<tr>
							<th style="width:12%;"><?php esc_html_e( 'SVG ID', 'matrix-interactive-svg-map' ); ?></th>
							<th style="width:28%;"><?php esc_html_e( 'Name', 'matrix-interactive-svg-map' ); ?></th>
							<th style="width:40%;"><?php esc_html_e( 'Link URL', 'matrix-interactive-svg-map' ); ?></th>
							<th style="width:12%;"><?php esc_html_e( 'Color', 'matrix-interactive-svg-map' ); ?></th>
							<th style="width:8%;"><?php esc_html_e( 'Reset', 'matrix-interactive-svg-map' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $regions as $region ) : ?>
						<tr>
							<td><code><?php echo esc_html( $region->region_key ); ?></code></td>
							<td>
								<input type="text"
									name="regions[<?php echo (int) $region->id; ?>][name]"
									value="<?php echo esc_attr( $region->name ); ?>"
									class="widefat" />
							</td>
							<td>
								<input type="url"
									name="regions[<?php echo (int) $region->id; ?>][url]"
									value="<?php echo esc_attr( $region->url ); ?>"
									class="widefat"
									placeholder="https://" />
							</td>
							<td>
								<input type="color"
									class="misvm-region-color"
									name="regions[<?php echo (int) $region->id; ?>][color]"
									value="<?php echo esc_attr( $region->color ?: $map->default_color ); ?>"
									data-default="<?php echo esc_attr( $map->default_color ); ?>"
									<?php echo $region->color ? '' : 'data-unset="1"'; ?> />
								<input type="hidden"
									class="misvm-region-color-flag"
									name="regions[<?php echo (int) $region->id; ?>][color_custom]"
									value="<?php echo $region->color ? '1' : '0'; ?>" />
							</td>
							<td>
								<button type="button" class="button-link misvm-reset-color">
									<?php esc_html_e( 'Default', 'matrix-interactive-svg-map' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p class="description">
					<?php esc_html_e( 'Regions without a URL are not clickable and will not show a hover effect on the front end. Leave color on “Default” to use the map’s default color.', 'matrix-interactive-svg-map' ); ?>
				</p>

				<?php submit_button( __( 'Save Regions', 'matrix-interactive-svg-map' ) ); ?>
			</form>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save map handler.
	 */
	public function handle_save_map(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'matrix-interactive-svg-map' ) );
		}
		check_admin_referer( 'misvm_save_map', 'misvm_nonce' );

		$map_id        = isset( $_POST['map_id'] ) ? absint( $_POST['map_id'] ) : 0;
		$title         = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		$default_color = isset( $_POST['default_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['default_color'] ) ) : '#333333';
		$hover_color   = isset( $_POST['hover_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['hover_color'] ) ) : '#666666';

		if ( ! $title || ! $attachment_id ) {
			$this->redirect( 'error' );
		}

		$mime = get_post_mime_type( $attachment_id );
		if ( 'image/svg+xml' !== $mime && 'image/svg' !== $mime ) {
			// Also accept by extension if mime is wrong.
			$file = get_attached_file( $attachment_id );
			if ( ! $file || 'svg' !== strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ) {
				$this->redirect( 'error' );
			}
		}

		$data = array(
			'title'         => $title,
			'attachment_id' => $attachment_id,
			'default_color' => $default_color ?: '#333333',
			'hover_color'   => $hover_color ?: '#666666',
		);

		$is_new = ! $map_id;
		$old    = $map_id ? Database::get_map( $map_id ) : null;

		if ( $map_id && $old ) {
			Database::update_map( $map_id, $data );
		} else {
			$map_id = Database::insert_map( $data );
		}

		if ( ! $map_id ) {
			$this->redirect( 'error' );
		}

		// Parse regions when new, or when SVG attachment changed.
		if ( $is_new || ! $old || (int) $old->attachment_id !== $attachment_id ) {
			$this->parse_and_sync( $map_id, $attachment_id );
		}

		wp_safe_redirect(
			admin_url( 'admin.php?page=misvm-maps&action=regions&map_id=' . $map_id . '&misvm_notice=saved' )
		);
		exit;
	}

	/**
	 * Delete map.
	 */
	public function handle_delete_map(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'matrix-interactive-svg-map' ) );
		}
		$map_id = isset( $_GET['map_id'] ) ? absint( $_GET['map_id'] ) : 0;
		check_admin_referer( 'misvm_delete_map_' . $map_id );

		if ( $map_id ) {
			Database::delete_map( $map_id );
		}

		$this->redirect( 'deleted' );
	}

	/**
	 * Save regions.
	 */
	public function handle_save_regions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'matrix-interactive-svg-map' ) );
		}
		$map_id = isset( $_POST['map_id'] ) ? absint( $_POST['map_id'] ) : 0;
		check_admin_referer( 'misvm_save_regions_' . $map_id, 'misvm_nonce' );

		$map = Database::get_map( $map_id );
		if ( ! $map ) {
			$this->redirect( 'error' );
		}

		$raw = isset( $_POST['regions'] ) && is_array( $_POST['regions'] ) ? wp_unslash( $_POST['regions'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rows = array();

		foreach ( $raw as $region_id => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$custom = ! empty( $row['color_custom'] );
			$color  = $custom && ! empty( $row['color'] ) ? $row['color'] : '';
			$rows[ absint( $region_id ) ] = array(
				'name'  => isset( $row['name'] ) ? $row['name'] : '',
				'url'   => isset( $row['url'] ) ? $row['url'] : '',
				'color' => $color,
			);
		}

		Database::update_regions_bulk( $map_id, $rows );

		wp_safe_redirect(
			admin_url( 'admin.php?page=misvm-maps&action=regions&map_id=' . $map_id . '&misvm_notice=regions' )
		);
		exit;
	}

	/**
	 * Re-parse SVG for a map.
	 */
	public function handle_reparse_svg(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'matrix-interactive-svg-map' ) );
		}
		$map_id = isset( $_POST['map_id'] ) ? absint( $_POST['map_id'] ) : 0;
		check_admin_referer( 'misvm_reparse_svg_' . $map_id, 'misvm_nonce' );

		$map = Database::get_map( $map_id );
		if ( ! $map || ! $map->attachment_id ) {
			$this->redirect( 'error' );
		}

		$this->parse_and_sync( $map_id, (int) $map->attachment_id );

		wp_safe_redirect(
			admin_url( 'admin.php?page=misvm-maps&action=regions&map_id=' . $map_id . '&misvm_notice=reparsed' )
		);
		exit;
	}

	/**
	 * Parse SVG attachment and sync regions.
	 *
	 * @param int $map_id        Map ID.
	 * @param int $attachment_id Attachment ID.
	 */
	private function parse_and_sync( int $map_id, int $attachment_id ): void {
		$svg     = Svg_Parser::read_attachment( $attachment_id );
		$clean   = Svg_Parser::sanitize( $svg );
		$regions = Svg_Parser::extract_regions( $clean ?: $svg );
		Database::sync_regions( $map_id, $regions, true );

		// Optionally rewrite sanitized SVG back to the file.
		if ( $clean && $clean !== $svg ) {
			$path = get_attached_file( $attachment_id );
			if ( $path && is_writable( $path ) ) {
				file_put_contents( $path, $clean ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
	}

	/**
	 * Redirect to list with notice.
	 *
	 * @param string $notice Notice key.
	 */
	private function redirect( string $notice ): void {
		wp_safe_redirect( admin_url( 'admin.php?page=misvm-maps&misvm_notice=' . rawurlencode( $notice ) ) );
		exit;
	}
}
