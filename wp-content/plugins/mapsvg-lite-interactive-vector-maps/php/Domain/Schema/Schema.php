<?php

namespace MapSVG;

/**
 * Class that stores information about custom table structure.
 * @package MapSVG
 */
class Schema extends Model
{

	public static $slugOne  = 'schema';
	public static $slugMany = 'schemas';

	/**
	 * @var string
	 */
	public $type;

	/**
	 * @var string|number
	 */
	public $id;
	/**
	 * @var string
	 */
	public $name;
	/**
	 * @var string
	 */
	public $objectNameSingular;
	/**
	 * @var string
	 */
	public $objectNamePlural;

	/**
	 * @var array
	 */
	public $apiEndpoints;
	/**
	 * @var boolean
	 */
	public $remote;
	/**
	 * @var object | null
	 */
	public $apiAuthorization;
	/**
	 * @var string | null
	 */
	public $apiBaseUrl;

	/**
	 * @var string
	 */
	public $title;
	public $fields = array();

	/** @var string | null */
	public $postType;

	/** @var string Primary key column name, defaults to 'id' */
	public $primaryKeyField = 'id';

	private $prevFields = array();

	public function __construct($data)
	{
		$data = (array)$data;
		parent::__construct($data);
		if (!isset($data["type"])) {
			$name = static::getTypeByName($data["name"]);
			$this->setType($name);
		}
	}

	public static function getTypeByName($name)
	{
		if (strpos($name, "region") === 0) {
			$schemaType = "region";
		} elseif (strpos($name, "object") === 0) {
			$schemaType = "object";
		} elseif ($name === "post") {
			$schemaType = "post";
		} elseif ($name === "postType") {
			$schemaType = "postType";
		} elseif (strpos($name, "schema") === 0) {
			$schemaType = "schema";
		} elseif (strpos($name, "map") === 0) {
			$schemaType = "map";
		} elseif (strpos($name, "token") === 0) {
			$schemaType = "token";
		} elseif (strpos($name, "import_settings") === 0) {
			$schemaType = "importSettings";
		} elseif (strpos($name, "logs") === 0) {
			$schemaType = "logs";
		} else {
			$schemaType = "object";
		}
		return $schemaType;
	}



	function isRemote()
	{
		return !$this->isLocal();
	}

	function isLocal()
	{
		return !in_array($this->type, ["api"]);
	}

	function setType($val)
	{
		$this->type = $val;

		if ($this->type === "object" || $this->type === "post" || $this->type === "region") {
			if ($this->type === "object" || $this->type === "post") {
				$defaultApiEndpoints = [
					['url' => "/objects/%name%", 'method' => "GET", 'name' => "index"],
					['url' => "/objects/%name%/[:id]", 'method' => "GET", 'name' => "show"],
					['url' => "/objects/%name%/distinct/[:fieldName]", 'method' => "GET", 'name' => "distinct"],
					['url' => "/objects/%name%", 'method' => "POST", 'name' => "create"],
					['url' => "/objects/%name%/[:id]", 'method' => "PUT", 'name' => "update"],
					['url' => "/objects/%name%/[:id]", 'method' => "DELETE", 'name' => "delete"],
					['url' => "/objects/%name%/[:id]/import", 'method' => "POST", 'name' => "import"],
					['url' => "/objects/%name%", 'method' => "DELETE", 'name' => "clear"]
				];
			}
			if ($this->type === "region") {
				$defaultApiEndpoints = [
					['url' => "/regions/%name%", 'method' => "GET", 'name' => "index"],
					['url' => "/regions/%name%/[:id]", 'method' => "GET", 'name' => "show"],
					['url' => "/regions/%name%/distinct/[:fieldName]", 'method' => "GET", 'name' => "distinct"],
					['url' => "/regions/%name%", 'method' => "POST", 'name' => "create"],
					['url' => "/regions/%name%/[:id]", 'method' => "PUT", 'name' => "update"],
					['url' => "/regions/%name%/[:id]/import", 'method' => "POST", 'name' => "import"],
					['url' => "/regions/%name%/[:id]", 'method' => "DELETE", 'name' => "delete"],
				];
			}

			foreach ($defaultApiEndpoints as &$endpoint) {
				$name = $this->name ? $this->name : '';
				$endpoint['url'] = str_replace('%name%', $name, $endpoint['url']);
			}
			$this->setApiEndpoints($defaultApiEndpoints);
			$this->setApiBaseUrl($this->getDefaultApiBaseUrl());
		}
	}

