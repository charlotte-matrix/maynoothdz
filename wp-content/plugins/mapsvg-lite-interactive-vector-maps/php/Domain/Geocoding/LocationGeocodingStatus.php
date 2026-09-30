<?php

namespace MapSVG;

/**
 * Per-row location geocoding workflow for object tables (single location field).
 * Stored in location_geocoding_status; default SKIPPED for "not participating".
 */
final class LocationGeocodingStatus
{
	public const FORWARD_CANDIDATE = 1;
	public const REVERSE_CANDIDATE = 2;
	public const DONE              = 3;
	public const FAILED_NO_RETRY   = 4;
	public const FAILED_RETRY      = 5;
	public const SKIPPED           = 6;
}
