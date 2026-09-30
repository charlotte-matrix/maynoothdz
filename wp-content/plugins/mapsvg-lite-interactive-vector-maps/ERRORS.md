# MapSVG Error Dictionary

`CLIENT-*` errors appear in the **browser console** (open via F12 › Console tab). `SERVER-*` errors are written to the **WordPress/server error log**.

---

## Client-Side Errors (browser console)

### CLIENT-001

**Message:** `MapSVG couldn't load SVG file`  
**Location:** `js/mapsvg/Map/Map.ts:695`  
**How to fix:** The SVG map file could not be loaded. In the **Map Editor**, click the first tab (map source/SVG) and verify the SVG file path is correct. Re-upload the SVG file if it is missing. This often happens after moving the site to a new server.

---

### CLIENT-002

**Message:** `Failed to migrate map options, loading with original options: <error>`  
**Location:** `js/mapsvg/Map/Map.ts:748`  
**How to fix:** After a plugin update, MapSVG tried to automatically upgrade this map's saved settings but something went wrong. The map loads anyway with its old settings. Try opening the map in the **Map Editor** and clicking **Save** to re-save settings in the current format. If the problem persists, contact MapSVG support and include the full error from the browser console.

---

### CLIENT-003

**Message:** `Fetch error: <error>`  
**Location:** `js/mapsvg/Map/Map.ts:891`  
**How to fix:** MapSVG could not reach its own REST API to load map data. Common causes: (1) Go to **WordPress Admin › Settings › Permalinks** and click **Save Changes** to flush rewrite rules. (2) A security plugin (e.g. Wordfence) may be blocking REST API requests — temporarily disable it to test. (3) A caching plugin may be serving stale responses — clear all caches.

---

### CLIENT-004

**Message:** `<Handlebars template error in browser console>`  
**Location:** `js/mapsvg/Map/Map.ts:1926`  
**How to fix:** One of the **Directory** templates contains a syntax error. In the **Map Editor**, go to **Directory** and click the **Directory Item template** or **Directory Category Item template** link to open the template editor. Check for unclosed `{{` / `}}` tags or mismatched `#each` / `#if` blocks.

---

### CLIENT-005

**Message:** `<Handlebars compilation error in browser console>`  
**Location:** `js/mapsvg/Map/Map.ts:1941`  
**How to fix:** One of the map's templates (Details View, Popover, Tooltip, etc.) has a syntax error and cannot be compiled. In the **Map Editor**, go to **Templates** and check each template for unclosed `{{` / `}}` tags or invalid Handlebars expressions. The map will display blank content for the broken template until it is fixed.

---

### CLIENT-006

**Message:** `MapSVG: wrong format of region style options.`  
**Location:** `js/mapsvg/Map/Map.ts:2266`  
**How to fix:** The style data stored for one or more regions is in an old or invalid format. In the **Map Editor**, go to the **Regions** tab, find the affected region, and re-apply its fill/stroke colour settings, then **Save** the map. If this affects many regions, contact MapSVG support.

---

### CLIENT-007

**Message:** `MapSVG: filter container #<containerId> does not exist`  
**Location:** `js/mapsvg/Map/Map.ts:3693`  
**How to fix:** The Filters panel is set to appear in a custom HTML element on the page, but that element was not found. In the **Map Editor**, go to **Filters › Settings › Location** and check the **Custom container ID** field. Make sure the HTML element with that ID actually exists in your page template/content before the map loads.

---

### CLIENT-008

**Message:** `The directory should load in a custom container #<containerId> which isn't available in the backend. Skipping the directory initialization step.`  
**Location:** `js/mapsvg/Map/Map.ts:4253`  
**How to fix:** This message appears only in the **Map Editor preview** — it is not a real error on the live site. The Directory is configured to load inside a custom container that doesn't exist in the admin panel preview. The directory will work correctly on the live page as long as the container element is present there. No action required.

---

### CLIENT-009

**Message:** `Google Maps API key is required to enable Google Maps`  
**Location:** `js/mapsvg/Map/Map.ts:4603`  
**How to fix:** Google Maps is turned on for this map but no API key has been entered. Go to **WordPress Admin › MapSVG › Settings › Google Maps** and enter a valid Google Maps JavaScript API key.

---

### CLIENT-010