	private function getDefaultApiBaseUrl()
	{
		$base_url = trailingslashit(home_url());
		return $base_url . 'wp-json/mapsvg/v1/';
	}


	function setApiAuthorization($authorization)
	{
		$this->apiAuthorization = $authorization;
	}

	function setApiBaseUrl($url)
	{
		if (!isset($url) || empty($url)) {
			$url = "";
		}
		$this->apiBaseUrl = rtrim($url, "/");
	}

	function setObjectNameSingular($name)
	{
		$this->objectNameSingular = $name;
	}

	function setObjectNamePlural($name)
	{
		$this->objectNamePlural = $name;
	}

	function setApiEndPoints($value)
	{
		$this->apiEndpoints = is_string($value) ? json_decode($value, true) : $value;
	}

	/**
	 * Get all fields types from regions / database table schema
	 *
	 * @return array|bool List of field types
	 */
	function getFieldTypes()
	{
		$db_types = array();
		foreach ($this->fields as $s) {
			$db_types[$s->name] = $s->type;

			if ($s->name === 'post_id') {
				$db_types['post'] = 'post';
			}
		}
		return $db_types;
	}

	function getFieldNames()
	{
		$db_names = array();
		foreach ($this->fields as $s) {
			$db_names[] = $s->name;
		}
		return $db_names;
	}

	public function getFields()
	{
		return $this->fields;
	}

	public function setName($name)
	{
		$this->name = str_replace(' ', '_', $name);
	}
	public function getName()
	{
		return $this->name;
	}
	public function setPostType($postType)
	{
		$this->postType = $postType;
	}
	public function getPostType()
	{
		return $this->postType;
	}

	/**
	 * Returns the primary key column name.
	 * Derives it from the id-type field in the schema, or falls back to the
	 * explicitly stored primaryKeyField property (default 'id').
	 *
	 * @return string
	 */
	public function getPrimaryKeyFieldName()
	{
		if (!empty($this->fields)) {
			foreach ($this->fields as $field) {
				if (isset($field->type) && strtolower($field->type) === 'id') {
					return $field->name;
				}
			}
		}
		return $this->primaryKeyField ?: 'id';
	}

	public function setPrimaryKeyField($name)
	{
		$this->primaryKeyField = $name;
	}
	public function setTitle($title)
	{
		$this->title = $title;
	}
	public function getTitle()
	{
		return $this->title;
	}

	public function setPrevFields()
	{
		if (!empty($this->fields)) {
			$this->prevFields = $this->fields;
		}
	}
	public function getPrevFields()
	{
		return $this->prevFields;
	}
	public function clearPrevFields()
	{
		return $this->prevFields = array();
	}


	public function setFields($fields)
	{

		$this->setPrevFields();

		if (is_string($fields)) {
			$fields = json_decode($fields);
		}
		$this->fields = array();

		if ($fields) foreach ($fields as $key => $field) {
			$this->fields[$key] = $this->formatField((object)$field);
		}

		return true;
	}

	public function getFieldsOptions()
	{
		$fieldsOptions = array();
		if (is_array($this->fields)) {
			foreach ($this->fields as $field) {
				if (isset($field->options)) {
					$optionsDict = new \stdClass();
					$fieldsOptions[$field->name] = array();
					foreach ($field->options as $option) {
						$key = isset($option->value) ? $option->value : $option->id;
						$optionsDict->{$key} = $option;
						$fieldsOptions[$field->name][$key] = (array)$option;
					}
				}
			}
		}
	}

	public function addField($field, $prepend = false)
	{
		$this->setPrevFields();

		if (!is_object($field)) {
			$field = json_decode(wp_json_encode($field), FALSE);
		}

		if ($prepend) {
			$this->fields = array_merge([$field], $this->fields);
		} else {
			$this->fields[] = $this->formatField((object)$field);
		}
	}

	public function removeField($fieldName)
	{
		$this->setPrevFields();
		for ($i = 0; $i < count($this->fields); $i++) {
			$field = $this->fields[$i];
			if ($field->name === $fieldName) {
				array_splice($this->fields, $i, 1);
			}
		}
	}

