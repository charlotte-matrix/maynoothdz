<?php

namespace MapSVG;

/**
 * Manages the state of a background CSV import job using WordPress transients.
 *
 * Transients are stored in wp_options (longtext column) so there is no length
 * limit on the job state JSON — unlike the mapsvg_settings table whose value
 * column is VARCHAR(255).  Jobs expire automatically after 24 hours so
 * abandoned uploads don't linger.
 *
 * Job lifecycle:  pending → processing → complete | failed
 */
class CsvImportJob {

	const TRANSIENT_PREFIX = 'mapsvg_csv_job_';
	const TTL              = DAY_IN_SECONDS;
	const MAX_ERRORS       = 100;

	/**
	 * Create a new job, persist it, and return its unique token.
	 */
	public static function create( array $params ): string {
		$token = wp_generate_password( 24, false );

        $job = array_merge( $params, [
            'token'           => $token,
            'status'          => 'pending',
            'processed'       => 0,
            'needs_geocoding' => false,
            'errors'          => [],
            'error_count'     => 0,
            'started_at'      => time(),
        ] );

		set_transient( self::TRANSIENT_PREFIX . $token, $job, self::TTL );

		return $token;
	}

	/**
	 * Load a job by token. Returns null if not found or expired.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function get( string $token ): ?array {
		$job = get_transient( self::TRANSIENT_PREFIX . $token );
		return is_array( $job ) ? $job : null;
	}

	/**
	 * Merge $data into an existing job and persist it (resets the TTL).
	 */
	public static function update( string $token, array $data ): void {
		$job = self::get( $token );
		if ( $job === null ) {
			return;
		}
		set_transient( self::TRANSIENT_PREFIX . $token, array_merge( $job, $data ), self::TTL );
	}

	/**
	 * Remove the job from storage.
	 */
	public static function delete( string $token ): void {
		delete_transient( self::TRANSIENT_PREFIX . $token );
	}

	/**
	 * Append new errors to the existing list, capped at MAX_ERRORS.
	 * Increments $totalCount by the number of new errors added.
	 *
	 * @param array<array{message: string}> $existing
	 * @param array<array{message: string}> $newErrors
	 * @return array<array{message: string}>
	 */
	public static function mergeErrors( array $existing, array $newErrors, int &$totalCount ): array {
		$totalCount += count( $newErrors );
		return array_slice( array_merge( $existing, $newErrors ), 0, self::MAX_ERRORS );
	}
}
