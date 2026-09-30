<?php

namespace MapSVG;

/**
 * Map class.
 */
class Map extends Model implements \JsonSerializable
{

	public static $slugOne  = 'map';
	public static $slugMany = 'maps';

	/**
	 *  @var ObjectsRepository $objects Custom DB objects
	 **/
	public $objects;

	/**
	 *  @var RegionsRepository $objects Custom DB objects
	 **/
	public $regions;

	/* @var string Map title */
	public $title;

	/* @var array Map options */
	public $options;

	/* @var boolean Map optionsBroken state, when json_decode from DB failed */
	public $optionsBroken;

	/* @var string $svgFilePath Path to SVG file */
	public $svgFilePath;

	/* @var SVGFile $svgFile SVG file object instance */
	private $svgFile;

	/**
	 * @var string $svgFileLastChanged - Timestamp of the last changes in SVG file.
	 * Added as a parameter to the SVG file URL to avoid cache issues
	 */
	public $svgFileLastChanged;

	/**
	 * @var $status - defines if the map should be deleted
	 */
	public $status;

	/**
	 * @var $statusChangedAt - if status is 0 , the map will be deleted after 48 hours from this timestamp
	 */
	public $statusChangedAt;

	/* @var string $version Number of MapSVG version the map was created in */
	public $version = MAPSVG_VERSION;

	/* @var bool $renderJsonWithObjects Defines if the list of child Objects should be included in JSON */
	private $renderJsonWithObjects = false;

	/* @var bool $renderJsonWithRegions Defines if the list of child Regions should be included in JSON */
	private $renderJsonWithRegions = false;

	/* @var bool $renderJsonWithData Defines if Schema should be included in JSON */
	private $renderJsonWithSchema = false;

	public static $updater;



	public function __construct($data)
	{
		parent::__construct($data);

		if (!isset($this->optionsBroken)) {
			$this->setOptionsBroken(false);
		}
		if ($this->optionsBroken) {
			return;
		}

		if (isset($this->options['database']) && isset($this->options['database']['schemas'])) {
			if (!$this->validateSchemasStructure($this->options['database']['schemas'])) {
				// Handle invalid schema structure
				throw new \Exception('Invalid schemas structure');
			}
		}

		if (!isset($this->options['database'])) {
			$this->options['database'] = ['pagination' => ["on" => true, "perpage" => 30]];
		} else {
			if (!isset($this->options['database']['schemas'])) {
				$this->options['database']['schemas'] = [
					"regions" => ["name" => "regions_" . $this->id],
					"objects" => ["name" => "objects_" . $this->id]
				];
			}
		}

		if (!isset($this->status)) {
			$this->setStatus(1);
		}

		// Set up title based on the file name
		if (!isset($this->title) && isset($this->options) && isset($this->options['source'])) {
			if (!isset($this->options['title']) || empty($this->options['title'])) {
				$this->options['title'] = ucwords(str_replace('-', ' ', basename($this->options['source'], '.svg')));
			}
			$this->setTitle($this->options['title']);
		}
	}

	private function validateSchemasStructure($schemas)
	{
		if (!is_array($schemas)) {
			return false;
		}

		foreach ($schemas as $schemaName => $schemaData) {
			if (!is_array($schemaData)) {
				return false;
			}

			if (isset($schemaData['apiEndpoints'])) {
				foreach ($schemaData['apiEndpoints'] as $endpoint) {
					if (!is_array($endpoint) || !isset($endpoint['url']) || !isset($endpoint['method']) || !isset($endpoint['name'])) {
						return false;
					}
				}
			}
		}

		return true;
	}

