# CSV import — agent notes

This folder holds **server-side CSV streaming and parsing**. Orchestration (REST, jobs, Google Sheets, secrets) lives elsewhere but calls into these classes.

For **database DDL** (e.g. `import_settings`, dropping `gs*` from `schema`), follow [php/Migrate/Migrations/AGENTS.md](../../Migrate/Migrations/AGENTS.md) and apply changes in `next.php`.

---

## What lives in `php/Core/Csv`

| Piece | Role |
|-------|------|
| `CsvImporter.php` | Opens the file, sniffs delimiter, reads headers, streams rows in batches. Supports **single-shot** `import()` and **chunked** `initialize()` + `importChunk()` (byte offset resume). Builds a `CsvRowParser` and calls `DbDataSource::import()` per batch. |
| `CsvRowParser.php` | Maps each CSV row (header → string) to DB columns using **field-type parsers** in registration order; first `supports()` wins, else `DefaultFieldParser`. |
| `FieldParser/*.php` | Type-specific parsing (`location`, `select`, `region`, `image`, dates, etc.). Implement `FieldParserInterface`. |
| `CsvImportJob.php` | Background job state in **WordPress transients** (`mapsvg_csv_job_*`), TTL ~24h: `pending` → `processing` → `complete` \| `failed`. |

Extension hooks:

- `mapsvg_csv_register_field_parsers` — receives the `CsvRowParser` (see `CsvImporter::buildRowParser()`).
- Filters/actions named in `CsvImporter` / `CsvRowParser` docblocks should be preferred over forking parsers when possible.

---

## End-to-end flow (admin CSV upload / URL)

1. **Preflight (optional but recommended for large files)** — `CollectionController::preflightCsv()`  
   - Saves CSV under `MAPSVG_UPLOADS_DIR/csv/` (`preflight_*` or `preflight_url_*`).  
   - `CsvImporter::initialize()` gets delimiter, headers, row count, data byte offset.  
   - `validateCsvIds()` scans **the full file** on the server (empty IDs, duplicates, numeric vs string profile).  
   - `ImportSettingsService::savePreflight()` writes a token + file path + hash + JSON **`preflightMeta`** on the `import_settings` row (hybrid: indexed columns + JSON blob).  

2. **Start import** — `importCsv` / `importCsvFromUrl` with `preflightToken` → `startImportFromPreflight()`  
   - Validates token via `getValidPreflight()`, applies user **`preflightResolution`** if needed (`clear` = truncate collection; `convert_to_string` = PK migration when preflight allows).  
   - `consumePreflight()` sets status to `consumed` so the token cannot be reused.  
   - `initImportJob()` creates a `CsvImportJob` transient and schedules cron + browser-driven `importCsvProcess()`.

3. **Chunk processing** — `runBatch()` uses `CsvImporter::importChunk()` with job-stored separator/headers/offset. Flushes to `DbDataSource::import($batch, $upsert)`.

4. **Cleanup** — On success/failure paths, preflight artifacts may be cleared via `ImportSettingsService::clearPreflight()`. `clearExpiredPreflightFiles()` removes stale rows/files (runs at preflight entry).

---

## Import modes and `upsert` flag

`DbDataSource::import($data, $upsert)`:

- **`upsert = true`** — `INSERT ... ON DUPLICATE KEY UPDATE` (stable primary keys / natural keys).  
- **`upsert = false`** — plain `INSERT` (e.g. staging table fill before table swap).

**Google Sheets auto-refetch** (`GoogleSheetSync`):

- **`hasStableId()`** — `ImportSettingsService` → non-empty `gsIdFieldName` after trim.  
- **`resolveImportMode($schema, $isAutoRefetch, $overrideIdField)`** — three-state override (see below) then persisted `gsIdFieldName`. No usable ID → **snapshot_replace** on auto-refetch, **append** on manual path.  
- **Snapshot replace** uses `importWithTableSwap()`: `CREATE TABLE ... LIKE`, import into staging with `upsert=false`, `RENAME TABLE` swap, drop backup — then `setRelationsForAllObjects()`.

Manual REST imports set job `upsert` from `GoogleSheetSync::resolveImportMode($schema, false, $preflightIdField)`. File-upload ID selection is **source-local** and must not write `gsIdFieldName` (that setting is for remote/Google Sheets). Preflight stores `idField` as:

- **`null` / absent** — no upload override; `initImportJob` may fall back to persisted `gsIdFieldName`
- **`''`** — user chose “No ID in CSV file”; import **must append** and must **not** fall back to `gsIdFieldName`
- **non-empty** — upsert using that column

`preflightCsv` → `initImportJob` → `resolveImportMode` must preserve null vs `''` (do not coerce a missing override to `''`). `CsvImporter::normalizeHeader()` strips a leading UTF-8 BOM so Excel `id` columns still match the schema field.

---

## Import settings vs schema

All **`gs*`** sync/import settings and **preflight** columns live in **`import_settings`** (admin-side repository), not on `Schema`:

- Access via `ImportSettingsService::getForSchema()` / `updateForSchema()`.  
- JSON definition: `php/Core/schema/importSettings.json`.  
- Ensures secrets (`gsSecret`, URLs) are not carried on public schema payloads.

`SchemaImportLifecycle`, `GoogleSheetSync`, `GoogleSheetAppScript`, and `CollectionController` read/write those fields through the service.

---

## Related files (outside this folder)

- `php/Core/CollectionController.php` — preflight, job init, batches, ID validation, PK string migration.  
- `php/Database/DbDataSource.php` — bulk `import()`, truncate, table name.  
- `php/Core/Repository.php` — `importFromCsv()` wraps `CsvImporter` + relation rebuild.  
- `php/Domain/GoogleSheet/GoogleSheetSync.php` — remote fetch, modes, table swap.  
- `php/Domain/ImportSettings/*` — persistence and preflight lifecycle.

Frontend behavior (preflight modal, import-settings API) is documented separately under `js/mapsvg-admin/modules/csv/AGENTS.md` (DOM ID prefixing and tab isolation).

---

## Conventions for changes

- Prefer **streaming** (`fgetcsv`, chunk boundaries) for large files; avoid loading whole CSV into memory.  
- New column types: add a `FieldParser`, register it in `CsvImporter::buildRowParser()`, keep parsers small and composable.  
- Any new persisted import/sync fields belong on **`import_settings`** + migration in `next.php`, not on `schema`.  
- Keep preflight **TTL**, token length, and cleanup behavior aligned with `ImportSettingsRepository` constants and `CollectionController` usage.
