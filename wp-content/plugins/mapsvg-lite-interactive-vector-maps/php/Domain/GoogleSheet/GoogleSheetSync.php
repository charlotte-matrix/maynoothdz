<?php

namespace MapSVG;

/**
 * Handles automatic Google Sheets to CSV import via WP Cron.
 *
 * Uses a recurring wp_schedule_event with a per-interval custom schedule
 * (see registerCronSchedules) so each schema's refetch interval is honored.
 */
class GoogleSheetSync
{
    const CRON_HOOK = 'mapsvg_gs_sync';
    const MODE_UPSERT = 'upsert';
    const MODE_APPEND = 'append';
    const MODE_SNAPSHOT_REPLACE = 'snapshot_replace';

    private static function settings(Schema $schema): array
    {
        return ImportSettingsService::getForSchema($schema);
    }

    public static function hasStableId(Schema $schema): bool
    {
        $settings = self::settings($schema);
        return is_string($settings['gsIdFieldName'] ?? null) && trim((string) $settings['gsIdFieldName']) !== '';
    }

    /**
     * Preserve absent vs explicit-empty ID override from a request or preflight value.
     *
     * @param mixed $raw Request/preflight idField. null = no override supplied.
     * @return string|null null, '' (explicit No ID), or a normalised field name
     */
    public static function normalizeOverrideIdField($raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        return CsvImporter::normalizeHeader(trim((string) $raw));
    }

    /**
     * Read preflight meta `idField` without collapsing a missing/null value to ''.
     *
     * @param array|null $preflightMeta
     * @return string|null
     */
    public static function resolveOverrideIdFieldFromPreflight(?array $preflightMeta): ?string
    {
        if (!is_array($preflightMeta) || !array_key_exists('idField', $preflightMeta)) {
            return null;
        }

        return self::normalizeOverrideIdField($preflightMeta['idField']);
    }

    /**
     * Three-state upload/request ID override:
     * - null: no override supplied; persisted remote/Google Sheets gsIdFieldName may be used
     * - '': explicit "No ID in CSV file"; must append (or snapshot_replace on auto-refetch)
     * - non-empty: upsert using that selected field
     *
     * @param string|null $overrideIdField
     * @param bool        $hasPersistedStableId
     * @param bool        $isAutoRefetch
     * @return string
     */
    public static function resolveImportModeFromOverride(
        ?string $overrideIdField,
        bool $hasPersistedStableId,
        bool $isAutoRefetch
    ): string {
        if ($overrideIdField !== null) {
            if (self::hasExplicitIdField($overrideIdField)) {
                return self::MODE_UPSERT;
            }

            return $isAutoRefetch ? self::MODE_SNAPSHOT_REPLACE : self::MODE_APPEND;
        }

        if ($hasPersistedStableId) {
            return self::MODE_UPSERT;
        }

        return $isAutoRefetch ? self::MODE_SNAPSHOT_REPLACE : self::MODE_APPEND;
    }

    /**
     * @param string|null $overrideIdField Preflight / request ID column. null = no override;
     *                                     '' = explicit No ID; non-empty forces upsert.
     *                                     File-upload imports do not persist gsIdFieldName.
     */
    public static function resolveImportMode(Schema $schema, bool $isAutoRefetch, ?string $overrideIdField = null): string
    {
        return self::resolveImportModeFromOverride(
            $overrideIdField,
            self::hasStableId($schema),
            $isAutoRefetch
        );
    }

    public static function hasExplicitIdField(?string $idFieldName): bool
    {
        return is_string($idFieldName) && trim($idFieldName) !== '';
    }

    /**
     * Whether this schema should have the recurring remote CSV refetch cron.
     *
     * @param Schema $schema
     * @return bool
     */
    public static function shouldScheduleRemoteRefetch(Schema $schema): bool
    {
        $settings = self::settings($schema);
        return ($settings['gsImportSource'] ?? 'upload') === 'remote'
            && (bool) ($settings['gsAutoRefetch'] ?? 0)
            && !empty($settings['gsCsvUrl']);
    }

    /**
     * Register MapSVG refetch intervals (1 to 168 hours) for wp_schedule_event.
     *
     * @param array $schedules WordPress cron_schedules array.
     * @return array
     */
    public static function registerCronSchedules($schedules)
    {
        for ($h = 1; $h <= 168; $h++) {
            $key                = 'mapsvg_gs_h_' . $h;
            $schedules[$key] = [
                'interval' => $h * HOUR_IN_SECONDS,
                // translators: %d = hour count.
                'display'  => sprintf(__('Every %d hours (MapSVG)', 'mapsvg'), $h),
            ];
        }

        return $schedules;
    }

