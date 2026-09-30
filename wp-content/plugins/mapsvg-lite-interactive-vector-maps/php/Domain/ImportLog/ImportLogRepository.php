<?php

namespace MapSVG;

class ImportLogRepository
{
    private Database $db;
    private string   $table;

    public function __construct()
    {
        $this->db    = Database::get();
        $this->table = $this->db->mapsvg_prefix . 'import_logs';
    }

    /**
     * Delete all log entries for a given schema — called at the start of each import
     * so the log always reflects only the most recent run.
     */
    public function clearForSchema(string $schemaName): void
    {
        $this->db->query(
            $this->db->prepare("DELETE FROM `{$this->table}` WHERE `schemaName` = %s", $schemaName)
        );
    }

    /**
     * Insert a log entry or increment its counter if an identical one already exists.
     * The id is a deterministic hash so ON DUPLICATE KEY UPDATE handles deduplication.
     */
    public function upsert(string $schemaName, string $message, string $type = 'info'): void
    {
        $id = md5($schemaName . $type . $message);

        $this->db->query(
            $this->db->prepare(
                "INSERT INTO `{$this->table}` (`id`, `schemaName`, `message`, `type`)
                 VALUES (%s, %s, %s, %s)
                 ON DUPLICATE KEY UPDATE `counter` = `counter` + 1, `createdAt` = NOW()",
                $id,
                $schemaName,
                $message,
                $type
            )
        );
    }

    /**
     * Return up to $limit log entries for a schema, ordered: errors first, then by createdAt desc.
     *
     * @return ImportLog[]
     */
    public function findBySchema(string $schemaName, int $limit = 100): array
    {
        $rows = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM `{$this->table}`
                 WHERE `schemaName` = %s
                 ORDER BY FIELD(`type`, 'error', 'warning', 'info'), `counter` DESC
                 LIMIT %d",
                $schemaName,
                $limit
            ),
            ARRAY_A
        );

        return array_map(static fn($row) => new ImportLog($row), $rows ?: []);
    }
}