	public function getField($fieldName)
	{
		if (empty($this->fields)) {
			return null;
		}
		foreach ($this->fields as $field) {
			if ($field->name === $fieldName) {
				return $field;
			}
		}
		return null;
	}

	public function getFieldByType($type)
	{
		if (empty($this->fields)) {
			return null;
		}
		foreach ($this->fields as $field) {
			if ($field->type === $type) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * Returns the geocoding language for this schema.
	 *
	 * Priority:
	 *  1. location field's own `language` property
	 *  2. googleMaps.language from any map that references this schema
	 *  3. 'en' as the final default
	 */
	public function getLocationLanguage(): string
	{
		$locationField = $this->getFieldByType('location');
		if ($locationField && !empty($locationField->language)) {
			return $locationField->language;
		}

		// Fallback: check maps that use this schema for their Google Maps language setting.
		$db         = Database::get();
		$mapsTable  = $db->mapsvg_prefix . 'maps';

		if (!$db->get_var("SHOW TABLES LIKE '{$mapsTable}'")) {
			return 'en';
		}

		$rows = $db->get_results("SELECT `options` FROM `{$mapsTable}`", ARRAY_A);
		foreach ((array) $rows as $row) {
			$opts = is_string($row['options']) ? json_decode($row['options'], true) : (array) $row['options'];
			if (!is_array($opts)) {
				continue;
			}
			$objName = $opts['database']['schemas']['objects']['name'] ?? '';
			$regName = $opts['database']['schemas']['regions']['name'] ?? '';
			if ($objName === $this->name || $regName === $this->name) {
				$lang = $opts['googleMaps']['language'] ?? '';
				if (!empty($lang)) {
					return $lang;
				}
			}
		}

		return 'en';
	}

	public function renameField($currentName, $newName)
	{
		$resultField = false;
		foreach ($this->fields as $key => $field) {
			if ($field->name === $currentName) {
				$this->fields[$key]->name = $newName;
			}
		}
	}

	public function getFieldOptions($fieldName)
	{
		$fields = $this->getField($fieldName);
		$options = array();
		foreach ($fields->options as $option) {
			$key = isset($option->value) ? $option->value : $option->id;
			$options[$key] = (array)$option;
		}
		return $options;
	}

	public function formatField($field)
	{
		$booleans = array('searchable', 'readonly', 'protected', 'visible', 'auto_increment', 'not_null');
		foreach ($booleans as $booleanFieldName) {
			if (isset($field->{$booleanFieldName})) {
				$field->{$booleanFieldName} = filter_var($field->{$booleanFieldName}, FILTER_VALIDATE_BOOLEAN);
			}
		}
		return $field;
	}

	public function getSearchableFields($fields = null, $options = [])
	{

		$options = array_merge(array('onlyNames' => false, 'onlyFulltext' => false, 'withPost' => false), $options);
		$searchable_fields = array();

		$_fields = $fields ? $fields : $this->fields;

		foreach ($_fields as $field) {
			if ($options['withPost'] && $field->type === "post") {
				$searchable_fields[] = $field;
			} elseif (isset($field->searchable) && $field->searchable === true) {
				$field = (array)$field;
				if ($field['type'] === 'location') {
					$field['name'] = $field['name'] . '_address';
				} elseif (($field['type'] === 'select' && (!isset($field['multiselect']) || $field['multiselect'] !== true)) || $field['type'] === 'radio') {
					$field['name'] = $field['name'] . '_text';
				}
				if ($options['onlyFulltext'] === false) {
					$searchable_fields[] = $field;
				} else {
					// Don't add incompatible column types to fulltext index
					if ($field["db_type"] !== "text" && $field["db_type"] !== "longtext" && strpos($field["db_type"], "varchar") === false) {
						continue;
					}
					if ($field['type'] === 'text') {
						if (!isset($field['searchType']) || $field['searchType'] === 'fulltext') {
							$searchable_fields[] = $field;
						}
					} else {
						$searchable_fields[] = $field;
					}
				}
			}
		}
		if ($options['onlyNames']) {
			$names = [];
			foreach ($searchable_fields as $field) {
				$names[] = $field['name'];
			}
			return $names;
		} else {
			return json_decode(wp_json_encode($searchable_fields, JSON_UNESCAPED_UNICODE), true);
		}
	}
}
