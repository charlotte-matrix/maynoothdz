<?php


namespace MapSVG;


/**
 * Proxy class that redirects all method calls to $wpdb
 * @package MapSVG
 */
class Database
{

	public $db;
	public $prefix;
	public $mapsvg_prefix;
	public $posts;
	public $postmeta;
	private static $dbInstance;
	public $insert_id;

	public function __construct()
	{
		global $wpdb;
		$this->db     = $wpdb;
		$this->mapsvg_prefix = $this->db->prefix . MAPSVG_PREFIX;
		$this->prefix = $this->db->prefix;
		$this->postmeta = $this->db->postmeta;
		$this->posts = $this->db->posts;
	}

	public function __get($name)
	{
		if (property_exists($this, $name)) {
			return $this->$name;
		}
		return $this->db->$name;
	}

	private function executeQuery($method, $args)
	{
		$time = microtime(true);
		$res = call_user_func_array([$this->db, $method], $args);
		$this->handleError();
		Logger::addDatabaseQuery($this->db->last_query, $time);
		return $res;
	}

	public function db_version()
	{
		return $this->db->db_version();
	}

	public function posts()
	{
		return $this->db->posts;
	}

	/* @return Database */
	public static function get()
	{
		if (!self::$dbInstance) {
			self::$dbInstance = new self();
		}
		return self::$dbInstance;
	}

	

	public function handleError($string = '')
	{
		if ($this->db->last_error) {
			// $caller = $this->getCaller();
			Logger::error("[SERVER-003] " . $this->db->last_error . " — Read more: https://mapsvg.com/docs/errors#SERVER-003");
		}
	}

	public function query($query)
	{
		return $this->executeQuery('query', [$query]);
	}

	public function get_col($query, $num)
	{
		return $this->executeQuery('get_col', [$query, $num]);
	}

	public function get_var($query)
	{
		return $this->executeQuery('get_var', [$query]);
	}

	public function get_row($query, $output = OBJECT)
	{
		return $this->executeQuery('get_row', [$query, $output]);
	}

	public function get_results($query, $responseType = OBJECT)
	{
		return $this->executeQuery('get_results', [$query, $responseType]);
	}

	public function insert($table, $data)
	{
		$res = $this->executeQuery('insert', [$table, $data]);
		$this->insert_id = $this->db->insert_id;
		return $res;
	}

	public function update($table, $data, $where = null)
	{
		return $this->executeQuery('update', [$table, $data, $where]);
	}

	public function replace($table, $data, $where = null)
	{
		return $this->executeQuery('replace', [$table, $data, $where]);
	}

	public function delete($table, $data)
	{
		return $this->executeQuery('delete', [$table, $data]);
	}

	public function clear($table)
	{
		return $this->executeQuery('query', ["DELETE FROM " . $table]);
	}

	public function prepare($data, ...$values)
	{
		return $this->db->prepare($data, ...$values);
	}
	public function esc_like($data)
	{
		return $this->db->esc_like($data);
	}

	/**
	 * Start a database transaction
	 *
	 * @return bool
	 */
	public function startTransaction(): bool
	{
		return (bool) $this->query('START TRANSACTION');
	}

	/**
	 * Commit a database transaction
	 *
	 * @return bool
	 */
	public function commit(): bool
	{
		return (bool) $this->query('COMMIT');
	}

	/**
	 * Rollback a database transaction
	 *
	 * @return bool
	 */
	public function rollback(): bool
	{
		return (bool) $this->query('ROLLBACK');
	}

	/**
	 * Check if mysqli is available
	 *
	 * @return bool
	 */
	public function isMysqli(): bool
	{
		return $this->db->dbh instanceof \mysqli;
	}

	/**
	 * Execute multiple SQL queries at once using mysqli_multi_query
	 * This handles semicolons inside JSON data correctly
	 *
	 * @param string $sql SQL queries separated by semicolons
	 * @return bool True on success, false on failure
	 */
	public function multiQuery(string $sql): bool
	{
		if (!$this->isMysqli()) {
			return false;
		}

		return mysqli_multi_query($this->db->dbh, $sql);
	}

	/**
	 * Process all results from multi_query to clear the buffer
	 *
	 * @return void
	 */
	public function processMultiQueryResults(): void
	{
		if (!$this->isMysqli()) {
			return;
		}

		do {
			// Store result if available
			if ($result = mysqli_store_result($this->db->dbh)) {
				mysqli_free_result($result);
			}
		} while (mysqli_next_result($this->db->dbh));
	}

	/**
	 * Get last mysqli error number
	 *
	 * @return int Error number, 0 if no error
	 */
	public function getMysqliErrno(): int
	{
		if (!$this->isMysqli()) {
			return 0;
		}

		return mysqli_errno($this->db->dbh);
	}

	/**
	 * Get last mysqli error message
	 *
	 * @return string Error message, empty string if no error
	 */
	public function getMysqliError(): string
	{
		if (!$this->isMysqli()) {
			return '';
		}

		return mysqli_error($this->db->dbh);
	}
}
