<?php

namespace MapSVG;

class ImportSettingsRepository extends Repository
{
    public static $className = 'ImportSetting';

    private const PREFLIGHT_TTL_SECONDS = 7200;

    public function findBySchemaId(int $schemaId)
    {
        return $this->findOne(['schema_id' => $schemaId]);
    }

    public function findBySchemaName(string $schemaName)
    {
        return $this->findOne(['schema_name' => $schemaName]);
    }

    /**
     * Default import_settings values when no DB row exists yet (no INSERT).
     *
     * @return array<string, mixed>
     */
    public function defaultPayloadForSchema(Schema $schema): array
    {
        return [
            'id'                              => null,
            'schema_id'                       => (int) $schema->id,
            'schema_name'                     => (string) $schema->name,
            'gsSync'                          => 0,
            'gsAutoRefetch'                   => 0,
            'gsSyncMode'                      => 'r',
            'gsCsvUrl'                        => null,
            'gsCsvHash'                       => null,
            'gsRefetchInterval'               => 24,
            'gsAutoId'                        => 0,
            'gsIdFieldName'                   => '',
            'gsSheetName'                     => 'Sheet1',
            'gsGeocode'                       => 0,
            'gsGeocodeConvertLatLngToAddress' => 0,
            'gsGeocodeConvertAddressToLatLng' => 1,
            'gsPaidGeocoding'                 => 0,
            'gsAppScriptUrl'                  => null,
            'gsSecret'                        => null,
            'gsImportFinishedAt'              => null,
            'gsImportStartedAt'               => null,
            'gsImportLastUpdatedAt'           => null,
            'gsImportEstimatedSeconds'        => null,
            'gsImportSource'                  => 'upload',
            'gsImportSourceValid'             => 0,
            'gsImportSkipFields'              => null,
            'preflightToken'                  => null,
            'preflightStatus'                 => null,
            'preflightExpiresAt'              => null,
            'preflightFilePath'               => null,
            'preflightFileHash'               => null,
            'preflightMeta'                   => null,
        ];
    }

    public function upsertBySchema(Schema $schema, array $data = [])
    {
        $defaults = $this->defaultPayloadForSchema($schema);

        $existing = $this->findBySchemaId((int) $schema->id);
        $existingData = [];
        if ($existing) {
            $existingData = is_object($existing) && method_exists($existing, 'getData')
                ? $existing->getData()
                : (array) $existing;
        }

        $payload = array_merge($defaults, $existingData, $data);
        if ($existing && is_object($existing) && isset($existing->id)) {
            $payload['id'] = $existing->id;
        }

        return $this->upsert($payload, 'schema_id');
    }

    public function savePreflight(Schema $schema, array $meta, ?int $ttlSeconds = null): array
    {
        $ttl = max(60, (int) ($ttlSeconds ?? self::PREFLIGHT_TTL_SECONDS));
        $token = wp_generate_password(40, false, false);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttl);
        $payload = [
            'preflightToken' => $token,
            'preflightStatus' => 'ready',
            'preflightExpiresAt' => $expiresAt,
            'preflightFilePath' => $meta['filePath'] ?? null,
            'preflightFileHash' => $meta['fileHash'] ?? null,
            'preflightMeta' => wp_json_encode($meta, JSON_UNESCAPED_UNICODE),
        ];
        $row = $this->upsertBySchema($schema, $payload);
        $data = is_object($row) && method_exists($row, 'getData') ? (array)$row->getData() : (array)$row;
        $data['preflightToken'] = $token;
        $data['preflightExpiresAt'] = $expiresAt;
        return $data;
    }

    public function getValidPreflight(Schema $schema, string $token): ?array
    {
        $row = $this->findBySchemaId((int) $schema->id);
        if (!$row) {
            return null;
        }
        $data = is_object($row) && method_exists($row, 'getData') ? (array)$row->getData() : (array)$row;
        if (($data['preflightToken'] ?? '') !== $token) {
            return null;
        }
        if (($data['preflightStatus'] ?? '') !== 'ready') {
            return null;
        }
        $expires = !empty($data['preflightExpiresAt']) ? strtotime((string)$data['preflightExpiresAt']) : 0;
        if (!$expires || $expires < time()) {
            return null;
        }
        $meta = [];
        if (!empty($data['preflightMeta'])) {
            $decoded = json_decode((string)$data['preflightMeta'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }
        $data['preflightMetaDecoded'] = $meta;
        return $data;
    }

    public function consumePreflight(Schema $schema, string $token): bool
    {
        $row = $this->getValidPreflight($schema, $token);
        if (!$row) {
            return false;
        }
        $this->upsertBySchema($schema, ['preflightStatus' => 'consumed']);
        return true;
    }

    public function clearPreflight(Schema $schema, bool $deleteFile = true): void
    {
        $row = $this->findBySchemaId((int) $schema->id);
        if (!$row) {
            return;
        }
        $data = is_object($row) && method_exists($row, 'getData') ? (array)$row->getData() : (array)$row;
        $file = (string) ($data['preflightFilePath'] ?? '');
        if ($deleteFile && $file !== '' && file_exists($file)) {
            @unlink($file); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
        $this->upsertBySchema($schema, [
            'preflightToken' => null,
            'preflightStatus' => null,
            'preflightExpiresAt' => null,
            'preflightFilePath' => null,
            'preflightFileHash' => null,
            'preflightMeta' => null,
        ]);
    }

    public function clearExpiredPreflightFiles(): int
    {
        $rows = $this->find(new Query(['perpage' => 0]))['items'] ?? [];
        $count = 0;
        foreach ($rows as $row) {
            $data = is_object($row) && method_exists($row, 'getData') ? (array)$row->getData() : (array)$row;
            $expires = !empty($data['preflightExpiresAt']) ? strtotime((string)$data['preflightExpiresAt']) : 0;
            $status = (string) ($data['preflightStatus'] ?? '');
            if (!$expires) {
                continue;
            }
            if ($expires >= time() && !in_array($status, ['expired', 'failed'], true)) {
                continue;
            }
            $schema = new Schema(['id' => (int)$data['schema_id'], 'name' => (string)$data['schema_name']]);
            $this->clearPreflight($schema, true);
            $count++;
        }
        return $count;
    }
}
