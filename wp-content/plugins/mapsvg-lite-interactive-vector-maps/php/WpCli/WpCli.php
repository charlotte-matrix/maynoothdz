<?php

namespace MapSVG;

class WpCli
{
  /**
   * Register WP-CLI commands
   */
  public static function addWpCliCommands(): void
  {
    if (!defined('WP_CLI') || !class_exists('\WP_CLI')) {
      return;
    }

    self::registerExportCommand();
    self::registerImportCommand();
  }

  /**
   * Register the export command
   */
  private static function registerExportCommand(): void
  {
    \WP_CLI::add_command('mapsvg export', function ($args, $assoc_args) {
      $db = Database::get();
      global $wpdb;

      $prefix = $wpdb->prefix . MAPSVG_PREFIX;
      $export_file = isset($assoc_args['file'])
        ? $assoc_args['file']
        : 'mapsvg-export-' . date('Y-m-d-His') . '.sql';

      // Check for domain replacement flag
      $to_domain = isset($assoc_args['toDomain']) ? $assoc_args['toDomain'] : null;
      $from_domain = null;
      $replacement_count = 0;

      if ($to_domain) {
        // Get current site domain
        $home_url = home_url();
        $parsed_home = wp_parse_url($home_url);
        $from_domain = $parsed_home['host'] ?? '';

        // Normalize toDomain (remove protocol if present, keep domain only)
        $parsed_to = wp_parse_url($to_domain);
        if ($parsed_to && isset($parsed_to['host'])) {
          $to_domain = $parsed_to['host'];
        } else {
          // Assume it's just a domain name
          $to_domain = trim($to_domain, '/');
        }

        if (empty($from_domain)) {
          \WP_CLI::warning('Could not determine current domain. Domain replacement skipped.');
          $to_domain = null;
        } else {
          \WP_CLI::log("Domain replacement enabled: {$from_domain} -> {$to_domain}");
        }
      }

      // Find all MapSVG tables
      $tables = $wpdb->get_col($wpdb->prepare(
        "SELECT table_name FROM information_schema.tables 
				 WHERE table_schema = %s 
				 AND table_name LIKE %s",
        $wpdb->dbname,
        $prefix . '%'
      ));

      if (empty($tables)) {
        \WP_CLI::warning('No MapSVG tables found.');
        return;
      }

      \WP_CLI::log('Found ' . count($tables) . ' tables.');

      // Export tables
      $export_content = "-- MapSVG Export\n";
      $export_content .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
      $export_content .= "-- Tables: " . implode(', ', $tables) . "\n";
      if ($to_domain && $from_domain) {
        $export_content .= "-- Domain replaced: {$from_domain} -> {$to_domain}\n";
      }
      $export_content .= "\n";

      foreach ($tables as $table) {
        \WP_CLI::log("Exporting table: {$table}");

        // Get table structure
        $create_table = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_A);
        $export_content .= "\n-- Table structure for `{$table}`\n";
        $export_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $export_content .= $create_table['Create Table'] . ";\n\n";

        // Get table data
        // Skip data export for clockwork tables
        $is_clockwork_table = strpos($table, 'clockwork') !== false;

        if (!$is_clockwork_table) {
          $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
          if (!empty($rows)) {
            $export_content .= "-- Data for table `{$table}`\n";
            $export_content .= "INSERT INTO `{$table}` VALUES\n";

            $values = [];
            foreach ($rows as $row) {
              $escaped_row = array_map(function ($value) use ($wpdb, $from_domain, $to_domain, &$replacement_count) {
                if ($value === null) {
                  return 'NULL';
                }

                // Replace domain in the value BEFORE preparing if toDomain is set
                if ($to_domain && $from_domain && is_string($value)) {
                  $original_value = $value;
                  $replacement_data = self::buildDomainReplacementPatterns($from_domain, $to_domain);
                  $value = preg_replace($replacement_data['patterns'], $replacement_data['replacements'], $value);

                  if ($value !== $original_value) {
                    $replacement_count++;
                  }
                }

                return $wpdb->prepare('%s', $value);
              }, $row);
              $values[] = '(' . implode(',', $escaped_row) . ')';
            }
            $export_content .= implode(",\n", $values) . ";\n\n";
          }
        } else {
          $export_content .= "-- Skipping data export for clockwork table `{$table}`\n\n";
        }
      }

      // Apply domain replacement to the entire export content as well (for any URLs in comments or structure)
      if ($to_domain && $from_domain) {
        $replacement_data = self::buildDomainReplacementPatterns($from_domain, $to_domain);
        $export_content = preg_replace($replacement_data['patterns'], $replacement_data['replacements'], $export_content);
      }

      // Ensure uploads directory exists
      if (!file_exists(MAPSVG_UPLOADS_DIR)) {
        wp_mkdir_p(MAPSVG_UPLOADS_DIR);
      }

      $file_path = MAPSVG_UPLOADS_DIR . DIRECTORY_SEPARATOR . $export_file;

      // Initialize WP_Filesystem
      global $wp_filesystem;
      if (empty($wp_filesystem)) {
        require_once(ABSPATH . '/wp-admin/includes/file.php');
        WP_Filesystem();
      }

      // Write file using WP_Filesystem
      $res = $wp_filesystem->put_contents(
        $file_path,
        $export_content,
        FS_CHMOD_FILE
      );

      if ($res === false) {
        \WP_CLI::error('Failed to write export file: ' . $file_path);
        return;
      }

      $success_message = "Export completed: {$file_path}";
      if ($to_domain && $replacement_count > 0) {
        $success_message .= " ({$replacement_count} domain replacements made)";
      }
      \WP_CLI::success($success_message);
    });
  }

  /**
   * Build domain replacement patterns and replacements
   * Handles both regular URLs and JSON-escaped URLs (with \/)
   *
   * @param string $from_domain Source domain
   * @param string $to_domain Target domain
   * @return array Array with 'patterns' and 'replacements' keys
   */
  private static function buildDomainReplacementPatterns(string $from_domain, string $to_domain): array
  {
    $delimiter = '~';
    $escaped_from = preg_quote($from_domain, $delimiter);
    $escaped_from_www = preg_quote('www.' . $from_domain, $delimiter);

    // Match both regular URLs, JSON-escaped URLs, and protocol-relative URLs
    // In PHP strings, \\ matches a single backslash, so \\\\ matches literal \\
    $patterns = [
      // Regular URLs
      $delimiter . 'http://' . $escaped_from . $delimiter,
      $delimiter . 'https://' . $escaped_from . $delimiter,
      $delimiter . 'http://' . $escaped_from_www . $delimiter,
      $delimiter . 'https://' . $escaped_from_www . $delimiter,
      // JSON-escaped URLs (double backslashes: http:\\/\\/)
      $delimiter . 'http:\\\\/\\\\/' . $escaped_from . $delimiter,
      $delimiter . 'https:\\\\/\\\\/' . $escaped_from . $delimiter,
      $delimiter . 'http:\\\\/\\\\/' . $escaped_from_www . $delimiter,
      $delimiter . 'https:\\\\/\\\\/' . $escaped_from_www . $delimiter,
      // Protocol-relative URLs (//domain)
      $delimiter . '//' . $escaped_from . $delimiter,
      $delimiter . '//' . $escaped_from_www . $delimiter,
      // Protocol-relative JSON-escaped URLs (\\/\\/domain)
      $delimiter . '\\\\/\\\\/' . $escaped_from . $delimiter,
      $delimiter . '\\\\/\\\\/' . $escaped_from_www . $delimiter,
    ];
    $replacements = [
      'http://' . $to_domain,
      'https://' . $to_domain,
      'http://www.' . $to_domain,
      'https://www.' . $to_domain,
      'http:\\\\/\\\\/' . $to_domain,
      'https:\\\\/\\\\/' . $to_domain,
      'http:\\\\/\\\\/www.' . $to_domain,
      'https:\\\\/\\\\/www.' . $to_domain,
      '//' . $to_domain,
      '//www.' . $to_domain,
      '\\\\/\\\\/' . $to_domain,
      '\\\\/\\\\/www.' . $to_domain,
    ];

    // Handle without www variant
    $from_domain_no_www = preg_replace('/^www\./', '', $from_domain);
    $to_domain_no_www = preg_replace('/^www\./', '', $to_domain);
    if ($from_domain_no_www !== $from_domain) {
      $escaped_from_no_www = preg_quote($from_domain_no_www, $delimiter);
      $patterns[] = $delimiter . 'http://' . $escaped_from_no_www . $delimiter;
      $patterns[] = $delimiter . 'https://' . $escaped_from_no_www . $delimiter;
      $patterns[] = $delimiter . 'http:\\\\/\\\\/' . $escaped_from_no_www . $delimiter;
      $patterns[] = $delimiter . 'https:\\\\/\\\\/' . $escaped_from_no_www . $delimiter;
      $patterns[] = $delimiter . '//' . $escaped_from_no_www . $delimiter;
      $patterns[] = $delimiter . '\\\\/\\\\/' . $escaped_from_no_www . $delimiter;
      $replacements[] = 'http://' . $to_domain_no_www;
      $replacements[] = 'https://' . $to_domain_no_www;
      $replacements[] = 'http:\\\\/\\\\/' . $to_domain_no_www;
      $replacements[] = 'https:\\\\/\\\\/' . $to_domain_no_www;
      $replacements[] = '//' . $to_domain_no_www;
      $replacements[] = '\\\\/\\\\/' . $to_domain_no_www;
    }

    return [
      'patterns' => $patterns,
      'replacements' => $replacements,
    ];
  }

  /**
   * Register the import command
   */
  private static function registerImportCommand(): void
  {
    \WP_CLI::add_command('mapsvg import', function ($args, $assoc_args) {
      $db = Database::get();
      global $wpdb;

      $import_file = isset($assoc_args['file'])
        ? $assoc_args['file']
        : (isset($args[0]) ? $args[0] : null);

      if (!$import_file || !file_exists($import_file)) {
        \WP_CLI::error('Import file not found. Usage: wp mapsvg import <file.sql>');
        return;
      }

      \WP_CLI::log("Importing from: {$import_file}");

      // Initialize WP_Filesystem
      global $wp_filesystem;
      if (empty($wp_filesystem)) {
        require_once(ABSPATH . '/wp-admin/includes/file.php');
        WP_Filesystem();
      }

      // Read SQL file using WP_Filesystem
      $sql = $wp_filesystem->get_contents($import_file);

      if (empty($sql)) {
        \WP_CLI::error('SQL file is empty.');
        return;
      }

      // Use mysqli_multi_query if available (works with both MySQL and MariaDB)
      if ($db->isMysqli()) {
        \WP_CLI::log("Executing SQL file in transaction...");

        // Start transaction
        $db->startTransaction();

        try {
          // Execute multiple statements at once
          // This handles semicolons inside JSON data correctly
          if ($db->multiQuery($sql)) {
            // Process all results to clear the buffer
            $db->processMultiQueryResults();

            // Check for errors after all queries executed
            if ($db->getMysqliErrno()) {
              // Rollback on error
              $db->rollback();
              \WP_CLI::error("Import failed: " . $db->getMysqliError() . " (Transaction rolled back)");
            } else {
              // Commit transaction on success
              $db->commit();
              \WP_CLI::success("Import completed successfully.");
            }
          } else {
            // Rollback on failure to execute
            $db->rollback();
            \WP_CLI::error("Failed to execute SQL: " . $db->getMysqliError() . " (Transaction rolled back)");
          }
        } catch (\Exception $e) {
          // Rollback on exception
          $db->rollback();
          \WP_CLI::error("Import failed with exception: " . $e->getMessage() . " (Transaction rolled back)");
        }
      } else {
        \WP_CLI::error("mysqli extension not available. Cannot execute SQL import.");
      }
    });

    /**
     * Run a migration file.
     *
     * Defaults to next.php (the in-development migration).
     * Use --name to target a specific versioned file.
     *
     * ## OPTIONS
     *
     * [--name=<version>]
     * : Version name of the migration to run (without .php), e.g. 8.13.0.
     *   Defaults to "next".
     *
     * ## EXAMPLES
     *
     *     wp mapsvg migrate
     *     wp mapsvg migrate --name=8.13.0
     */
    \WP_CLI::add_command('mapsvg migrate', function ($args, $assoc_args) {
      $migrationsDir = __DIR__ . '/../Migrate/Migrations';
      $name     = isset($assoc_args['name']) ? $assoc_args['name'] : 'next';
      $filename = basename($name) . '.php'; // prevent path traversal
      $filePath = $migrationsDir . DIRECTORY_SEPARATOR . $filename;

      if (!file_exists($filePath)) {
        \WP_CLI::error("Migration file not found: {$filename}");
      }

      $migration = require $filePath;

      if (!is_callable($migration)) {
        \WP_CLI::error("Migration file does not return a callable: {$filename}");
      }

      $migration();
      \WP_CLI::success("Migration applied: {$filename}");
    });
  }
}
