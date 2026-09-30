<?php

namespace MapSVG;

class ImportSettingsService
{
    public static function repo(): ImportSettingsRepository
    {
        /** @var ImportSettingsRepository $repo */
        $repo = RepositoryFactory::get('import_settings');
        return $repo;
    }

    /**
     * Returns import settings for the schema. Does not INSERT a row — use
     * {@see updateForSchema()}, {@see savePreflight()}, or repository upsert when persisting.
     */
    public static function getForSchema(Schema $schema): array
    {
        $repo = self::repo();
        $row  = $repo->findBySchemaId((int) $schema->id);
        if (!$row) {
            return $repo->defaultPayloadForSchema($schema);
        }
        return is_object($row) && method_exists($row, 'getData') ? (array) $row->getData() : (array) $row;
    }

    public static function updateForSchema(Schema $schema, array $data): array
    {
        $repo = self::repo();
        $row  = $repo->upsertBySchema($schema, $data);
        return is_object($row) && method_exists($row, 'getData') ? (array) $row->getData() : (array) $row;
    }

    public static function savePreflight(Schema $schema, array $meta, ?int $ttlSeconds = null): array
    {
        return self::repo()->savePreflight($schema, $meta, $ttlSeconds);
    }

    public static function getValidPreflight(Schema $schema, string $token): ?array
    {
        return self::repo()->getValidPreflight($schema, $token);
    }

    public static function consumePreflight(Schema $schema, string $token): bool
    {
        return self::repo()->consumePreflight($schema, $token);
    }

    public static function clearPreflight(Schema $schema, bool $deleteFile = true): void
    {
        self::repo()->clearPreflight($schema, $deleteFile);
    }

    public static function clearExpiredPreflightFiles(): int
    {
        return self::repo()->clearExpiredPreflightFiles();
    }
}
