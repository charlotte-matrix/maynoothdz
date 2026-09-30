<?php

namespace MapSVG;

/**
 * Tracks CSV import progress on the schema row for UX (ETA), cron locking,
 * and stale heartbeat detection after PHP crashes.
 */
class SchemaImportLifecycle
{

    /**
     * Seconds without heartbeat before an in-progress import is treated as abandoned.
     *
     * @return int
     */
    public static function getStaleThreshold(): int
    {
        return (int) apply_filters('mapsvg_stale_import_threshold', 10 * MINUTE_IN_SECONDS);
    }

    /**
     * Import is active when a run has started but not recorded completion.
     *
     * @param Schema $schema
     * @return bool
     */
    public static function isImportInProgress(Schema $schema): bool
    {
        $settings = ImportSettingsService::getForSchema($schema);
        return !empty($settings['gsImportStartedAt']) && empty($settings['gsImportFinishedAt']);
    }

    /**
     * Rough ETA for UI — not authoritative for stale detection (heartbeat is).
     *
     * @param int  $rowCount
     * @param bool $convertLatlngToAddress
     * @param bool $convertAddressToLatLng
     * @param bool $paidGeocoding
     * @return int
     */
    public static function estimateSeconds(
        int $rowCount,
        bool $convertLatlngToAddress,
        bool $convertAddressToLatLng,
        bool $paidGeocoding
    ): int {
        $base = max(30, (int) ($rowCount * 0.05));
        $geo  = $convertLatlngToAddress || $convertAddressToLatLng;
        if ($geo) {
            if ($paidGeocoding) {
                $base += (int) min(3600, $rowCount * 0.3);
            } else {
                $base += (int) min(86400 * 7, max(300, $rowCount * 2));
            }
        }

        return min(2147483647, $base);
    }

    /**
     * Mark the start of an import: clears the last-finished timestamp until complete.
     *
     * @param string   $schemaName
     * @param int|null $estimatedSeconds
     * @return void
     */
    public static function beginImport(string $schemaName, $estimatedSeconds = null): void
    {
        $schemaRepo = RepositoryFactory::get('schema');
        $schema     = $schemaRepo->findByName($schemaName);
        if (!$schema) {
            return;
        }
        $now = current_time('mysql');
        ImportSettingsService::updateForSchema($schema, [
            'gsImportStartedAt'       => $now,
            'gsImportLastUpdatedAt'   => $now,
            'gsImportFinishedAt'      => null,
            'gsImportEstimatedSeconds' => ($estimatedSeconds !== null && (int) $estimatedSeconds > 0)
                ? (int) $estimatedSeconds
                : null,
        ]);
    }

    /**
     * Heartbeat: call after each meaningful batch of work.
     *
     * @param string $schemaName
     * @return void
     */
    public static function touchProgress(string $schemaName): void
    {
        $schemaRepo = RepositoryFactory::get('schema');
        $schema     = $schemaRepo->findByName($schemaName);
        if (!$schema) {
            return;
        }
        $settings = ImportSettingsService::getForSchema($schema);
        if (empty($settings['gsImportStartedAt'])) {
            return;
        }
        ImportSettingsService::updateForSchema($schema, [
            'gsImportLastUpdatedAt' => current_time('mysql'),
        ]);
    }

    /**
     * Successful completion: records finish time and clears in-progress fields.
     *
     * @param string $schemaName
     * @return string MySQL datetime of completion
     */
    public static function completeImport(string $schemaName): string
    {
        $finishedAt = current_time('mysql');
        $schemaRepo = RepositoryFactory::get('schema');
        $schema     = $schemaRepo->findByName($schemaName);
        if (!$schema) {
            return $finishedAt;
        }
        ImportSettingsService::updateForSchema($schema, [
            'gsImportFinishedAt'      => $finishedAt,
            'gsImportStartedAt'       => null,
            'gsImportLastUpdatedAt'   => null,
            'gsImportEstimatedSeconds' => null,
        ]);

        return $finishedAt;
    }

    /**
     * Clears in-progress markers after failure or stale repair (keeps last gsImportFinishedAt).
     *
     * @param string $schemaName
     * @return void
     */
    public static function resetInProgress(string $schemaName): void
    {
        $schemaRepo = RepositoryFactory::get('schema');
        $schema     = $schemaRepo->findByName($schemaName);
        if (!$schema) {
            return;
        }
        ImportSettingsService::updateForSchema($schema, [
            'gsImportStartedAt'       => null,
            'gsImportLastUpdatedAt'   => null,
            'gsImportEstimatedSeconds' => null,
        ]);
    }

    /**
     * Clears abandoned import locks when the heartbeat is too old.
     *
     * @return void
     */
    public static function repairStaleImports(): void
    {
        $schemaRepo = RepositoryFactory::get('schema');
        $result     = $schemaRepo->find(new Query(['perpage' => 0]));
        $items      = $result['items'] ?? [];
        $threshold  = self::getStaleThreshold();
        $logRepo    = new ImportLogRepository();

        foreach ($items as $schema) {
            if (!self::isImportInProgress($schema)) {
                continue;
            }
            $settings = ImportSettingsService::getForSchema($schema);
            $last = $settings['gsImportLastUpdatedAt'] ?? $settings['gsImportStartedAt'] ?? null;
            if (empty($last)) {
                continue;
            }
            $lastTs = mysql2date('U', $last, false);
            if ($lastTs && (current_time('timestamp') - (int) $lastTs) > $threshold) {
                $logRepo->upsert($schema->name, 'Import lock cleared (stale heartbeat).', 'info');
                self::resetInProgress($schema->name);
            }
        }
    }
}
