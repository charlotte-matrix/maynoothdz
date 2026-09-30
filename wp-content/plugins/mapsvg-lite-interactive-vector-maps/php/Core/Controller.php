<?php

namespace MapSVG;

/**
 * Core Controller class used to implement actual controllers.
 * @package MapSVG
 */
class Controller
{

	/**
	 * Returns a 403 response if the schema has Google Sheets read-only sync enabled.
	 * Returns null when the operation is allowed.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|null
	 */
	protected static function rejectIfGsReadOnly(\WP_REST_Request $request): ?\WP_REST_Response
	{
		$repo = RepositoryFactory::get($request['_collection_name']);
		if ($repo && $repo->schema) {
			$schema = $repo->schema;
			$settings = ImportSettingsService::getForSchema($schema);
			// Block writes when remote CSV auto-refetch is active (one-way pull, source of truth is remote).
			$isRemoteReadOnly = ($settings['gsImportSource'] ?? 'upload') === 'remote'
				&& !empty($settings['gsAutoRefetch'])
				&& ($settings['gsSyncMode'] ?? 'r') !== 'w';
			if ($isRemoteReadOnly) {
				return self::render(['error' => 'This data source is managed by Google Sheets auto-refetch and is read-only.'], 403);
			}
		}
		return null;
	}

	/**
	 * Renders a response.
	 *
	 * @param mixed $data
	 * @param int $status
	 * @param string $output
	 * @param string $template
	 *
	 * @return \WP_REST_Response|void
	 */
	public static function render($data, $status = 200, $output = 'json', $template = '')
	{
		if ($output === 'text' || ($output === 'html' && !$template)) {
			header('Content-Type: text/' . ($output === "text" ? "plain" : "html") . '; charset=utf-8');
			echo $data;
			exit();
		}
		if ($output === 'html' && $template) {
			$reflector = new \ReflectionClass(get_called_class());
			$filename = $reflector->getFileName();
			$dir = dirname($filename);

			$templatePath = $dir . '/templates/' . $template . '.php';



			if (file_exists($templatePath)) {
				extract($data);
				include $templatePath;
			} else {
				echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
				echo '<p>Template not found: ' . esc_html($template) . '</p>';
				echo '</body></html>';
			}
		} else {

			return new \WP_REST_Response(json_decode(wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), $status);
		}
	}
}