	public function getRegions()
	{

		if (!$this->regions) {
			if (isset($this->options['database']) && isset($this->options['database']['schemas']) && isset($this->options['database']['schemas']["regions"]) && isset($this->options['database']['schemas']["regions"]["name"])) {

				/**
				 * @var RegionsRepository
				 */
				$this->regions = RepositoryFactory::get($this->options['database']['schemas']["regions"]["name"]);
			} else {
				$regionsTableName = $this->options['database'] && $this->options['database']['regionsTableName'] ? $this->options['database']['regionsTableName'] : "regions_" . $this->id;
				if ($regionsTableName) {
					$this->regions = RepositoryFactory::get($regionsTableName);
				}
			}

			if ($this->regions) {
				if (isset($this->options["regionPrefix"]) && strlen($this->options["regionPrefix"])) {
					$this->regions->setPrefix($this->options["regionPrefix"]);
				}
			}
		}

		return $this->regions;
	}

	public function getObjects()
	{

		if (!$this->objects) {
			if (isset($this->options['database']) && isset($this->options['database']['schemas']) && isset($this->options['database']["schemas"]["objects"]) && isset($this->options['database']["schemas"]["objects"]["name"])) {
				/**
				 * @var ObjectsRepository|RemoteRepository
				 */
				$this->objects = RepositoryFactory::get($this->options['database']['schemas']["objects"]["name"]);
			} else {
				$objectsTableName = isset($this->options['database']) && isset($this->options['database']['objectsTableName']) ? $this->options['database']['objectsTableName'] : "objects_" . $this->id;
				if ($objectsTableName) {
					$this->objects = RepositoryFactory::get($objectsTableName);
				}
			}
		}


		return $this->objects;
	}

	/**
	 * Returns map essential data
	 * @return array
	 */
	public function getData()
	{
		$data = array(
			'id' => $this->id,
			'title' => $this->title,
			'options' => $this->options,
			'svgFilePath' => $this->svgFilePath,
			'svgFileLastChanged' => $this->svgFileLastChanged,
			'version' => $this->version,
			'status' => $this->status,
			'statusChangedAt' => $this->statusChangedAt
		);
		if ($this->optionsBroken) {
			$data['optionsBroken'] = true;
		}
		return $data;
	}
	public function setStatus($status)
	{
		$this->status = $status;
		$this->setStatusChangedAt();
	}

	public function setStatusChangedAt()
	{
		$this->statusChangedAt = gmdate('Y-m-d H:i:s');
	}

	/**
	 * Defines data that should be passed to json_encode()
	 * @return mixed
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize()
	{

		$response = new \stdClass();

		$data = $this->getData();

		if (isset($data['optionsBroken'])) {
			return $data;
		}

		$data['options']['id'] = $this->id;
		$data['options']['version'] = $this->version;

		return $data;
	}

	/**
	 * Defines whether JSON should contain Regions/Objects arrays
	 */
	public function withObjects()
	{
		$this->renderJsonWithObjects = true;
	}

	/**
	 * Defines whether JSON should contain Regions/Objects arrays
	 */
	public function withRegions()
	{
		$this->renderJsonWithRegions = true;
	}

	/**
	 * Defines whether JSON should contain Schema
	 */
	public function withSchema()
	{
		$this->renderJsonWithSchema = true;
	}

	/**
	 * Sets map title
	 * @param string|null $title
	 */
	public function setTitle($title = null)
	{
		$this->title = $title;
	}

	/**
	 * Sets SVG file path and loads its "modified" timestamp
	 * @param string|null $svgFilePath
	 */
	public function setSvgFilePath($svgFilePath = null)
	{
		if ($this->svgFilePath !== $svgFilePath) {
			$this->svgFilePath = $svgFilePath;
			$this->svgFile = new SVGFile(array('relativeUrl' => $this->svgFilePath));
			$this->setSvgFileLastChanged($this->svgFile->lastChanged());
		}
	}

	/**
	 * Sets the timestamp of the last changes in the file
	 * @param string|null $svgFileLastChanged
	 */
	public function setSvgFileLastChanged($svgFileLastChanged = null)
	{
		$this->svgFileLastChanged = (int)$svgFileLastChanged;
	}

