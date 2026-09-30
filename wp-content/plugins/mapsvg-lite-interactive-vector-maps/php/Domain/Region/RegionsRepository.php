<?php

namespace MapSVG;

class RegionsRepository extends Repository
{

	public static $className = 'Region';
	public $prefix = "";

	public function __construct($tableName = null)
	{
		$this->prefix = "";
		$this->db = Database::get();
		parent::__construct($tableName);
	}

	public function getTableName()
	{
		return $this->db->mapsvg_prefix . $this->id;
	}

	public function setPrefix($prefix)
	{
		$this->prefix = $prefix;
	}

	/**
	 * Returns an array of Entities by provided Query.
	 * By default excludes rows with orphaned=1 unless filters.includeOrphans is set.
	 *
	 * @param Query|null $query
	 * @return array
	 */
	public function find($query = null)
	{
		if ($query === null) {
			$query = new Query(array('perpage' => 0, 'filters' => array()));
		}
		if ($this->prefix) {
			$query->filters["prefix"] = $this->prefix;
		}
		if (!$query || !$query->sort) {
			$query->sort = [["field" => "id", "order" => "ASC"]];
		}

		$includeOrphans = !empty($query->filters['includeOrphans']);
		unset($query->filters['includeOrphans']);

		if (!$includeOrphans && $this->schema && $this->schema->getField('orphaned')) {
			if (!is_array($query->filterout)) {
				$query->filterout = array();
			}
			if (!array_key_exists('orphaned', $query->filterout)) {
				$query->filterout['orphaned'] = 1;
			}
		}

		return parent::find($query);
	}

	/**
	 * Bulk-set the orphaned flag for the given region IDs (does not touch other columns).
	 *
	 * @param string[] $ids
	 * @param int      $orphaned 1 = orphaned, 0 = active
	 */
	public function setOrphanedByIds(array $ids, int $orphaned): void
	{
		if (empty($ids) || !$this->schema || !$this->schema->getField('orphaned')) {
			return;
		}

		$rows = array();
		foreach ($ids as $id) {
			$rows[] = array(
				'id'       => (string) $id,
				'orphaned' => $orphaned ? 1 : 0,
			);
		}

		$this->createOrUpdateAll($rows);
	}

	/**
	 * Count rows currently marked orphaned (ignores prefix / default find filters).
	 */
	public function countOrphaned(): int
	{
		if (!$this->schema || !$this->schema->getField('orphaned')) {
			return 0;
		}

		$table = $this->getTableName();
		$sql = "SELECT COUNT(*) FROM `{$table}` WHERE `orphaned` = 1";
		if ($this->prefix) {
			$sql .= $this->db->prepare(' AND `id` LIKE %s', $this->prefix . '%');
		}

		return (int) $this->db->get_var($sql);
	}

	/**
	 * Permanently delete all orphaned region rows and detach related r2o links.
	 *
	 * Respects regionPrefix when set (same scope as setRegionsTable / find).
	 *
	 * @return array{deleted: int, r2oDeleted: int, ids: string[]}
	 */
	public function deleteOrphaned(): array
	{
		if (!$this->schema || !$this->schema->getField('orphaned')) {
			return array(
				'deleted'    => 0,
				'r2oDeleted' => 0,
				'ids'        => array(),
			);
		}

		$resp = $this->find(new Query(array(
			'perpage' => 0,
			'filters' => array(
				'includeOrphans' => 1,
				'orphaned'       => 1,
			),
		)));

		$ids = RegionOrphanCleanup::orphanIdsFromRows($resp['items'] ?? array());

		if (empty($ids)) {
			return array(
				'deleted'    => 0,
				'r2oDeleted' => 0,
				'ids'        => array(),
			);
		}

		$r2oDeleted = $this->deleteR2oLinksForRegionIds($ids);

		$table = $this->getTableName();
		$placeholders = implode(',', array_fill(0, count($ids), '%s'));
		$deleteSql = $this->db->prepare(
			"DELETE FROM `{$table}` WHERE `orphaned` = 1 AND `id` IN ({$placeholders})",
			...$ids
		);
		$this->db->query($deleteSql);

		return array(
			'deleted'    => count($ids),
			'r2oDeleted' => $r2oDeleted,
			'ids'        => $ids,
		);
	}

	/**
	 * Remove object↔region links for the given region IDs in this regions table.
	 *
	 * @param string[] $regionIds
	 */
	public function deleteR2oLinksForRegionIds(array $regionIds): int
	{
		$regionIds = array_values(array_filter(array_map('strval', $regionIds)));
		if (empty($regionIds)) {
			return 0;
		}

		$r2oTable = $this->db->mapsvg_prefix . 'r2o';
		if (!$this->db->get_var("SHOW TABLES LIKE '" . esc_sql($r2oTable) . "'")) {
			return 0;
		}

		$placeholders = implode(',', array_fill(0, count($regionIds), '%s'));
		$regionsTableName = $this->id; // short name, e.g. regions_2
		$sql = $this->db->prepare(
			"DELETE FROM `{$r2oTable}` WHERE `regions_table` = %s AND `region_id` IN ({$placeholders})",
			$regionsTableName,
			...$regionIds
		);
		$this->db->query($sql);

		return (int) $this->db->rows_affected;
	}

	/**
	 * Updates all provided regions in the database
	 * @param array $objects
	 */
	public function createOrUpdateAll($objects)
	{
		$fields = array();
		$duplicateUpdateMysql = array();
		foreach ($objects as $object) {
			$keys = array_keys($object);
			if (array_diff($keys, $fields)) {
				$fields = $keys;
			}
		}

		// Filter out non-existing fields using schema validation
		$validFields = array();
		foreach ($fields as $k => $v) {
			if ($this->schema->getField($v)) {
				$validFields[] = $v;
			}
		}
		$fields = $validFields;

		$_fields = array();
		foreach ($fields as $k => $v) {
			$_fields[$k] = '`' . $v . '`';
			$duplicateUpdateMysql[] = '`' . $v . '` = VALUES(`' . $v . '`)';
		}

		$regions = array();
		foreach ($objects as $k => $object) {
			$data = array();
			foreach ($fields as $key => $fieldName) {
				$data[$fieldName] = isset($object[$fieldName]) ? esc_sql($object[$fieldName]) : '';
			}
			$regions[] = "('" . implode("','", $data) . "')";
		}

		$this->db->query('INSERT INTO ' . static::getTableName() . ' (' . implode(',', $_fields) . ') VALUES ' . implode(',', $regions) . ' ON DUPLICATE KEY UPDATE ' . implode(',', $duplicateUpdateMysql));
	}
}
