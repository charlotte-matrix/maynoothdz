<?php
/**
 * Database schema and helpers.
 *
 * @package Matrix_Interactive_SVG_Map
 */

namespace Matrix_Interactive_SVG_Map;

defined( 'ABSPATH' ) || exit;

/**
 * Class Database
 */
class Database {

	/**
	 * Maps table name (with prefix).
	 *
	 * @return string
	 */
	public static function maps_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'misvm_maps';
	}

	/**
	 * Regions table name (with prefix).
	 *
	 * @return string
	 */
	public static function regions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'misvm_regions';
	}

	/**
	 * Create or update tables.
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$maps    = self::maps_table();
		$regions = self::regions_table();

		$sql_maps = "CREATE TABLE {$maps} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL DEFAULT '',
			attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
			default_color varchar(20) NOT NULL DEFAULT '#333333',
			hover_color varchar(20) NOT NULL DEFAULT '#666666',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) {$charset};";

		$sql_regions = "CREATE TABLE {$regions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			map_id bigint(20) unsigned NOT NULL,
			region_key varchar(191) NOT NULL,
			name varchar(255) NOT NULL DEFAULT '',
			url text NULL,
			color varchar(20) NOT NULL DEFAULT '',
			sort_order int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY map_region (map_id, region_key),
			KEY map_id (map_id)
		) {$charset};";

		dbDelta( $sql_maps );
		dbDelta( $sql_regions );
	}

	/**
	 * Get a map by ID.
	 *
	 * @param int $id Map ID.
	 * @return object|null
	 */
	public static function get_map( int $id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::maps_table() . ' WHERE id = %d',
				$id
			)
		);
		return $row ?: null;
	}

	/**
	 * Get all maps.
	 *
	 * @return array<object>
	 */
	public static function get_maps(): array {
		global $wpdb;
		$results = $wpdb->get_results(
			'SELECT * FROM ' . self::maps_table() . ' ORDER BY id DESC'
		);
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Insert a map.
	 *
	 * @param array<string,mixed> $data Map data.
	 * @return int New map ID.
	 */
	public static function insert_map( array $data ): int {
		global $wpdb;
		$wpdb->insert(
			self::maps_table(),
			array(
				'title'          => sanitize_text_field( $data['title'] ?? '' ),
				'attachment_id'  => absint( $data['attachment_id'] ?? 0 ),
				'default_color'  => sanitize_hex_color( $data['default_color'] ?? '#333333' ) ?: '#333333',
				'hover_color'    => sanitize_hex_color( $data['hover_color'] ?? '#666666' ) ?: '#666666',
			),
			array( '%s', '%d', '%s', '%s' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a map.
	 *
	 * @param int                 $id   Map ID.
	 * @param array<string,mixed> $data Map data.
	 * @return bool
	 */
	public static function update_map( int $id, array $data ): bool {
		global $wpdb;
		$fields = array();
		$format = array();

		if ( isset( $data['title'] ) ) {
			$fields['title'] = sanitize_text_field( $data['title'] );
			$format[]        = '%s';
		}
		if ( isset( $data['attachment_id'] ) ) {
			$fields['attachment_id'] = absint( $data['attachment_id'] );
			$format[]                = '%d';
		}
		if ( isset( $data['default_color'] ) ) {
			$fields['default_color'] = sanitize_hex_color( $data['default_color'] ) ?: '#333333';
			$format[]                = '%s';
		}
		if ( isset( $data['hover_color'] ) ) {
			$fields['hover_color'] = sanitize_hex_color( $data['hover_color'] ) ?: '#666666';
			$format[]              = '%s';
		}

		if ( empty( $fields ) ) {
			return false;
		}

		return false !== $wpdb->update( self::maps_table(), $fields, array( 'id' => $id ), $format, array( '%d' ) );
	}

	/**
	 * Delete a map and its regions.
	 *
	 * @param int $id Map ID.
	 * @return bool
	 */
	public static function delete_map( int $id ): bool {
		global $wpdb;
		$wpdb->delete( self::regions_table(), array( 'map_id' => $id ), array( '%d' ) );
		return false !== $wpdb->delete( self::maps_table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Get regions for a map.
	 *
	 * @param int $map_id Map ID.
	 * @return array<object>
	 */
	public static function get_regions( int $map_id ): array {
		global $wpdb;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::regions_table() . ' WHERE map_id = %d ORDER BY sort_order ASC, name ASC',
				$map_id
			)
		);
		return is_array( $results ) ? $results : array();
	}

	/**
	 * Upsert regions for a map (merge by region_key; keep existing name/url/color when re-parsing).
	 *
	 * @param int                  $map_id  Map ID.
	 * @param array<int,array>     $regions Parsed regions: region_key, name, sort_order.
	 * @param bool                 $replace If true, remove regions not in the new list.
	 */
	public static function sync_regions( int $map_id, array $regions, bool $replace = true ): void {
		global $wpdb;

		$existing = array();
		foreach ( self::get_regions( $map_id ) as $row ) {
			$existing[ $row->region_key ] = $row;
		}

		$seen = array();

		foreach ( $regions as $index => $region ) {
			$key = (string) ( $region['region_key'] ?? '' );
			if ( '' === $key ) {
				continue;
			}
			$seen[] = $key;

			$name = isset( $region['name'] ) ? sanitize_text_field( $region['name'] ) : $key;

			if ( isset( $existing[ $key ] ) ) {
				$wpdb->update(
					self::regions_table(),
					array(
						'sort_order' => (int) ( $region['sort_order'] ?? $index ),
						// Only set name from SVG if current name is empty or still equals the key.
						'name'       => ( '' === $existing[ $key ]->name || $existing[ $key ]->name === $key )
							? $name
							: $existing[ $key ]->name,
					),
					array(
						'map_id'     => $map_id,
						'region_key' => $key,
					),
					array( '%d', '%s' ),
					array( '%d', '%s' )
				);
			} else {
				$wpdb->insert(
					self::regions_table(),
					array(
						'map_id'     => $map_id,
						'region_key' => $key,
						'name'       => $name,
						'url'        => '',
						'color'      => '',
						'sort_order' => (int) ( $region['sort_order'] ?? $index ),
					),
					array( '%d', '%s', '%s', '%s', '%s', '%d' )
				);
			}
		}

		if ( $replace ) {
			foreach ( array_keys( $existing ) as $old_key ) {
				if ( ! in_array( $old_key, $seen, true ) ) {
					$wpdb->delete(
						self::regions_table(),
						array(
							'map_id'     => $map_id,
							'region_key' => $old_key,
						),
						array( '%d', '%s' )
					);
				}
			}
		}
	}

	/**
	 * Update a single region.
	 *
	 * @param int                 $id   Region ID.
	 * @param array<string,mixed> $data Region fields.
	 * @return bool
	 */
	public static function update_region( int $id, array $data ): bool {
		global $wpdb;
		$fields = array();
		$format = array();

		if ( array_key_exists( 'name', $data ) ) {
			$fields['name'] = sanitize_text_field( (string) $data['name'] );
			$format[]       = '%s';
		}
		if ( array_key_exists( 'url', $data ) ) {
			$fields['url'] = esc_url_raw( (string) $data['url'] );
			$format[]      = '%s';
		}
		if ( array_key_exists( 'color', $data ) ) {
			$color           = (string) $data['color'];
			$fields['color'] = $color ? ( sanitize_hex_color( $color ) ?: '' ) : '';
			$format[]        = '%s';
		}

		if ( empty( $fields ) ) {
			return false;
		}

		return false !== $wpdb->update( self::regions_table(), $fields, array( 'id' => $id ), $format, array( '%d' ) );
	}

	/**
	 * Bulk-update regions from admin form.
	 *
	 * @param int                          $map_id  Map ID.
	 * @param array<int,array<string,mixed>> $rows Region rows keyed by region ID.
	 */
	public static function update_regions_bulk( int $map_id, array $rows ): void {
		foreach ( $rows as $region_id => $row ) {
			$region_id = absint( $region_id );
			if ( ! $region_id ) {
				continue;
			}
			// Ensure region belongs to this map.
			global $wpdb;
			$belongs = (int) $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM ' . self::regions_table() . ' WHERE id = %d AND map_id = %d',
					$region_id,
					$map_id
				)
			);
			if ( ! $belongs ) {
				continue;
			}
			self::update_region(
				$region_id,
				array(
					'name'  => $row['name'] ?? '',
					'url'   => $row['url'] ?? '',
					'color' => $row['color'] ?? '',
				)
			);
		}
	}
}
