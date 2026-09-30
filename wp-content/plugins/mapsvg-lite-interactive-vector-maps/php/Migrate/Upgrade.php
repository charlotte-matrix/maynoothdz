<?php

namespace MapSVG;

/**
 * Class that recursively updates mapsvg, starting from the lowest version
 * and going up through all updates until the last version.
 */
class Upgrade
{

    protected $db;


    public function __construct()
    {
        $this->db = Database::get();
    }

    /**
     * Checks the mapsvg version and upgrades it if necessary
     */
    function run()
    {

        $dbVersion = $this->isSettingsTableExists() ? Options::get('db_version') : '1.0.0';


        


        // Check if the current map version is outdated
        if (is_null($dbVersion) || version_compare($dbVersion, MAPSVG_VERSION, '<')) {
            // Get all migration files with version numbers higher than the current version
            $migrations = $this->getPendingMigrations($dbVersion);


            // Apply each migration to the map options
            foreach ($migrations as $migrationFile) {
                try {
                    $this->applyMigration($migrationFile);
                } catch (\Exception $e) {
                    // Optionally log the error or handle it as needed
                    Logger::error("[SERVER-005] Migration failed: " . $e->getMessage() . " — Read more: https://mapsvg.com/docs/errors#SERVER-005");
                    break;
                }
            }

            Options::set('db_version', MAPSVG_VERSION);
        }
    }

    /**
     * @return string|null
     */
    public function isSettingsTableExists()
    {
        $settings_table_exists = $this->db->get_var('SHOW TABLES LIKE \'' . $this->db->mapsvg_prefix . 'settings\'');
        return $settings_table_exists;
    }


    private function getPendingMigrations($currentVersion)
    {
        $migrations = [];
        $migrationDir = __DIR__ . DIRECTORY_SEPARATOR . 'Migrations';



        if (!is_dir($migrationDir)) {
            Logger::error("[SERVER-006] MapSVG: Migration directory does not exist: $migrationDir — Read more: https://mapsvg.com/docs/errors#SERVER-006");
            return $migrations;
        }

        if (!is_readable($migrationDir)) {
            Logger::error("[SERVER-007] MapSVG: Migration directory is not readable: $migrationDir — Read more: https://mapsvg.com/docs/errors#SERVER-007");
            return $migrations;
        }

        $globPattern = $migrationDir . DIRECTORY_SEPARATOR . '*.php';
        $files = glob($globPattern);

        if ($files === false) {
            Logger::error("[SERVER-008] MapSVG: glob() failed for pattern: $globPattern — Read more: https://mapsvg.com/docs/errors#SERVER-008");
            return $migrations;
        }

        if (empty($files)) {
            Logger::error("[SERVER-009] MapSVG: No migration files found in: $migrationDir — Read more: https://mapsvg.com/docs/errors#SERVER-009");
            return $migrations;
        }

        foreach ($files as $file) {
            $version = basename($file, '.php');

            if (is_null($currentVersion) || version_compare($version, $currentVersion, '>')) {
                $migrations[$version] = $file;
            }
        }


        // Sort migrations by version
        uksort($migrations, 'version_compare');

        return $migrations;
    }

    private function applyMigration($migrationFile)
    {
        $migration = require $migrationFile;

        if (is_callable($migration)) {
            $migration();
        } else {
            Logger::error("[SERVER-010] MapSVG: Migration file is not callable: $migrationFile — Read more: https://mapsvg.com/docs/errors#SERVER-010");
        }
    }
}