	/**
	 * Sets map ID
	 * @param int|string $id
	 */
	public function setId($id = 0)
	{
		$this->id = (int)$id;
	}

	/**
	 * Sets map options broken state
	 * @param $options
	 */
	public function setOptionsBroken($value)
	{
		$this->optionsBroken = $value;
	}
	/**
	 * Sanitize loadingText on write.
	 * Users with unfiltered_html may store limited HTML (no JS); others get plain text.
	 *
	 * @param mixed $text
	 * @return string
	 */
	public static function sanitizeLoadingText($text)
	{
		if (!is_string($text)) {
			return '';
		}
		if (current_user_can('unfiltered_html')) {
			return wp_kses_post($text);
		}
		return sanitize_text_field($text);
	}

	/**
	 * Sanitize loadingText for output / in-memory use (safe HTML, no JS).
	 *
	 * @param mixed $text
	 * @return string
	 */
	public static function sanitizeLoadingTextForOutput($text)
	{
		if (!is_string($text)) {
			return '';
		}
		return wp_kses_post($text);
	}

	/**
	 * Apply write-time sanitization to map options that can contain HTML.
	 *
	 * @param array $options
	 * @return array
	 */
	public static function sanitizeOptionsForWrite(array $options)
	{
		if (isset($options['loadingText'])) {
			$options['loadingText'] = self::sanitizeLoadingText($options['loadingText']);
		}
		return $options;
	}

	/**
	 * Sets map options
	 * @param $options
	 */
	public function setOptions($options)
	{

		if (is_string($options)) {
			try {
				$tryDecode = json_decode($options, true);
				if (!$tryDecode) {
					throw new \Exception("Failed to decode options");
				}
				$options = $tryDecode;
			} catch (\Exception $e) {
				$this->setOptionsBroken(true);
				return;
			}
		}

		if (is_array($options) && isset($options['loadingText'])) {
			$options['loadingText'] = self::sanitizeLoadingTextForOutput($options['loadingText']);
		}

		$this->options = $options;

		if ($this->options && is_array($this->options) && isset($this->options['source'])) {
			$this->setOptionsBroken(false);
			$filePath = isset($this->options['source']) ? $this->options['source'] : '';
			$this->setSvgFilePath($filePath);
		} else {
			$this->setOptionsBroken(true);
			return;
		}
	}

	public static function fixJsonOptions($options)
	{
		if (is_string($options)) {
			$tryDecode = json_decode($options, true);
			if (!$tryDecode) {
				require_once MAPSVG_PLUGIN_DIR . '/php/Core/JsToJson.php';
				$tryDecode = \MapSVG\JsToJson::convertToJson($options);
				$tryDecode = json_decode($tryDecode, true);
			}
			if ($tryDecode) {
				return $tryDecode;
			} else {
				return $options;
			}
		}
		return $options;
	}

	/**
	 * Sets the version number of MapSVG the map was created in
	 * @param $options
	 */
	public function setVersion($version)
	{
		$this->version = $version;
	}


