<?php

namespace MapSVG;

class SVGFileController extends Controller
{

	/**
	 * Forces generated SVG file download in the browser
	 * @param $request
	 *
	 * @return array|string[]
	 * @throws \Exception
	 */
	public static function download($request)
	{
		// Initialize WP_Filesystem
		if (!function_exists('WP_Filesystem')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();
		global $wp_filesystem;

		if (!$wp_filesystem) {
			wp_send_json_error('Filesystem access not available', 500);
			return;
		}

		$file_path = MAPSVG_UPLOADS_DIR . DIRECTORY_SEPARATOR . "mapsvg.svg";

		if ($wp_filesystem->exists($file_path)) {
			$file_contents = $wp_filesystem->get_contents($file_path);

			if ($file_contents === false) {
				wp_send_json_error('Could not read file', 500);
				return;
			}

			// Set headers for file download
			nocache_headers(); // WordPress function to prevent caching
			header('Content-Type: image/svg+xml');
			header('Content-Disposition: attachment; filename=mapsvg.svg');
			header('Content-Length: ' . strlen($file_contents));

			echo $file_contents;
			exit;
		} else {
			wp_send_json_error('File not found', 404);
		}
	}

	/**
	 * Uploads an SVG file.
	 * @param $request
	 * @return \WP_REST_Response
	 */
	public static function create($request)
	{
		$files = $request->get_file_params();
		$filesRepo = new SVGFileRepository();
		try {
			$file = new SVGFile($files['file']);
		} catch (\Exception $e) {
			return self::render(array(
				'error' => $e->getMessage()
			), 400);
		}
		$file->maybeSanitize(current_user_can('unfiltered_html'));
		$file = $filesRepo->create($file);

		return self::render(array('file' => array(
			'name' => $file->name,
			'relativeUrl' => $file->relativeUrl,
			'pathShort' => $file->pathShort,
			'serverPath' => $file->serverPath
		)));
	}

	/**
	 * Updates an SVG file
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 * @throws \Exception
	 */
	public static function update($request)
	{
		$files = $request->get_file_params();
		$filesRepo = new SVGFileRepository();
		$file = new SVGFile($files['file']);
		$file->maybeSanitize(current_user_can('unfiltered_html'));
		$filesRepo->save($file);

		static::updateLastChanged($file);

		return self::render(array('status' => 'OK'));
	}


	/**
	 * Copies an SVG file
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 * @throws \Exception
	 */
	public static function copy($request)
	{
		$filesRepo = new SVGFileRepository();
		$file = new SVGFile(["relativeUrl" => $request['file']['path']]);
		$file->maybeSanitize(current_user_can('unfiltered_html'));
		$newFile = $filesRepo->copy($file);
		return self::render(array('file' => $newFile));
	}

	/**
	 * Updates "lastChanged" timestamp for all maps created from the provided SVG file.
	 * @param SVGFile $file
	 * @param $updateTitles
	 * @return array{maps: array<int, array>, orphanedMarked: int, orphanedCount: int}
	 */
	private static function updateLastChanged($file, $updateTitles = null): array
	{
		$mapsRepo = RepositoryFactory::get("map");
		$query = new Query(array('filters' => array('svgFilePath' => $file->relativeUrl)));
		$maps = $mapsRepo->find($query);

		$syncResults = array();
		$orphanedMarked = 0;
		$orphanedCount = 0;

		foreach ($maps["items"] as $map) {
			/** @var $map Map */
			$map->update(array('svgFileLastChanged' => $file->lastChanged()));
			$result = $mapsRepo->updateFromSvg($map, $updateTitles);
			if (is_array($result)) {
				$syncResults[] = array_merge(array('mapId' => $map->id), $result);
				$orphanedMarked += (int) ($result['orphanedMarked'] ?? 0);
				$orphanedCount += (int) ($result['orphanedCount'] ?? 0);
			}
		}

		return array(
			'maps'            => $syncResults,
			'orphanedMarked'  => $orphanedMarked,
			'orphanedCount'   => $orphanedCount,
		);
	}

	/**
	 * Updates "lastChanged" timestamp for all maps created from the provided SVG file.
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public static function reload($request)
	{
		$file = new SVGFile($request['file']);
		$updateTitles = $request['updateTitles'] === 'true';
		$regionSync = static::updateLastChanged($file, $updateTitles);
		return self::render(array(
			'file' => $file,
			'regionSync' => $regionSync,
		));
	}
}
