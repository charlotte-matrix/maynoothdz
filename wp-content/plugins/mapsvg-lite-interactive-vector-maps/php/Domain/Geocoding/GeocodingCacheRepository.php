<?php

namespace MapSVG;

class GeocodingCacheRepository
{
    private Database $db;
    private string   $table;

    public function __construct()
    {
        $this->db    = Database::get();
        $this->table = $this->db->mapsvg_prefix . 'geocoding_cache';
    }

    /**
     * Look up a cached geocoding response by cache key.
     * Returns the decoded response array, or null on a cache miss.
     */
    public function find(string $request): ?array
    {
        $row = $this->db->get_row(
            $this->db->prepare(
                "SELECT `response` FROM `{$this->table}` WHERE `request` = %s LIMIT 1",
                $request
            ),
            ARRAY_A
        );

        return $row ? json_decode($row['response'], true) : null;
    }

    /**
     * Persist a successful geocoding response. Upserts so re-imports are safe.
     */
    public function store(string $request, array $response): void
    {
        $this->db->query(
            $this->db->prepare(
                "INSERT INTO `{$this->table}` (`request`, `response`)
                 VALUES (%s, %s)
                 ON DUPLICATE KEY UPDATE `response` = VALUES(`response`)",
                $request,
                wp_json_encode($response, JSON_UNESCAPED_UNICODE)
            )
        );
    }
}