	/**
	 * Parses an SVG file, gets all SVG objects that must be added to the "Regions" table
	 * and updates "regions" table in the database.
	 *
	 * Region IDs present in MySQL but missing from the SVG are marked orphaned=1
	 * (not deleted) so customers can migrate custom field data manually.
	 *
	 * @param string $prefix If prefix is provided, only SVG objects with the provided prefix get into the "Regions" list
	 * @param bool|null $updateTitles
	 * @return array{orphanedMarked: int, orphanedRestored: int, inserted: int, orphanedCount: int}
	 * @throws \Exception
	 */
	function setRegionsTable($prefix = '', $updateTitles = null)
	{

		// Convert related URL of the file to absolute server path
		if (strpos($this->svgFilePath, basename(WP_CONTENT_DIR)) !== false) {
			list($junk, $fileServerPath) = explode(basename(WP_CONTENT_DIR), $this->svgFilePath);
			$fileServerPath = WP_CONTENT_DIR . $fileServerPath;
		} else {
			list($junk, $fileServerPath) = explode(basename(MAPSVG_UPLOADS_DIR), $this->svgFilePath);
			$fileServerPath = MAPSVG_UPLOADS_DIR . $fileServerPath;
		}

		if (file_exists($fileServerPath)) {
			libxml_use_internal_errors(true);
			$svgFile = simplexml_load_file($fileServerPath);
			if ($svgFile === false) {
				$errors = libxml_get_errors();
				libxml_clear_errors();
				$errorMessages = array_map(function ($error) {
					return $error->message;
				}, $errors);
				$errorString = implode(', ', $errorMessages);
				Logger::error("[SERVER-014] Failed to load SVG file: $fileServerPath. Errors: $errorString — Read more: https://mapsvg.com/docs/errors#SERVER-014");
				throw new \Exception('Failed to load SVG file: ' . esc_html($errorString), 500);
			}
		} else {
			Logger::error("[SERVER-015] SVG file does not exist: $fileServerPath — Read more: https://mapsvg.com/docs/errors#SERVER-015");
			throw new \Exception('File does not exist: ' . esc_html($fileServerPath), 404);
		}

		// The following list of SVG objects can be converted to Regions
		$allowed_objects = array(null, 'path', 'ellipse', 'rect', 'circle', 'polygon', 'polyline');

		$namespaces = $svgFile->getDocNamespaces();
		$svgFile->registerXPathNamespace('_ns', $namespaces['']);

		$regions = array();
		$regionIdsFromSvg = array();
		$regionTitlesFromSvg = array();
		$regions_assoc = array();

		$db_types = $this->getRegions()->getSchema()->getFieldTypes();
		$db_types = array_flip($db_types);

		$status_field = isset($db_types['status']);

		// Parse the SVG file, find objects that can be converted to Regions
		while ($obj = next($allowed_objects)) {
			$nodes = $svgFile->xpath('//_ns:' . $obj);

			if (!empty($nodes)) {
				foreach ($nodes as $o) {

					if (isset($o['id']) && !empty($o['id'])) {

						// Skip the node if it's inside of the <defs></defs> tag
						$defs = $svgFile->xpath('//_ns:' . $obj . '[@id="' . $o['id'] . '"]/ancestor::_ns:defs');
						if (!empty($defs)) {
							continue;
						}



						if (!$prefix || ($prefix && strpos($o['id'], $prefix) === 0)) {
							$rid = (string)$o['id'];
							$title              		 = isset($o['title']) && !empty($o['title']) ? (string)$o['title'] : '';
							$regions[]          		 = "('" . esc_sql($rid) . "','" . esc_sql($title) . "'" . ($status_field ? ",1" : "") . ")";
							$region             		 = array('id' => $rid, 'title' => $title);
							$regionsFromSvg[]    	 	 = $region;
							$regionIdsFromSvg[]      = $rid;
							$regionTitlesFromSvg[]   = $title;
							$regions_assoc[$rid]     = $title;
						}
					}
				}
			}
		}

		sort($regionIdsFromSvg);
		sort($regionTitlesFromSvg);

		// Compare SVG regions to DB (including already orphaned rows)
		$regionsRepo = $this->getRegions();
		$resp = $regionsRepo->find(new Query(array(
			'perpage' => 0,
			'filters' => array('includeOrphans' => 1),
		)));
		$regionsFromDb = $resp["items"];
		$regionIdsFromDb = array();
		$regionTitlesFromDb = array();

		foreach ($regionsFromDb as $region) {
			$regionIdsFromDb[] = $region->id;
			$regionTitlesFromDb[] = isset($region->title) ? $region->title : '';
		}

		sort($regionIdsFromDb);
		sort($regionTitlesFromDb);

		$diff = RegionSvgDiff::diff($regionIdsFromDb, $regionIdsFromSvg);

		// DB-only IDs → keep rows, mark as orphaned (no hard delete)
		$newlyMarked = 0;
		if (!empty($diff['toOrphan'])) {
			foreach ($regionsFromDb as $region) {
				if (in_array((string) $region->id, $diff['toOrphan'], true) && empty($region->orphaned)) {
					$newlyMarked++;
				}
			}
			$regionsRepo->setOrphanedByIds($diff['toOrphan'], 1);
		}

		// IDs present in both that were orphaned → clear flag (region returned in SVG)
		$toRestore = array();
		foreach ($regionsFromDb as $region) {
			if (!empty($region->orphaned) && in_array((string) $region->id, $diff['toRestore'], true)) {
				$toRestore[] = (string) $region->id;
			}
		}
		if (!empty($toRestore)) {
			$regionsRepo->setOrphanedByIds($toRestore, 0);
		}

		// SVG-only IDs → insert
		$uniqueSvgRegions = [];
		$uniqueSvgRegionsTitles = [];
		if (!empty($diff['toInsert'])) {
			$regionsFromSvg = isset($regionsFromSvg) ? $regionsFromSvg : array();
			foreach ($regionsFromSvg as $regionFromSvg) {
				if (in_array($regionFromSvg['id'], $diff['toInsert'], true)) {
					$regionFromSvg['orphaned'] = 0;
					$uniqueSvgRegions[] = $regionFromSvg;
					$uniqueSvgRegionsTitles[] = $regionFromSvg['title'];
				}
			}
			if (!empty($uniqueSvgRegions)) {
				$regionsRepo->createOrUpdateAll($uniqueSvgRegions);
			}
		}

		// If some titles have changed in the SVG file then update corresponding Regions in the database
		$currentRegionTitlesFromDb = array_merge($regionTitlesFromDb, $uniqueSvgRegionsTitles);
		sort($currentRegionTitlesFromDb);

		$changedRegionTitles = array_diff($regionTitlesFromSvg, $currentRegionTitlesFromDb);

		if ((!empty($changedRegionTitles)) && ($updateTitles === true)) {
			$changedRegions = [];
			$regionsFromSvg = isset($regionsFromSvg) ? $regionsFromSvg : array();
			foreach ($regionsFromSvg as $regionFromSvg) {
				if (in_array($regionFromSvg['title'], $changedRegionTitles)) {
					$changedRegions[] = $regionFromSvg;
				}
			}

			$regionsRepo->createOrUpdateAll($changedRegions);
		}

		return array(
			'orphanedMarked'   => $newlyMarked,
			'orphanedRestored' => count($toRestore),
			'inserted'         => count($diff['toInsert']),
			'orphanedCount'    => $regionsRepo->countOrphaned(),
		);
	}

	/**
	 * Scans $this->templates for shortcode usages and returns an array of unique shortcode names.
	 * Matches patterns like:
	 *   {{shortcode "[shortcodeName ...]"}}
	 *   {{shortcode_inline '[shortcodeName ...]'}}
	 *   {{shortcode `[...]`}}
	 * Handles single, double, or backtick quotes and variable whitespace.
	 *
	 * @return array
	 */
	public function getShortcodeNamesFromTemplates()
	{
		$shortcodeNames = [];
		if (!isset($this->options['templates']) || !is_array($this->options['templates'])) {
			return $shortcodeNames;
		}
		$pattern = '/\{\{\s*(shortcode|shortcode_inline)\s+([\"\'`])\s*\[\s*([a-zA-Z0-9_\-]+)\b.*?\]\s*\2\s*\}\}/s';
		foreach ($this->options['templates'] as $template) {
			if (!is_string($template)) {
				continue;
			}
			if (preg_match_all($pattern, $template, $matches)) {
				foreach ($matches[3] as $shortcodeName) {
					$shortcodeNames[] = $shortcodeName;
				}
			}
		}
		return array_values(array_unique($shortcodeNames));
	}
}
