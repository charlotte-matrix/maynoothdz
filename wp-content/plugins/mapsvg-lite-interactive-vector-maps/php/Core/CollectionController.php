<?php

namespace MapSVG;

/**
 * Base controller for collections that support CRUD, CSV import, and Google
 * Sheets bi-directional sync (objects and regions).
 *
 * MapController does not extend this class because maps are not collections
 * and do not have CSV import or AppScript sync.
 *
 * Subclasses (ObjectsController, RegionsController) are kept as empty stubs
 * for backwards compatibility with third-party add-ons that may reference those
 * class names.  All logic lives here.
 *
 * The "post" type meta-sync branch in update/delete is a cheap type guard that
 * is a no-op for regions, so there is no need for a separate subclass.
 */
class CollectionController extends Controller
{
    /**
     * Derives the REST collection segment ("objects" or "regions") from a
     * schema's type field.  Used to build sync URLs and temp-file suffixes.
     *
     * @param Schema $schema
     * @return string
     */
    private static function collectionFromSchema(Schema $schema): string
    {
        return $schema->type === 'region' ? 'regions' : 'objects';
    }

    /** WP-Cron hook name for background CSV batch processing. */
    const CSV_CRON_HOOK = 'mapsvg_csv_process_batch';

    // ──────────────────────────────────────────────────────────────────────────
    // Standard CRUD
    // ──────────────────────────────────────────────────────────────────────────

    public static function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo  = RepositoryFactory::get($request['_collection_name']);
        if (!$repo) {
            return static::render(['error' => 'Collection not found.'], 400);
        }

        $query = new Query($request->get_params());

        $response = $repo->find($query);