    /**
     * Schedule recurring cron for a schema. Clears any existing event first.
     *
     * @param Schema $schema
     * @param bool   $immediate Fire after 60 s instead of waiting a full interval first.
     */
    public static function scheduleForSchema(Schema $schema, bool $immediate = false): void
    {
        if (!self::shouldScheduleRemoteRefetch($schema)) {
            return;
        }

        // Min. interval is 1 minute.
        $settings = self::settings($schema);
        $intervalHours = max(0.016, (($settings['gsRefetchInterval'] ?? 24) ?: 24));
        $scheduleName  = 'mapsvg_gs_h_' . $intervalHours;

        self::clearForSchema($schema->name);

        $firstRunDelay = $immediate ? 60 : $intervalHours * HOUR_IN_SECONDS;
        wp_schedule_event(time() + $firstRunDelay, $scheduleName, self::CRON_HOOK, [$schema->name]);
    }

    /**
     * Remove the recurring cron event for a schema (any recurrence slug).
     *
     * @param string $schemaName
     */
    public static function clearForSchema(string $schemaName): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK, [$schemaName]);
    }

    /**
     * After import_settings change: schedule or clear remote CSV auto-refetch cron;
     * enqueue geocoding when appropriate.
     *
     * @param Schema $schema
     */
    public static function syncCronAfterImportSettingsChange(Schema $schema): void
    {

        if (self::shouldScheduleRemoteRefetch($schema)) {

            self::scheduleForSchema($schema);
        } else {
            self::clearForSchema($schema->name);
        }
        self::enqueueGeocodingIfPending($schema);
    }

    /**
     * If a schema should be scheduled but WP-Cron lost the event, restore it.
     *
     * @return void
     */
    public static function repairMissingScheduledEvents(): void
    {
        /** @var SchemaRepository $schemaRepo */
        $schemaRepo = RepositoryFactory::get('schema');
        $result     = $schemaRepo->find(new Query(['perpage' => 0]));
        $items      = $result['items'] ?? [];

        foreach ($items as $schema) {
            if (!self::shouldScheduleRemoteRefetch($schema)) {
                continue;
            }
            if (!wp_next_scheduled(self::CRON_HOOK, [$schema->name])) {
                self::scheduleForSchema($schema, false);
            }
        }
    }

    /**
     * Download a remote CSV URL, write it to a temp file, import it into the
     * given repository, then update the schema's gsCsvHash.
     *
     * @param string     $csvUrl
     * @param Repository $repo
     * @param string     $tmpSuffix
     * @param array      $geocoding
     * @return array{count: int, error: string|null}
     */
    public static function fetchAndImportCsv(string $csvUrl, Repository $repo, string $tmpSuffix = 'gs', array $geocoding = []): array
    {
        $httpResponse = SafeRemoteUrl::get($csvUrl, [
            'timeout' => 30,
        ]);

        if (is_wp_error($httpResponse)) {
            return [
                'count' => 0,
                'error' => SafeRemoteUrl::publicErrorMessage($httpResponse, 'Failed to fetch CSV.'),
            ];
        }

        $httpCode = wp_remote_retrieve_response_code($httpResponse);
        if ($httpCode !== 200) {
            return ['count' => 0, 'error' => 'Remote server returned HTTP ' . $httpCode . '.'];
        }

        $csvContent = wp_remote_retrieve_body($httpResponse);
        if (empty($csvContent)) {
            return ['count' => 0, 'error' => 'Remote CSV is empty.'];
        }

        $csvDir  = \MAPSVG_UPLOADS_DIR . DIRECTORY_SEPARATOR . 'csv';
        wp_mkdir_p($csvDir);
        $tmpPath = $csvDir . DIRECTORY_SEPARATOR . $tmpSuffix . '_' . time() . '.csv';

        if (file_put_contents($tmpPath, $csvContent) === false) {
            return ['count' => 0, 'error' => 'Could not write temporary CSV file.'];
        }

        $convertLatlngToAddress = !empty($geocoding['convertLatlngToAddress']);
        $convertAddressToLatLng = !empty($geocoding['convertAddressToLatLng']);
        $paidGeocoding          = !empty($geocoding['paidGeocoding']);

        $language = $repo->schema->getLocationLanguage();
        $schemaNm = $repo->schema->name;
        $rowsEst  = max(1, substr_count($csvContent, "\n"));

        SchemaImportLifecycle::beginImport(
            $schemaNm,
            SchemaImportLifecycle::estimateSeconds(
                $rowsEst,
                $convertLatlngToAddress,
                $convertAddressToLatLng,
                $paidGeocoding
            )
        );

        $heartbeat = static function () use ($schemaNm) {
            SchemaImportLifecycle::touchProgress($schemaNm);
        };

        try {
            $mode = self::resolveImportMode($repo->schema, false);
            $result = $repo->importFromCsv(
                $tmpPath,
                $convertLatlngToAddress,
                $convertAddressToLatLng,
                $language,
                '',
                $heartbeat,
                $mode === self::MODE_UPSERT
            );
        } catch (\Exception $e) {
            @unlink($tmpPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            SchemaImportLifecycle::resetInProgress($schemaNm);

            return ['count' => 0, 'error' => $e->getMessage()];
        }

        @unlink($tmpPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        if (!empty($result['needs_geocoding']) && ($convertLatlngToAddress || $convertAddressToLatLng)) {
            GeocodingQueue::add($repo->schema->name, $language, $convertLatlngToAddress, $paidGeocoding, $convertAddressToLatLng);
        }

        SchemaImportLifecycle::completeImport($schemaNm);

        ImportSettingsService::updateForSchema($repo->schema, [
            'gsCsvHash' => md5($csvContent),
        ]);

        return ['count' => $result['count'] ?? 0, 'error' => null];
    }

    /**
     * When geocoding is enabled and DB rows still match "pending geocoding", enqueue the
     * batch processor — without re-importing CSV (used when remote hash unchanged or after save).
     */
    public static function enqueueGeocodingIfPending(Schema $schema): void
    {
        $settings = self::settings($schema);
        if (empty($settings['gsGeocode'])) {
            return;
        }

        $convertLatlngToAddress  = (bool) ($settings['gsGeocodeConvertLatLngToAddress'] ?? false);
        $convertAddressToLatLng = (bool) ($settings['gsGeocodeConvertAddressToLatLng'] ?? false);

        if (!$convertLatlngToAddress && !$convertAddressToLatLng) {
            return;
        }

        $repo = RepositoryFactory::get($schema->name);
        if (!$repo || empty($repo->schema)) {
            return;
        }

        $language       = $repo->schema->getLocationLanguage();
        $paidGeocoding  = (bool) ($settings['gsPaidGeocoding'] ?? false);
        $pending        = GeocodingQueue::countPending($schema->name, $convertLatlngToAddress, $convertAddressToLatLng);

        if ($pending > 0) {
            GeocodingQueue::add($schema->name, $language, $convertLatlngToAddress, $paidGeocoding, $convertAddressToLatLng);
        }
    }

    /**
     * WP Cron callback: fetches the CSV URL for a schema and imports when the
     * file hash changed. Does not reschedule (recurring hook handles the next run).
     *
     * @param string $schemaName
     */
    public static function process(string $schemaName): void
    {
        $schema     = null;
        $schemaRepo = RepositoryFactory::get('schema');

        try {
            $schema = $schemaRepo->findByName($schemaName);

            if (!$schema) {
                return;
            }
            $settings = self::settings($schema);
            if (empty($settings['gsCsvUrl'])) {
                return;
            }

            if (!self::shouldScheduleRemoteRefetch($schema)) {
                self::clearForSchema($schemaName);

                return;
            }

            if (SchemaImportLifecycle::isImportInProgress($schema)) {
                return;
            }

            $httpResponse = SafeRemoteUrl::get($settings['gsCsvUrl'], [
                'timeout' => 30,
            ]);

            if (is_wp_error($httpResponse) || wp_remote_retrieve_response_code($httpResponse) !== 200) {
                return;
            }

            $csvContent = wp_remote_retrieve_body($httpResponse);
            if (empty($csvContent)) {
                return;
            }

            if (md5($csvContent) === ($settings['gsCsvHash'] ?? null)) {
                self::enqueueGeocodingIfPending($schema);

                return;
            }

            $repo = RepositoryFactory::get($schemaName);
            $mode = self::resolveImportMode($repo->schema, true);

            $geocoding = [];
            if (!empty($settings['gsGeocode'])) {
                $geocoding = [
                    'convertLatlngToAddress' => (bool) ($settings['gsGeocodeConvertLatLngToAddress'] ?? false),
                    'convertAddressToLatLng' => (bool) ($settings['gsGeocodeConvertAddressToLatLng'] ?? false),
                    'paidGeocoding'          => (bool) ($settings['gsPaidGeocoding'] ?? false),
                ];
            }

            $convertLatlngToAddress = !empty($geocoding['convertLatlngToAddress']);
            $convertAddressToLatLng = !empty($geocoding['convertAddressToLatLng']);
            $paidGeocoding          = !empty($geocoding['paidGeocoding']);

            $language = $repo->schema->getLocationLanguage();
            $rowsEst  = max(1, substr_count($csvContent, "\n"));

            SchemaImportLifecycle::beginImport(
                $schemaName,
                SchemaImportLifecycle::estimateSeconds(
                    $rowsEst,
                    $convertLatlngToAddress,
                    $convertAddressToLatLng,
                    $paidGeocoding
                )
            );

            $heartbeat = static function () use ($schemaName) {
                SchemaImportLifecycle::touchProgress($schemaName);
            };

            $csvDir  = \MAPSVG_UPLOADS_DIR . DIRECTORY_SEPARATOR . 'csv';
            wp_mkdir_p($csvDir);
            $tmpPath = $csvDir . DIRECTORY_SEPARATOR . 'gs_cron_' . sanitize_key($schemaName) . '_' . time() . '.csv';

            if (file_put_contents($tmpPath, $csvContent) === false) {
                SchemaImportLifecycle::resetInProgress($schemaName);

                return;
            }

            try {
                if ($mode === self::MODE_SNAPSHOT_REPLACE) {
                    $result = self::importWithTableSwap(
                        $repo,
                        $tmpPath,
                        $convertLatlngToAddress,
                        $convertAddressToLatLng,
                        $language,
                        $heartbeat
                    );
                } else {
                    $result = $repo->importFromCsv(
                        $tmpPath,
                        $convertLatlngToAddress,
                        $convertAddressToLatLng,
                        $language,
                        '',
                        $heartbeat,
                        $mode === self::MODE_UPSERT
                    );
                }

                if (!empty($result['needs_geocoding']) && ($convertLatlngToAddress || $convertAddressToLatLng)) {
                    GeocodingQueue::add($schemaName, $language, $convertLatlngToAddress, $paidGeocoding, $convertAddressToLatLng);
                }
            } catch (\Exception $e) {
                SchemaImportLifecycle::resetInProgress($schemaName);
                @unlink($tmpPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

                return;
            }

            @unlink($tmpPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

            SchemaImportLifecycle::completeImport($schemaName);

            ImportSettingsService::updateForSchema($schema, [
                'gsCsvHash' => md5($csvContent),
            ]);
        } catch (\Exception $e) {
            if ($schema) {
                SchemaImportLifecycle::resetInProgress($schemaName);
            }
        }
    }

    /**
     * Imports CSV into a staging table and atomically swaps it with live table.
     *
     * @return array{count:int, needs_geocoding:bool}
     * @throws \Exception
     */
    private static function importWithTableSwap(
        Repository $repo,
        string $filePath,
        bool $convertLatlngToAddress,
        bool $convertAddressToLatLng,
        string $language,
        $onBatchFlush = null
    ): array {
        $db         = Database::get();
        $liveSource = $repo->schema->name;
        $liveTable  = $db->mapsvg_prefix . $liveSource;
        $suffix     = sanitize_key((string) time()) . '_' . wp_generate_password(6, false, false);
        $stageSrc   = $liveSource . '_stg_' . $suffix;
        $backupSrc  = $liveSource . '_bak_' . $suffix;
        $stageTable = $db->mapsvg_prefix . $stageSrc;
        $backupTable = $db->mapsvg_prefix . $backupSrc;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are escaped
        $db->query("CREATE TABLE `" . esc_sql($stageTable) . "` LIKE `" . esc_sql($liveTable) . "`");

        try {
            $stageSource = new DbDataSource($repo->schema);
            $stageSource->setSource($stageSrc);
            $importer = new CsvImporter($repo->schema, $stageSource);
            $result = $importer->import(
                $filePath,
                $convertLatlngToAddress,
                $convertAddressToLatLng,
                '',
                $onBatchFlush,
                false
            );

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are escaped
            $db->query(
                "RENAME TABLE `"
                    . esc_sql($liveTable) . "` TO `" . esc_sql($backupTable)
                    . "`, `" . esc_sql($stageTable) . "` TO `" . esc_sql($liveTable) . "`"
            );

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are escaped
            $db->query("DROP TABLE `" . esc_sql($backupTable) . "`");

            $repo->setRelationsForAllObjects();

            return $result;
        } catch (\Exception $e) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are escaped
            $db->query("DROP TABLE IF EXISTS `" . esc_sql($stageTable) . "`");
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are escaped
            $db->query("DROP TABLE IF EXISTS `" . esc_sql($backupTable) . "`");
            throw $e;
        }
    }
}
