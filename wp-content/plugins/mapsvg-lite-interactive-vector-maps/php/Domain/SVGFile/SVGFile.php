<?php

namespace MapSVG;

use enshrined\svgSanitize\Sanitizer;

class SVGFile extends File
{
	public function __construct($file)
	{
		if (!is_array($file)) {
			throw new \Exception('Invalid file data', 400);
		}

		// Check for path traversal / extension on existing file references
		if (isset($file['relativeUrl'])) {
			$relativePath = $file['relativeUrl'];
			if (strpos($relativePath, '../') !== false || strpos($relativePath, '..\\') !== false) {
				throw new \Exception('Invalid file path: path traversal detected', 400);
			}
			$this->assertSvgFileName(basename((string) $relativePath));
		}

		// Uploads: controller passes the inner $_FILES part (name, tmp_name, type, ...).
		// Also accept a nested ['file' => [...]] shape if ever used.
		if (isset($file['file']) && is_array($file['file'])) {
			$this->assertUploadedSvg($file['file']);
		} elseif (isset($file['tmp_name']) || (isset($file['name']) && array_key_exists('error', $file))) {
			$this->assertUploadedSvg($file);
			// Normalize stored name after checks
			if (isset($file['name'])) {
				$file['name'] = sanitize_file_name((string) $file['name']);
				$this->assertSvgFileName($file['name']);
			}
		}

		parent::__construct($file);
	}

	/**
	 * Validate an uploaded file array (PHP $_FILES item shape).
	 *
	 * @param array $uploadedFile
	 * @throws \Exception
	 */
	private function assertUploadedSvg(array $uploadedFile): void
	{
		$fileName = isset($uploadedFile['name']) ? (string) $uploadedFile['name'] : '';
		$fileName = str_replace("\0", '', $fileName);
		$this->assertSvgFileName(basename($fileName));

		$mimeType = isset($uploadedFile['type']) ? strtolower((string) $uploadedFile['type']) : '';
		if ($mimeType !== '') {
			$allowedMimeTypes = ['image/svg+xml', 'text/xml', 'application/xml'];
			if (!in_array($mimeType, $allowedMimeTypes, true)) {
				throw new \Exception('Invalid file type: only SVG files are allowed', 400);
			}
		}
	}

	/**
	 * Require a safe .svg basename (blocks .php, .htaccess, etc.).
	 *
	 * @param string $fileName
	 * @throws \Exception
	 */
	private function assertSvgFileName(string $fileName): void
	{
		$fileName = str_replace("\0", '', $fileName);
		$base = basename($fileName);
		$baseLower = strtolower($base);

		if ($base === '' || $baseLower === '.htaccess' || $baseLower === 'htaccess') {
			throw new \Exception('Invalid file type: only SVG files are allowed', 400);
		}

		if (strtolower(pathinfo($base, PATHINFO_EXTENSION)) !== 'svg') {
			throw new \Exception('Invalid file type: only SVG files are allowed', 400);
		}
	}

	public function lastChanged()
	{
		if (file_exists($this->serverPath)) {
			return filemtime($this->serverPath);
		} else {
			return 0;
		}
	}

	/**
	 *  Remove all <script>...</script> tags (case-insensitive, multiline, greedy)
	 **/
	public function maybeSanitize($canHaveScripts = false)
	{
		if (!$canHaveScripts) {
			$this->body = self::sanitize($this->body);
		}

		return $this;
	}

	public static function sanitize($body)
	{
		if (isset($body)) {
			$sanitizer = new Sanitizer();
			$body = $sanitizer->sanitize($body);
			if (!$body) {
				throw new \Exception('SVG file sanitization failed', 400);
			}
		}
		return $body;
	}
}