        if ($query->withSchema) {
            $response['schema'] = $repo->getSchema();
        }
        return static::render($response, 200);
    }

    public static function get(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo   = RepositoryFactory::get($request['_collection_name']);
        $name   = $repo->schema->objectNameSingular;
        $object = $repo->findById($request['id']);

        if ($object) {
            return static::render([$name => $object], 200);
        }
        return static::render(['message' => ucfirst($name) . ' not found'], 404);
    }

    public static function create(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($err = static::rejectIfGsReadOnly($request)) return $err;

        $repo   = RepositoryFactory::get($request['_collection_name']);
        $name   = $repo->schema->objectNameSingular;

        if (empty($request[$name])) {
            return static::render([], 400);
        }

        $object = $repo->create($request[$name]);

        $settings = ImportSettingsService::getForSchema($repo->schema);
        if (($settings['gsSyncMode'] ?? 'r') === 'w' && !empty($settings['gsSecret'])) {
            GoogleSheetAppScript::push($object->getData(), 'upsert', $repo->schema);
        }

        return static::render([$name => $object], 200);
    }

    public static function clear(\WP_REST_Request $request): \WP_REST_Response
    {
        // Don't reject if GS is read-only, because we're clearing the database.
        // if ($err = static::rejectIfGsReadOnly($request)) return $err;

        $repo = RepositoryFactory::get($request['_collection_name']);
        $repo->clear();
        return static::render([], 200);
    }

    /**
     * Permanently delete orphaned region records and detach r2o links.
     * Regions only — returns 400 for non-region collections.
     */
    public static function deleteOrphans(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo = RepositoryFactory::get($request['_collection_name']);
        if (!$repo) {
            return static::render(['error' => 'Collection not found.'], 400);
        }

        $schema = $repo->getSchema();
        if (!$schema || $schema->type !== 'region') {
            return static::render(['error' => 'Orphan cleanup is only available for region collections.'], 400);
        }

        if (!($repo instanceof RegionsRepository)) {
            return static::render(['error' => 'Invalid regions repository.'], 400);
        }

        $result = $repo->deleteOrphaned();
        return static::render($result, 200);
    }

    public static function update(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($err = static::rejectIfGsReadOnly($request)) return $err;

        $repo   = RepositoryFactory::get($request['_collection_name']);
        $name   = $repo->schema->objectNameSingular;
        $schema = $repo->getSchema();

        $object = $repo->findById($request[$name]['id']);
        $object->update($request[$name]);
        $repo->update($object);

        // WP Post CPT: sync the location meta field (objects only via posts_ naming convention).
        if ($schema->type === "post") {
            $objectData = $object->getData();
            if (!empty($objectData['post'])) {
                if ($request[$name]['location']) {
                    update_post_meta($objectData['post']->id, 'mapsvg_location', wp_json_encode($objectData['location'], JSON_UNESCAPED_UNICODE));
                } else {
                    delete_post_meta($objectData['post']->id, 'mapsvg_location');
                }
            }
        }

        $settings = ImportSettingsService::getForSchema($schema);
        if (($settings['gsSyncMode'] ?? 'r') === 'w' && !empty($settings['gsSecret'])) {
            GoogleSheetAppScript::push($object->getData(), 'upsert', $schema);
        }

        return static::render([], 200);
    }

    public static function delete(\WP_REST_Request $request): \WP_REST_Response
    {
        if ($err = static::rejectIfGsReadOnly($request)) return $err;

        $repo   = RepositoryFactory::get($request['_collection_name']);
        $name   = $repo->schema->objectNameSingular;
        $schema = $repo->getSchema();
        $object = $repo->findById($request['id']);

        // WP Post CPT: remove the location meta field when the object is deleted.
        if ($schema->type === "post") {
            $objectData = $object ? $object->getData() : [];
            if (!empty($objectData['post'])) {
                if (!empty($request[$name]['location'])) {
                    update_post_meta($objectData['post']->id, 'mapsvg_location', wp_json_encode($objectData['location'], JSON_UNESCAPED_UNICODE));
                } else {
                    delete_post_meta($objectData['post']->id, 'mapsvg_location');
                }
            }
        }

        $settings = ImportSettingsService::getForSchema($schema);
        if (($settings['gsSyncMode'] ?? 'r') === 'w' && !empty($settings['gsSecret'])) {
            $idFieldName = $settings['gsIdFieldName'] ?? $schema->getPrimaryKeyFieldName();
            $rowData = $object ? $object->getData() : [$idFieldName => $request['id']];
            GoogleSheetAppScript::push($rowData, 'delete', $schema);
        }

        $repo->delete($request['id']);
        return static::render([], 200);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // JSON import (legacy, chunked client-side approach)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Imports data from a JSON payload.
     * For objects, geocoding errors are surfaced to the caller.
     * For regions, geocoding params are absent in the request so they default to false.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function import(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo = RepositoryFactory::get($request['_collection_name']);
        $name = $repo->schema->objectNamePlural;
        $data = json_decode($request[$name], true);
        $convertLatlngToAddress = filter_var($request['convertLatlngToAddress'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $repo->import($data, $convertLatlngToAddress);

        if (isset($repo->geocodingErrors) && count($repo->geocodingErrors) > 0) {
            return static::render(['error' => ['geocodingError' => $repo->geocodingErrors]], 400);
        }

        return static::render([], 200);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CSV file upload import (async, chunked server-side)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Accepts a raw CSV file upload, initialises a background import job, and
     * returns HTTP 202 immediately.
     *
     * The browser drives processing by polling importCsvProcess().  WP Cron is
     * also scheduled as a fallback so the import continues even if the tab is
     * closed before it finishes.
     *
     * Geocoding params are optional; if absent (e.g. regions) they default to
     * false so the import works without modification for both collections.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function importCsv(\WP_REST_Request $request): \WP_REST_Response
    {
        $preflightToken = sanitize_text_field($request['preflightToken'] ?? '');
        if ($preflightToken !== '') {
            return static::startImportFromPreflight($request, $preflightToken);
        }

        $files = $request->get_file_params();

        if (empty($files['csv']) || $files['csv']['error'] !== UPLOAD_ERR_OK) {
            $code    = isset($files['csv']['error']) ? (int) $files['csv']['error'] : -1;
            $message = static::uploadErrorMessage($code);
            return static::render(['error' => $message], 400);
        }

        $file = $files['csv'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            return static::render(['error' => 'Only CSV files are allowed.'], 400);
        }

        $csvDir    = MAPSVG_UPLOADS_DIR . DIRECTORY_SEPARATOR . 'csv';
        wp_mkdir_p($csvDir);
        $savedPath = $csvDir . DIRECTORY_SEPARATOR . 'import_' . time() . '_'
            . wp_unique_filename($csvDir, sanitize_file_name($file['name']));

        if (!move_uploaded_file($file['tmp_name'], $savedPath)) {
            return static::render(['error' => 'Could not save uploaded file.'], 500);
        }

        return static::initImportJob($savedPath, $request['_collection_name'], [
            'convertLatlngToAddress' => filter_var($request['convertLatlngToAddress'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'convertAddressToLatLng' => filter_var($request['convertAddressToLatLng'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'paidGeocoding'          => filter_var($request['paidGeocoding'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'regionsTableName'       => sanitize_key($request['regionsTableName'] ?? ''),
        ]);
    }

    public static function preflightCsv(\WP_REST_Request $request): \WP_REST_Response
    {
        ImportSettingsService::clearExpiredPreflightFiles();
        $repo = RepositoryFactory::get($request['_collection_name']);
        if (!$repo || !$repo->schema) {
            return static::render(['error' => 'Collection not found.'], 404);
        }

        $rawIdField = $request['idFieldName'] ?? null;
        if ($rawIdField !== null) {
            $rawIdField = sanitize_text_field((string) $rawIdField);
        }
        $idFieldOverride = GoogleSheetSync::normalizeOverrideIdField($rawIdField);
        $idFieldName     = $idFieldOverride ?? $repo->schema->getPrimaryKeyFieldName();
        $csvPath = '';
        $sourceType = 'upload';
        $sourceUrl = null;

        $files = $request->get_file_params();
        if (!empty($files['csv']) && ($files['csv']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $csvDir    = MAPSVG_UPLOADS_DIR . DIRECTORY_SEPARATOR . 'csv';
            wp_mkdir_p($csvDir);
            $csvPath = $csvDir . DIRECTORY_SEPARATOR . 'preflight_' . time() . '_'
                . wp_unique_filename($csvDir, sanitize_file_name($files['csv']['name']));
            if (!move_uploaded_file($files['csv']['tmp_name'], $csvPath)) {
                return static::render(['error' => 'Could not save uploaded file.'], 500);
            }
        } else {
            $sourceType = 'remote';
            $sourceUrl = isset($request['csvUrl']) ? trim((string)$request['csvUrl']) : '';
            if ($sourceUrl === '') {
                return static::render(['error' => 'csvUrl or CSV file is required.'], 400);
            }
            $httpResponse = SafeRemoteUrl::get($sourceUrl, ['timeout' => 30]);
            if (is_wp_error($httpResponse)) {
                $status = in_array(
                    $httpResponse->get_error_code(),
                    array('mapsvg_unsafe_url', 'mapsvg_invalid_url'),
                    true
                ) ? 400 : 502;
                return static::render([
                    'error' => SafeRemoteUrl::publicErrorMessage(
                        $httpResponse,
                        'Failed to fetch CSV.'
                    ),
                ], $status);
            }
            if (wp_remote_retrieve_response_code($httpResponse) !== 200) {
                return static::render(['error' => 'Remote server returned non-200 response.'], 502);
            }
            $csvContent = wp_remote_retrieve_body($httpResponse);
            if ($csvContent === '') {
                return static::render(['error' => 'Remote CSV is empty.'], 400);
            }
            $csvDir = MAPSVG_UPLOADS_DIR . DIRECTORY_SEPARATOR . 'csv';
            wp_mkdir_p($csvDir);
            $csvPath = $csvDir . DIRECTORY_SEPARATOR . 'preflight_url_' . sanitize_key($repo->schema->name) . '_' . time() . '.csv';
            if (file_put_contents($csvPath, $csvContent) === false) {
                return static::render(['error' => 'Could not write temporary CSV file.'], 500);
            }
        }

        try {
            /** @var DbDataSource $source */
            $source = $repo->source;
            $importer = new CsvImporter($repo->schema, $source);
            $init = $importer->initialize($csvPath);
            $validation = static::validateCsvIds($csvPath, $init['separator'], $init['headers'], $idFieldName);
            $meta = [
                'headers' => $init['headers'],
                'separator' => $init['separator'],
                'dataOffset' => (int) $init['data_offset'],
                'rowCount' => (int) $init['total'],
                'idField' => $idFieldOverride,
                'idProfile' => $validation['idProfile'],
                'emptyIdCount' => $validation['emptyIdCount'],
                'duplicateIdCount' => $validation['duplicateIdCount'],
                'duplicateSample' => $validation['duplicateSample'],
                'sourceType' => $sourceType,
                'sourceUrl' => $sourceUrl,
                'filePath' => $csvPath,
                'fileHash' => md5_file($csvPath) ?: null,
                'warnings' => $validation['warnings'],
            ];
            $saved = ImportSettingsService::savePreflight($repo->schema, $meta, 2 * HOUR_IN_SECONDS);
            return static::render([
                'preflightToken' => $saved['preflightToken'] ?? null,
                'preflightExpiresAt' => $saved['preflightExpiresAt'] ?? null,
                'summary' => $meta,
            ], 200);
        } catch (\Exception $e) {
            if ($csvPath !== '' && file_exists($csvPath)) {
                @unlink($csvPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            }
            return static::render(['error' => $e->getMessage()], 500);
        }
    }

    private static function startImportFromPreflight(\WP_REST_Request $request, string $preflightToken): \WP_REST_Response
    {
        $repo = RepositoryFactory::get($request['_collection_name']);
        if (!$repo || !$repo->schema) {
            return static::render(['error' => 'Collection not found.'], 404);
        }
        $preflight = ImportSettingsService::getValidPreflight($repo->schema, $preflightToken);
        if (!$preflight) {
            return static::render(['error' => 'Preflight token is invalid or expired.'], 400);
        }
        $meta = (array) ($preflight['preflightMetaDecoded'] ?? []);
        $filePath = (string) ($preflight['preflightFilePath'] ?? '');
        if ($filePath === '' || !file_exists($filePath)) {
            ImportSettingsService::clearPreflight($repo->schema, false);
            return static::render(['error' => 'Preflight file is missing.'], 400);
        }

        $mode = sanitize_text_field($request['preflightResolution'] ?? '');
        if ($mode === 'clear') {
            $repo->clear();
        } elseif ($mode === 'convert_to_string') {
            if (((int) ($meta['emptyIdCount'] ?? 0)) > 0 || ((int) ($meta['duplicateIdCount'] ?? 0)) > 0) {
                return static::render(['error' => 'Cannot convert IDs: CSV has empty or duplicate ID values.'], 400);
            }
            static::migratePrimaryKeyToString($repo->schema);
        }

        if (!ImportSettingsService::consumePreflight($repo->schema, $preflightToken)) {
            return static::render(['error' => 'Could not lock preflight artifact.'], 409);
        }

        return static::initImportJob($filePath, $request['_collection_name'], [
            'convertLatlngToAddress' => filter_var($request['convertLatlngToAddress'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'convertAddressToLatLng' => filter_var($request['convertAddressToLatLng'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'paidGeocoding' => filter_var($request['paidGeocoding'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'regionsTableName' => sanitize_key($request['regionsTableName'] ?? ''),
            'preflightToken' => $preflightToken,
            'preflightMeta' => $meta,
        ]);
    }

    /**
     * Shared helper: initialises a background CSV import job from a file already on disk.
     * Called by both importCsv() (file upload) and importCsvFromUrl() (remote download).
     *
     * @param string $savedPath     Absolute path to the CSV file on disk.
     * @param string $collection    Schema / collection name.
     * @param array  $params        Geocoding and import options.
     * @return \WP_REST_Response    HTTP 202 with token + total, or 500 on error.
     */
    private static function initImportJob(string $savedPath, string $collection, array $params): \WP_REST_Response
    {
        try {
            $repo     = RepositoryFactory::get($collection);
            $preflightMeta = $params['preflightMeta'] ?? null;
            if (is_array($preflightMeta) && !empty($preflightMeta['separator']) && !empty($preflightMeta['headers'])) {
                $init = [
                    'separator' => $preflightMeta['separator'],
                    'headers' => $preflightMeta['headers'],
                    'data_offset' => (int) ($preflightMeta['dataOffset'] ?? 0),
                    'total' => (int) ($preflightMeta['rowCount'] ?? 0),
                ];
            } else {
                /** @var DbDataSource $source */
                $source   = $repo->source;
                $importer = new CsvImporter($repo->schema, $source);
                $init     = $importer->initialize($savedPath);
            }

            $init['headers'] = array_map(
                [CsvImporter::class, 'normalizeHeader'],
                array_map('strval', $init['headers'] ?? [])
            );

            (new ImportLogRepository())->clearForSchema($collection);

            $preflightIdField = GoogleSheetSync::resolveOverrideIdFieldFromPreflight(
                is_array($preflightMeta) ? $preflightMeta : null
            );

            $token = CsvImportJob::create([
                'file'                   => $savedPath,
                'collection'             => $collection,
                'separator'              => $init['separator'],
                'headers'                => $init['headers'],
                'current_offset'         => $init['data_offset'],
                'total'                  => $init['total'],
                'upsert'                 => GoogleSheetSync::resolveImportMode($repo->schema, false, $preflightIdField) === GoogleSheetSync::MODE_UPSERT,
                'convertLatlngToAddress' => $params['convertLatlngToAddress'] ?? false,
                'convertAddressToLatLng' => $params['convertAddressToLatLng'] ?? false,
                'paidGeocoding'          => $params['paidGeocoding'] ?? false,
                'regionsTableName'       => $params['regionsTableName'] ?? '',
                'language'               => $repo->schema->getLocationLanguage(),
                'preflight_token'        => $params['preflightToken'] ?? null,
            ]);

            $estimated = SchemaImportLifecycle::estimateSeconds(
                (int) $init['total'],
                (bool) ($params['convertLatlngToAddress'] ?? false),
                (bool) ($params['convertAddressToLatLng'] ?? false),
                (bool) ($params['paidGeocoding'] ?? false)
            );
            SchemaImportLifecycle::beginImport($collection, $estimated);
        } catch (\Exception $e) {
            @unlink($savedPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            return static::render(['error' => $e->getMessage()], 500);
        }

        wp_schedule_single_event(time() + 10, static::CSV_CRON_HOOK, [$token]);

        return static::render([
            'token'  => $token,
            'total'  => $init['total'],
            'status' => 'pending',
        ], 202);
    }

    /**
     * Processes one chunk of a background CSV import job (called by the browser).
     * Returns progress so the client can update its UI and call again.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function importCsvProcess(\WP_REST_Request $request): \WP_REST_Response
    {
        $token = sanitize_text_field($request['token'] ?? '');
        $job   = CsvImportJob::get($token);

        if ($job === null) {
            return static::render(['error' => 'Import job not found. It may have expired.'], 404);
        }

        if (in_array($job['status'], ['complete', 'failed'], true)) {
            return static::render($job, 200);
        }

        $result = static::runBatch($token, $job);

        if (!wp_next_scheduled(static::CSV_CRON_HOOK, [$token])) {
            wp_schedule_single_event(time() + 30, static::CSV_CRON_HOOK, [$token]);
        }

        return static::render($result, 200);
    }

    /**
     * WP Cron handler: processes one chunk then reschedules itself until done.
     * Fires as a fallback when the browser tab is closed mid-import.
     */
    public static function importCsvCron(string $token): void
    {
        $job = CsvImportJob::get($token);
        if ($job === null || in_array($job['status'], ['complete', 'failed'], true)) {
            return;
        }

        $lockKey = 'mapsvg_csv_lock_' . $token;
        if (get_transient($lockKey)) {
            wp_schedule_single_event(time() + 15, static::CSV_CRON_HOOK, [$token]);
            return;
        }
        set_transient($lockKey, 1, 60);

        $job    = CsvImportJob::get($token);
        $result = static::runBatch($token, $job);

        delete_transient($lockKey);

        if ($result['status'] !== 'complete' && $result['status'] !== 'failed') {
            wp_schedule_single_event(time(), static::CSV_CRON_HOOK, [$token]);
        }
    }

    /**
     * @return array{idProfile:string,emptyIdCount:int,duplicateIdCount:int,duplicateSample:array,warnings:array}
     */
    private static function validateCsvIds(string $filePath, string $separator, array $headers, string $idFieldName): array
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \Exception('Cannot open CSV file for preflight.');
        }

        // Skip headers row
        fgetcsv($handle, 0, $separator, '"', '');

        $empty = 0;
        $dupes = 0;
        $sample = [];
        $seen = [];
        $numeric = 0;
        $string = 0;

        while (($row = fgetcsv($handle, 0, $separator, '"', '')) !== false) {
            $raw = array_combine($headers, array_pad($row, count($headers), ''));
            $value = trim((string) ($raw[$idFieldName] ?? ''));
            if ($value === '') {
                $empty++;
                continue;
            }
            if (ctype_digit($value)) {
                $numeric++;
            } else {
                $string++;
            }
            if (isset($seen[$value])) {
                $dupes++;
                if (count($sample) < 20) {
                    $sample[] = $value;
                }
            } else {
                $seen[$value] = 1;
            }
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($handle);

        $profile = 'empty';
        if ($numeric > 0 && $string > 0) {
            $profile = 'mixed';
        } elseif ($string > 0) {
            $profile = 'string';
        } elseif ($numeric > 0) {
            $profile = 'numeric';
        }

        $warnings = [];
        if ($empty > 0) {
            $warnings[] = 'Some rows have empty ID values.';
        }
        if ($dupes > 0) {
            $warnings[] = 'Duplicate IDs detected.';
        }

        return [
            'idProfile' => $profile,
            'emptyIdCount' => $empty,
            'duplicateIdCount' => $dupes,
            'duplicateSample' => $sample,
            'warnings' => $warnings,
        ];
    }

    private static function migratePrimaryKeyToString(Schema $schema): void
    {
        $db = Database::get();
        $table = $db->mapsvg_prefix . $schema->name;
        $pk = $schema->getPrimaryKeyFieldName();
        $column = $db->get_row("SHOW COLUMNS FROM `{$table}` LIKE '{$pk}'");
        if (!$column) {
            throw new \Exception('Primary key column not found.');
        }
        if (stripos((string) $column->Type, 'varchar') !== false) {
            return;
        }
        $db->query("ALTER TABLE `{$table}` MODIFY COLUMN `{$pk}` VARCHAR(255) NOT NULL");

        $schemaRepo = RepositoryFactory::get('schema');
        $schemaObj = $schemaRepo->findByName($schema->name);
        if ($schemaObj) {
            $fields = (array) $schemaObj->fields;
            foreach ($fields as &$field) {
                if (isset($field->name) && $field->name === $pk && isset($field->type) && $field->type === 'id') {
                    $field->db_type = 'varchar(255)';
                    $field->auto_increment = false;
                }
            }
            $schemaRepo->update([
                'id' => $schemaObj->id,
                'fields' => wp_json_encode($fields, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /**
     * Reads next chunk from the CSV file, inserts rows, updates job state, and
     * returns a status array.
     *
     * @param string              $token
     * @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private static function runBatch(string $token, array $job): array
    {
        try {
            $repo = RepositoryFactory::get($job['collection']);
            /** @var DbDataSource $source */
            $source   = $repo->source;
            $importer = new CsvImporter($repo->schema, $source);

            $chunk = $importer->importChunk(
                $job['file'],
                $job['separator'],
                $job['headers'],
                (int) $job['current_offset'],
                (bool) ($job['convertLatlngToAddress'] ?? false),
                (bool) ($job['convertAddressToLatLng'] ?? false),
                (string) ($job['regionsTableName'] ?? ''),
                (bool) ($job['upsert'] ?? true)
            );
        } catch (\Exception $e) {
            CsvImportJob::update($token, ['status' => 'failed', 'error' => $e->getMessage()]);
            if (!empty($job['preflight_token']) && isset($repo) && $repo && $repo->schema) {
                ImportSettingsService::updateForSchema($repo->schema, ['preflightStatus' => 'failed']);
            }
            SchemaImportLifecycle::resetInProgress($job['collection']);
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }

        $errorCount     = (int) ($job['error_count'] ?? 0);
        $errors         = CsvImportJob::mergeErrors($job['errors'] ?? [], $chunk['errors'], $errorCount);
        $processed      = (int) $job['processed'] + $chunk['rows_processed'];
        $needsGeocoding = ($job['needs_geocoding'] ?? false) || $chunk['needs_geocoding'];
        $done           = $chunk['eof'];

        // Persist per-batch errors to the permanent log table immediately
        if (!empty($chunk['errors'])) {
            $logRepo = new ImportLogRepository();
            foreach ($chunk['errors'] as $err) {
                $logRepo->upsert($job['collection'], $err['message'], 'error');
            }
        }

        if ($done) {
            if (!empty($job['preflight_token'])) {
                ImportSettingsService::clearPreflight($repo->schema, true);
            } else {
                @unlink($job['file']); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            }

            $geocodingQueued = false;
            if ($needsGeocoding) {
                GeocodingQueue::add(
                    $job['collection'],
                    $job['language'] ?? 'en',
                    (bool) ($job['convertLatlngToAddress'] ?? false),
                    (bool) ($job['paidGeocoding'] ?? false),
                    (bool) ($job['convertAddressToLatLng'] ?? true)
                );
                $geocodingQueued = true;
            }

            $repo->setRelationsForAllObjects();
            CsvImportJob::delete($token);

            $elapsed = time() - (int) ($job['started_at'] ?? time());
            $logRepo = new ImportLogRepository();
            $logRepo->upsert($job['collection'], "Imported {$processed} rows", 'info');
            $logRepo->upsert($job['collection'], "Import took {$elapsed} seconds", 'info');
            if ($geocodingQueued) {
                $logRepo->upsert($job['collection'], 'Geocoding queued', 'info');
            }

            $importedAt = SchemaImportLifecycle::completeImport($job['collection']);

            return [
                'status'           => 'complete',
                'processed'        => $processed,
                'total'            => $job['total'],
                'geocoding_queued' => $geocodingQueued,
                'errors'           => $errors,
                'error_count'      => $errorCount,
                'importedAt'       => $importedAt,
            ];
        }

        SchemaImportLifecycle::touchProgress($job['collection']);

        CsvImportJob::update($token, [
            'status'          => 'processing',
            'current_offset'  => $chunk['next_offset'],
            'processed'       => $processed,
            'needs_geocoding' => $needsGeocoding,
            'errors'          => $errors,
            'error_count'     => $errorCount,
        ]);

        return [
            'status'      => 'processing',
            'processed'   => $processed,
            'total'       => $job['total'],
            'errors'      => $errors,
            'error_count' => $errorCount,
        ];
    }

    /**
     * Returns the current geocoding queue status (pending row counts per table).
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function geocodeStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        return static::render(['queue' => GeocodingQueue::getStatus()], 200);
    }

    public static function getDistinctValues(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo           = RepositoryFactory::get($request['_collection_name']);
        $distinctValues = $repo->getDistinctValues($request['_field_name']);
        return static::render(['items' => $distinctValues], 200);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Remote CSV import (auto-sync tab)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Fetches a remote CSV URL and imports it synchronously.
     * Used for the "Import now" button in the Remote CSV auto-sync tab.
     *
     * Expects JSON body: { csvUrl: string }
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function importCsvFromUrl(\WP_REST_Request $request): \WP_REST_Response
    {
        $preflightToken = sanitize_text_field($request['preflightToken'] ?? '');
        if ($preflightToken !== '') {
            return static::startImportFromPreflight($request, $preflightToken);
        }

        $body   = $request->get_params();
        $csvUrl = isset($body['csvUrl']) ? trim((string) $body['csvUrl']) : '';

        if ($csvUrl === '') {
            return static::render(['error' => 'csvUrl is required.'], 400);
        }

        // Download the remote CSV to a temp file on disk, then hand off to the
        // shared async batch system (same path as a file upload).
        $httpResponse = SafeRemoteUrl::get($csvUrl, ['timeout' => 30]);

        if (is_wp_error($httpResponse)) {
            $status = in_array(
                $httpResponse->get_error_code(),
                array('mapsvg_unsafe_url', 'mapsvg_invalid_url'),
                true
            ) ? 400 : 502;
            return static::render([
                'error' => SafeRemoteUrl::publicErrorMessage($httpResponse, 'Failed to fetch CSV.'),
            ], $status);
        }

        if (wp_remote_retrieve_response_code($httpResponse) !== 200) {
            return static::render(['error' => 'Remote server returned non-200 response.'], 502);
        }

        $csvContent = wp_remote_retrieve_body($httpResponse);
        if (empty($csvContent)) {
            return static::render(['error' => 'Remote CSV is empty.'], 400);
        }

        $csvDir    = MAPSVG_UPLOADS_DIR . DIRECTORY_SEPARATOR . 'csv';
        wp_mkdir_p($csvDir);
        $tmpPath = $csvDir . DIRECTORY_SEPARATOR . 'url_import_' . time() . '.csv';

        if (file_put_contents($tmpPath, $csvContent) === false) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            return static::render(['error' => 'Could not write temporary CSV file.'], 500);
        }

        return static::initImportJob($tmpPath, $request['_collection_name'], [
            'convertLatlngToAddress' => filter_var($body['convertLatlngToAddress'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'convertAddressToLatLng' => filter_var($body['convertAddressToLatLng'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'paidGeocoding'          => filter_var($body['paidGeocoding'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // AppScript bi-directional sync
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Receives a row push from AppScript (bi-directional sync).
     * Authenticated via HMAC-SHA256 + 30-second timestamp window.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function sync(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo = RepositoryFactory::get($request['_collection_name']);
        if (!GoogleSheetAppScript::verifyRequest($request, $repo->schema)) {
            return static::render(['error' => 'Unauthorized'], 401);
        }
        $body   = $request->get_params();
        $action = $body['action'] ?? 'upsert';
        $row    = $body['row'] ?? [];
        if (empty($row)) {
            return static::render(['error' => 'No row data provided'], 400);
        }
        if ($action === 'delete') {
            $settings = ImportSettingsService::getForSchema($repo->schema);
            $idFieldName = $settings['gsIdFieldName'] ?? $repo->schema->getPrimaryKeyFieldName();
            $idValue     = $row[$idFieldName] ?? null;
            if ($idValue !== null) {
                $repo->delete($idValue);
            }
            return static::render([], 200);
        }

        $object = GoogleSheetAppScript::upsertRow($row, $repo, $repo->schema);
        $name   = $repo->schema->objectNameSingular;
        return static::render([$name => $object->getData()], 200);
    }

    /**
     * One-time AppScript setup: exchanges the one-time setup key for a shared
     * HMAC secret and stores it encrypted in the schema.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function setupAppScript(\WP_REST_Request $request): \WP_REST_Response
    {
        $body         = $request->get_params();
        $setupKey     = $body['setupKey'] ?? '';
        $appScriptUrl = isset($body['appScriptUrl']) ? esc_url_raw(trim($body['appScriptUrl'])) : '';
        if (empty($setupKey) || empty($appScriptUrl)) {
            return static::render(['error' => 'setupKey and appScriptUrl are required.'], 400);
        }
        $repo    = RepositoryFactory::get($request['_collection_name']);
        $syncUrl = GoogleSheetAppScript::buildSyncUrl(static::collectionFromSchema($repo->schema), $repo->schema->name);
        $result  = GoogleSheetAppScript::setup($setupKey, $appScriptUrl, $repo->schema, $syncUrl);
        if (!empty($result['error'])) {
            return static::render(['error' => $result['error']], 502);
        }
        return static::render(['ok' => true], 200);
    }

    /**
     * Reset AppScript connection: sends a signed reset action to AppScript
     * (which regenerates its setup key), then clears the secret from the schema.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function resetAppScript(\WP_REST_Request $request): \WP_REST_Response
    {
        $repo   = RepositoryFactory::get($request['_collection_name']);
        $result = GoogleSheetAppScript::reset($repo->schema);
        if (!empty($result['error'])) {
            return static::render(['error' => $result['error']], 502);
        }
        return static::render(['ok' => true], 200);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Shared helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Translates a PHP file-upload error code into a human-readable message.
     *
     * @param int $code
     * @return string
     */
    protected static function uploadErrorMessage(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                return sprintf(
                    'File is too large. Your server limits: upload_max_filesize = %s, post_max_size = %s. '
                        . 'Increase these values in php.ini or ask your hosting provider.',
                    ini_get('upload_max_filesize'),
                    ini_get('post_max_size')
                );
            case UPLOAD_ERR_FORM_SIZE:
                return 'File exceeds the maximum form upload size.';
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded. Please try again.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was selected for upload.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Server error: missing temporary upload directory. Contact your hosting provider.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Server error: failed to write the uploaded file to disk. Check directory permissions.';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension blocked the upload. Contact your hosting provider.';
            default:
                return 'Unexpected upload error (code ' . $code . ').';
        }
    }
}