**Message:** `MapSVG: can't load Google API because no API key has been provided`  
**Location:** `js/mapsvg/Map/Map.ts:4827`  
**How to fix:** Same root cause as CLIENT-009. Go to **WordPress Admin › MapSVG › Settings › Google Maps** and enter a valid API key.

---

### CLIENT-011

**Message:** `MapSVG: Google maps API key is incorrect.`  
**Location:** `js/mapsvg/Map/Map.ts:4853`  
**How to fix:** The Google Maps API key is entered but Google rejected it as invalid. Go to **WordPress Admin › MapSVG › Settings › Google Maps**, verify the key, then check that the **Maps JavaScript API** is enabled for this key in your [Google Cloud Console](https://console.cloud.google.com/) and that billing is active.

---

### CLIENT-012

**Message:** `<Google Maps script loading exception>`  
**Location:** `js/mapsvg/Map/Map.ts:4885`  
**How to fix:** The Google Maps script failed to load. Check: (1) Your server's **Content Security Policy** (CSP) headers — they may be blocking `maps.googleapis.com`. (2) Any plugin that adds security headers (e.g. Solid Security, Wordfence). (3) Check the full error in the browser console's Network tab.

---

### CLIENT-013

**Message:** `Can't load a new map: container was not provided.`  
**Location:** `js/mapsvg/Map/Map.ts:7072`  
**How to fix:** The **Show another map** action is configured but the target **Container ID** is empty or refers to an element that doesn't exist. In the **Map Editor**, go to **Actions › Region click** (or **Marker click**), find **Show another map**, and make sure the **Container ID** field contains the correct ID of an existing HTML element on the page.

---

### CLIENT-014

**Message:** `Could not load objects`  
**Location:** `js/mapsvg/Map/Map.ts:8009`  
**How to fix:** MapSVG could not load Database Objects from the server. Try: (1) **WordPress Admin › Settings › Permalinks › Save Changes** to refresh REST API routes. (2) Disable security/caching plugins temporarily to isolate the cause. (3) Check the **Network** tab in browser DevTools (F12) for the failing request and look at the server response for a more specific error.

---

### CLIENT-015

**Message:** `Could not load regions`  
**Location:** `js/mapsvg/Map/Map.ts:8083`  
**How to fix:** Same as CLIENT-014 but for Region data. Go to **WordPress Admin › Settings › Permalinks › Save Changes**, clear caches, and check the browser Network tab for the failing REST API request.

---

### CLIENT-016

**Message:** `MapSVG: Error in the "Region Label" template`  
**Location:** `js/mapsvg/Map/Map.ts:8382`  
**How to fix:** The Region Label template has a syntax error. In the **Map Editor**, go to **Templates › Region Label** and check for invalid Handlebars syntax (e.g. unclosed `{{` tags or references to non-existent fields). Region labels will not be shown until the template is fixed.

---

### CLIENT-017

**Message:** `MapSVG: Error in the "Marker Label" template`  
**Location:** `js/mapsvg/Map/Map.ts:8411`  
**How to fix:** The Marker Label template has a syntax error. In the **Map Editor**, go to **Templates › Marker Label** and check for invalid Handlebars syntax. Marker labels will not be shown until the template is fixed.

---

### CLIENT-018

**Message:** `MapSVG: file not found - <file path>` _(on the live site)_  
**Location:** `js/mapsvg/Map/Map.ts:8790`  
**How to fix:** The SVG file cannot be found (HTTP 404). This commonly happens after migrating the site to a new server or domain. In the **Map Editor**, go to the **SVG / Source** tab and update the path to the SVG file, or re-upload it.

---

### CLIENT-019

**Message:** `MapSVG: can't load SVG file for unknown reason. Please contact support.`  
**Location:** `js/mapsvg/Map/Map.ts:8797`  
**How to fix:** The SVG file request failed with an unexpected server error (e.g. HTTP 403 or 500). Check the browser Network tab for the exact response. Common causes: file permission issues on the server, a firewall or WAF blocking the request, or the file being corrupted. Contact your hosting provider if the issue persists.

---

### CLIENT-020

**Message:** `MapSVG: Google Maps API is not loaded.`  
**Location:** `js/mapsvg/Core/Utils.ts:274`  
**How to fix:** A map feature that requires Google Maps was triggered before the Google Maps API finished loading. This is usually caused by a slow network. If this happens consistently, check that no other plugin is loading a conflicting Google Maps script on the same page. Also verify your API key is correct in **WordPress Admin › MapSVG › Settings › Google Maps**.

---

### CLIENT-021

**Message:** `Error in middleware <type>: <error>`  
**Location:** `js/mapsvg/Core/Middleware.ts:142`  
**How to fix:** A middleware function registered via the **Map Editor › JavaScript** tab (using `mapsvg.setMiddlewares()`) threw an error. Open the **Map Editor › JavaScript** tab and review the middleware code for the reported type (`mapLoad` or `render`). Check the full error message in the browser console for the specific line that failed.

---

### CLIENT-022

**Message:** `<Handlebars rendering error in browser console>`  
**Location:** `js/mapsvg/Core/Controller.ts:465`  
**How to fix:** A template (Details View, Popover, or Tooltip) failed to render for a specific object. The panel will show blank content. Go to **Map Editor › Templates** and check the relevant template for references to fields that may be missing or `null` for some objects (e.g. `{{images.0.full}}` on an object with no images). Use `{{#if fieldName}}` guards around optional fields.

---

### CLIENT-023

**Message:** `ZERO_RESULTS` / `REQUEST_DENIED` / `OVER_DAILY_LIMIT` / `<other Google error>`  
**Location:** `js/mapsvg/FormBuilder/FormElements/Distance/DistanceFormElement.ts:323`  
**How to fix:** Google could not geocode the address entered in the **Distance Search** field. Common fixes: (1) `REQUEST_DENIED` — verify that the **Geocoding API** is enabled for your key in the [Google Cloud Console](https://console.cloud.google.com/) (in addition to the Maps JavaScript API). (2) `OVER_DAILY_LIMIT` — check your billing/quota in Google Cloud Console. (3) `ZERO_RESULTS` — the address entered was not found; this is a user input issue.

---

### CLIENT-024

**Message:** `MapSVG: incorrect format of {x, y} object for SVGPoint.`  
**Location:** `js/mapsvg/Location/Location.ts:31`  
**How to fix:** A Database Object has a location stored with invalid SVG coordinates (missing or non-numeric `x`/`y` values). In the **Map Editor › Database**, find the affected object and re-set its location by clicking the location field and re-pinning it on the map, then save.

---

### CLIENT-025

**Message:** `MapSVG: incorrect format of {lat, lng} object for GeoPoint.`  
**Location:** `js/mapsvg/Location/Location.ts:69`  
**How to fix:** A Database Object has a location stored with invalid geographic coordinates (missing or non-numeric `lat`/`lng` values). In the **Map Editor › Database**, find the affected object and re-set its location, then save.

---

### CLIENT-026

**Message:** `Schema not found`  
**Location:** `js/mapsvg/Core/Repository.ts:179`  
**How to fix:** The map's database schema definition is missing, which usually means the map settings are incomplete or corrupted after an upgrade. Open the map in the **Map Editor**, go to **Database › Settings**, check that the Objects table name is set correctly, then **Save** the map. If the issue persists, contact MapSVG support.

---

### CLIENT-027

**Message:** `<HTTP error response during data import>`  
**Location:** `js/mapsvg/Core/Repository.ts:598`  
**How to fix:** A CSV data import failed while sending a chunk of records to the server. Go to **Map Editor › Database › Import CSV**, reduce the import batch size if possible, and try again. Check the Network tab in DevTools for the specific server response. Also ensure your PHP `max_execution_time` and `upload_max_filesize` limits are sufficient for your file size.

---

### CLIENT-028

**Message:** `Nested fields are not supported for this field type: <fieldName>`  
**Location:** `js/mapsvg/Core/Repository.ts:783`  
**How to fix:** A filter or query is trying to search inside a nested field (using dot notation like `location.address`) on a field type that doesn't support it. In the **Map Editor › Filters**, review the filter fields and check that the selected field type supports the operation being performed.

---

### CLIENT-029

**Message:** `Could not update options to version <version> due to error: <error>`  
**Location:** `js/mapsvg/Core/Migrations/migrate.ts:39`  
**How to fix:** One step of the automatic settings migration (run after a plugin update) failed. This leads to CLIENT-002. Open the map in the **Map Editor** and click **Save** to attempt to recover. If the map still doesn't load correctly, contact MapSVG support with the full error from the browser console, including the version number shown in the message.

---

### CLIENT-030

**Message:** `MapSVG: event handler error for "<event>" on map ID=<id>`  
**Location:** `js/mapsvg/Core/Events.ts:171`  
**How to fix:** A custom event handler (entered in **Map Editor › JavaScript**) threw a runtime error. The map continues to work but the event action is skipped. Open **Map Editor › JavaScript**, find the handler for the event shown in the message (e.g. `click.region`, `afterLoad`), and review the code for errors. The full error and the data that triggered it are shown in the browser console.

---

### CLIENT-031

**Message:** `There has been a problem with your fetch operation: <error>`  
**Location:** `js/mapsvg-admin/core/admin.js:1159`  
**How to fix:** _(Admin panel only)_ A save or load request in the Map Editor failed at the network level. Check your internet connection. If the error persists, try disabling security or firewall plugins, or check whether your server is rate-limiting admin requests.

---

### CLIENT-032

**Message:** `Failed to copy text: <error>`  
**Location:** `js/mapsvg-admin/core/admin.js:1788, 1815`  
**How to fix:** _(Admin panel only)_ The "Copy to clipboard" button in the Map Editor failed. This happens when the admin panel is not served over **HTTPS** — the browser's Clipboard API requires a secure connection. Make sure your WordPress admin is accessed via `https://`.

---

### CLIENT-033

**Message:** `<Google Geocoding API error message>`  
**Location:** `js/mapsvg-admin/core/admin.js:2369`  
**How to fix:** _(Admin panel only)_ Google could not geocode the address entered in the admin-panel location picker. See CLIENT-023 for common causes and fixes. In particular, ensure the **Geocoding API** is enabled for your key in [Google Cloud Console](https://console.cloud.google.com/).

---

## Server-Side Errors (server/WordPress log)

### SERVER-001

**Message:** `MapSVG: trying to update location meta field of the post type that is not connected to any map: <post_type>`  
**Location:** `php/PostEditorMapLoader/PostEditorMapLoader.php:127`  
**How to fix:** A post was saved but MapSVG couldn't find a map connected to that post type. In the **Map Editor › Database › Settings**, check the **Post types** section and verify the correct post type is selected. Also make sure the database source is set to **WordPress Posts**.

---

### SERVER-002

**Message:** `<database query exception>`  
**Location:** `php/Database/DbDataSource.php:505`  
**How to fix:** A database query failed with an exception. The affected map or object list will return empty results. Check the **WordPress debug log** for the full query and error. Common causes: a MapSVG table is missing (try deactivating and reactivating the plugin to trigger table creation) or MySQL user permissions are insufficient.

---

### SERVER-003

**Message:** `<WordPress database error string>`  
**Location:** `php/Database/Database.php:81`  
**How to fix:** A database operation failed and WordPress (`$wpdb`) reported an error. Enable **WP_DEBUG_LOG** in `wp-config.php` to capture the full error. Common causes: missing MapSVG tables, incorrect table prefix, or MySQL permission issues. Try deactivating and reactivating the MapSVG plugin to recreate the tables.

---

### SERVER-004

**Message:** `SVG file not found: <server path>`  
**Location:** `php/Domain/Map/MapController.php:177`  
**How to fix:** The server cannot find the SVG file at the stored path. This is the server-side version of CLIENT-018. In the **Map Editor**, go to the **SVG / Source** tab, update the path or re-upload the SVG file, and save.

---

### SERVER-005

**Message:** `Migration failed: <error message>`  
**Location:** `php/Migrate/Upgrade.php:60`  
**How to fix:** A plugin-level database migration (run once when upgrading MapSVG) failed. Other migrations after this one continue running. Check the WordPress debug log for the specific version and error. If tables or data are missing after the upgrade, try deactivating and reactivating the plugin, or contact MapSVG support with the error details.

---

### SERVER-006

**Message:** `MapSVG: Migration directory does not exist: <path>`  
**Location:** `php/Migrate/Upgrade.php:87`  
**How to fix:** The plugin's migration files folder is missing — the plugin installation is incomplete or corrupted. Re-upload the MapSVG plugin via **WordPress Admin › Plugins › Add New** (upload the zip) or via FTP, making sure all files are transferred correctly.

---

### SERVER-007

**Message:** `MapSVG: Migration directory is not readable: <path>`  
**Location:** `php/Migrate/Upgrade.php:92`  
**How to fix:** The migration folder exists but the web server cannot read it due to file permissions. Connect via FTP/SSH and set the permissions of `wp-content/plugins/mapsvg/php/Migrate/Migrations/` to `755`. Contact your hosting provider if you don't have access to change file permissions.

---

### SERVER-008

**Message:** `MapSVG: glob() failed for pattern: <pattern>`  
**Location:** `php/Migrate/Upgrade.php:100`  
**How to fix:** PHP's `open_basedir` restriction is preventing MapSVG from scanning its own migration folder. Contact your hosting provider and ask them to add the MapSVG plugin directory to the `open_basedir` allowlist in the PHP configuration.

---

### SERVER-009

**Message:** `MapSVG: No migration files found in: <path>`  
**Location:** `php/Migrate/Upgrade.php:105`  
**How to fix:** The migration folder is empty — no `.php` migration files were found. The plugin installation is incomplete. Re-upload or reinstall the MapSVG plugin.

---

### SERVER-010

**Message:** `MapSVG: Migration file is not callable: <file>`  
**Location:** `php/Migrate/Upgrade.php:131`  
**How to fix:** One of the migration files is malformed — it does not return an executable function. This is an internal plugin issue. Re-install the MapSVG plugin to restore the correct file. If this persists after reinstalling, contact MapSVG support.

---

### SERVER-011

**Message:** `Error creating tokens table: <database error>`  
**Location:** `php/Migrate/Migrations/8.3.0.php:24`  
**How to fix:** MapSVG could not create the tokens database table during the 8.3.0 upgrade migration. Common cause: the MySQL database user doesn't have `CREATE TABLE` permission. Ask your hosting provider to grant `CREATE`, `ALTER`, and `DROP` privileges on your WordPress database to the database user defined in `wp-config.php`.

---

### SERVER-012

**Message:** `Failed to create tokens table. Table does not exist after creation attempt.`  
**Location:** `php/Migrate/Migrations/8.3.0.php:29`  
**How to fix:** The table creation command ran without error but the table still doesn't exist. This is unusual and may be caused by a database transaction issue or a MySQL configuration problem. Try deactivating and reactivating the MapSVG plugin, or contact MapSVG support and your hosting provider.

---

### SERVER-013

**Message:** `Migration failed: <error message>`  
**Location:** `php/Domain/Map/MapUpdater.php:49`  
**How to fix:** The automatic per-map settings upgrade (run each time a map is loaded whose version is older than the current plugin version) failed. The map is restored to its previous state. Check the WordPress debug log for the specific map ID and error. Try opening the map in the **Map Editor** and clicking **Save** to manually trigger a re-save in the current format. Contact MapSVG support if the map remains broken.

---

### SERVER-014

**Message:** `Failed to load SVG file: <path>. Errors: <XML errors>`  
**Location:** `php/Domain/Map/Map.php:402`  
**How to fix:** The SVG file was found but contains XML errors that PHP cannot parse. The specific XML errors are listed in the log. Common causes: the file contains invalid characters, undeclared XML entities, or was not saved as a proper SVG file. Open the SVG in a vector editor (e.g. Inkscape or Illustrator), fix any warnings, and re-export/re-upload it.

---

### SERVER-015

**Message:** `SVG file does not exist: <server path>`  
**Location:** `php/Domain/Map/Map.php:406`  
**How to fix:** Same as SERVER-004 but detected during PHP-side SVG parsing. In the **Map Editor**, go to the **SVG / Source** tab, verify or re-upload the SVG file, and save.

---

### SERVER-016

**Message:** `<file listing exception>`  
**Location:** `php/Domain/File/FilesRepository.php:58`  
**How to fix:** MapSVG could not list the files in the markers/uploads directory. Check that the `wp-content/uploads/mapsvg/` directory exists and is readable by the web server. If missing, create it via FTP and set its permissions to `755`.

---

### SERVER-017

**Message:** `<object parameter decoding exception>`  
**Location:** `php/Core/Repository.php:364`  
**How to fix:** One database record could not be decoded — it likely contains malformed or invalid JSON in its `options` column. That record is skipped silently. To identify which record is affected, enable `WP_DEBUG_LOG` and look for the exception details. The record may need to be manually corrected or deleted via **Map Editor › Database › Objects list**.

---

_Update this file whenever new `console.error` or `Logger::error` calls are added to the codebase._
