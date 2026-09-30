=== MapSVG - Vector maps, Image maps, Google Maps ===
Contributors: oyatek
Tags: map, store locator, google maps, floorplan, image map
Requires at least: 5.0
Tested up to: 7.0.1
Stable tag: 8.16.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create interactive vector maps, floor plans, and image maps. Support for Google Maps integration, custom markers, tooltips, and popups.

== Description ==

MapSVG is a powerful WordPress mapping plugin that allows you to create interactive vector maps, floor plans, and image maps. Perfect for real estate websites, store locators, seating charts, and more.

* [WP-Admin Demo](https://mapsvg.com/demo)

https://youtu.be/GXrGA9ecN-Y?si=ExhZthcs1AOn58IQ

= Key Features (free version) =

* SVG Vector Maps (100+ maps are included in the plugin)
* Custom SVG files support (upload your own SVG files and turn them into interactive maps)
* Google Maps
* Custom overlays on Google Maps
* Image Maps
* Floor Plans
* Store Locator (search by address)
* Markers (add markers by entering an address)
* Connect markers to clickable areas on the map (show all locations connected to a specific area in the popover window)
* Map areas and markers working as links
* Custom Tooltips, Popovers, Large scrollable modals
* Database Integration
* Custom Fields
* Filters & Search
* REST API Support

= MapSVG vs other mapping plugin =

Below is the comparison of the free version of MapSVG to other popular free mapping plugins on WordPress.org.

### Unlimited maps
* **MapSVG**: Unlimited number of maps
* **Others**: 1 map

### Unlimited locations
* **MapSVG**: Unlimited number of markers 
* **Others**: Limited number of markers

### Map regions to locations connection
* **MapSVG**: Connect markers to map clickable areas. Every area of the map can have one or many markers connected to it. Show the list of connected locations in the popover window.
* **Others**: No connection between markers and map clickable areas

### Internal Database
* **MapSVG**: Custom database, with custom fields support
* **Others**: You can only use a small set of pre-defined fields, such as title, description and address.

### Views
* **MapSVG**: Show custom fields, as formatted HTML, or show the fully rendered WP Post content - in the Tooltips / Popovers (unique feature of MapSVG)
* **Others**: You can only show the coordinates of the marker in a small tooltip

### Labels
* **MapSVG**: Show marker labels (name or address of the location) and region labels (names of countries, states, etc.)
* **Others**: No labels


= MapSVG Premium Features =

- **Directory**: Display a list of interactive items alongside the map.
- **Custom Overlays**: Add custom overlays on Google Maps.
- **Advanced Filters**: Enable fulltext search, search by category, zip code, by date.
- **Country-Specific Address Search**: Restrict address searches by country.
- **CSV Data Import**: Load data seamlessly from CSV files.
- **Image Gallery Module**: Showcase images in a lightbox format.
- **Parent/Child Maps**: Open an additional map upon clicking a region.
- **Marker Clustering**: Group large amount of markers into clusters.
- **Data Source: WordPressCPT**: Use WordPress CPTs as a data source.
- **Data Source: Custom API**: Use JSON response from your own API as a data source.
- **Code editor: CSS**: Edit CSS directly in the map editor, customize the look and feel of the map.
- **Code editor: Handlebars Templates**: Get full control over the HTML of the popovers / tooltips / details view.
- **Code editor: JavaScript**: Use built-in JavaScript editor for event handling, middleware customization, modify data before it is displayed on the map.
- **Custom Marker Images**: Upload your own marker images.
- **Chat Authorization**: Store the chat history. Get access to the same chats online any time on mapsvg.com/dashboard. 
- **Priority Support**: Faster response time in the chat.
- **Purchase Code for Updates**: Enable automatic updates from our servers with the purchase code for more frequent updates.
- **One-Click wp-admin Access**: Grant support agents quick access to your wp-admin for faster issue resolution.


== Installation ==

1. Unzip the plugin folder
2. Upload the plugin folder to `/wp-content/plugins` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Click on the MapSVG menu item
5. Get your Google Maps API keys and add them to the MapSVG settings
6. Create your first map
7. Activate support chat
8. Ask our AI bot any questions

== Frequently Asked Questions ==

= Can I use my own SVG files? =

Yes, you can upload any SVG file and turn it into an interactive map.

= Do you have a vector map of XYZ? = 

MapSVG has 100+ SVG vector maps inlcuded in the plugin. Also, MapSVG can create an interactive map from any custom SVG file. But it can't generate SVG maps from scratch and we don't provide map drawing services. If you need a specific map of some country, you can try to search on Google "blank svg map of XYZ", download the SVG file and then upload it to MapSVG.

= Can I turn map areas into clickable links? =

Yes, you can do that.

= Can I show another map on click on a map region? =

Yes, you can do that - but only in the Premium version.

= Can I show fields created with ACF plugin? =

You can show a full rendered post content in the modal window on click on a map region. If you WP theme page template shows ACF fields - you can show them in a modal window. If you want to show just some of the ACF fields, it is possible to do - but only in the Premium version.

= Can I create a store locator with MapSVG? =

Yes, you can create store locator with search by address. But if you also need to show a clickable list of stores, you need to use MapSVG Premium.

= Can I use a custom marker image? =

You can - but only in the Premium version.

= Can I customize the look and feel of the map? =

In the free version, you can use your WP theme's CSS file to customize the looks of the map.
MapSVG Premium has an embedded CSV editor, which makes style editing more comfortable, because you can see the changes you make in real-time.

= How can I report a security bug? =

You can report security bugs through the Patchstack Vulnerability Disclosure Program. The Patchstack team helps validate, triage and handle any security vulnerabilities. [Report a security vulnerability.](https://patchstack.com/database/vdp/4b4b7989-18ec-4cef-82a4-c2225e5fceef)

== Changelog ==

= 8.16.1 =
* Fixed: **Security** — hardened WordPress post-type REST routes with additional access checks.
* Fixed: **Filters** — public maps no longer get stuck on a loading spinner when a cached page sends an expired WordPress REST nonce; those reads retry without the stale nonce. Gleap #237612
* Fixed: **CSV import** — file uploads honor the selected ID column so existing region rows update instead of duplicating. "No ID" appends as expected, and Excel BOM headers no longer create blank IDs. Gleap #237577
* Fixed: **Loading spinner** — the spinner sits next to the loading text, empty text is hidden, and the preloader is visible before JavaScript runs.
* Fixed: **Mobile toolbar** — Map/List icons show correctly when Shadow DOM is enabled.
* Fixed: **Pinch-zoom** — pinch-zoom no longer fails on some iPad browsers (including DuckDuckGo). Gleap #237573
* Fixed: **USA counties map** — corrected the Orange County spelling.

= 8.16.0 =
* Improved: **Regions / SVG changes** — replacing or editing an SVG no longer deletes region records whose IDs disappeared. Those rows stay as orphans so you can copy their data, then remove them from Regions → Settings → Clean orphan region records.
* Improved: **Region labels** — more reliable label centering (better placement on awkward shapes, no flash before styles load). Bundled SVG maps were updated with the new centers.
* Fixed: **Security / REST API** — hardened remote CSV and API fetches so user-supplied URLs cannot reach private, loopback, or cloud-metadata hosts.
* Fixed: **CSV import** — Google Sheets web-view URLs are rejected; use a published CSV link instead.
* Fixed: **Admin** — stopped a map resize feedback loop that could keep resizing the preview.

= 8.15.0 =
* Improved: **Elementor + Gutenberg** — added native integrations (Elementor widget and Gutenberg block) with live map preview directly inside the page editor, so you can see map updates while editing.

= 8.14.2 =
* Improved: **Admin UI** — data sources list now shows collection name, type, and linked post type.
* Improved: **Compatibility** — declared tested with WordPress 7.0.1.
* Fixed: **WP Posts** — fixed allowing the same WordPress post type to be attached to more than one data source.
* Fixed: **API** — requests for a missing collection/data source now return a clear bad-request error instead of failing.

= 8.14.1 =
* Fixed: **SVG uploads** — strengthened upload checks so only valid SVG files are accepted.
* Fixed: **Security / REST API** — hardened post-type and distinct-value endpoints against SQL injection.
* Fixed: **Security / loading text** — sanitized and safely rendered map loading text to prevent stored XSS.
* Fixed: **Security / Handlebars** — updated Handlebars to the latest version with security fixes.

= 8.14.0 =
* Improved: **Data sources** — you can delete unused data sources from the admin (connected sources and region tables stay protected).
* Improved: **WP Posts** — clearer Connect / Edit / Connected controls for data sources, and more reliable linking of maps to WordPress post types.
* Improved: **Admin UI** — cleaner data source actions (icon buttons) and form layout tweaks.
* Fixed: **WP Posts / post types** — fixed empty or missing `postType` on legacy `posts_*` schemas so post filters and sync work correctly.
* Fixed: **Google Maps** — removed an incorrect viewport restriction that could block map interaction.

= 8.13.2 =
* Fixed: data table was not getting created for new or duplicated maps

= 8.13.1 =
* Fixed: **Forms** — improved multiselect tag layout so tags don’t overlap other elements.
* Fixed: **Popovers** — fixed popovers closing when clicking inside them.

= 8.13.0 =
* Improved: **Google Sheets auto-sync** — added a full Google Sheets/remote CSV data source flow with scheduled auto-refetch support.
* Improved: **CSV import** — improved import flow with preflight validation, clearer ID-field handling, and smarter append/upsert behavior.
* Improved: **Admin UX** — improved CSV/database/regions controls, including geocoding options, read-only behavior, and add/edit button state updates.

= 8.12.2 =
* Fixed: zoom to cluster with markers at the same location

= 8.12.1 =
* Improved: categories can now group by text fields

= 8.12.0 =
* Improved: CSV import — large files (100K+ rows) are now processed in the background using chunked server-side parsing and WordPress Cron. Progress is tracked live and survives page reloads. Supports all field types including Location (with background geocoding), Image (remote URLs downloaded to WP Media Library with deduplication), Regions, Select, Checkboxes, Post, and Date.
* Improved: Error handling — all errors now include unique error codes. Extended descriptions are available at [mapsvg.com/docs/errors](https://mapsvg.com/docs/errors).

= 8.11.1 =
* Fixed: map data now updates correctly when connected posts are published, unpublished, trashed, or restored.
* Fixed: directory item templates rendering issues.
* Fixed: zoom to a single marker after filtering.
* Fixed: region status colors not applying correctly.
* Fixed: Slovenia map calibration.

= 8.11.0 =
* Feature: load 1 post location only, for the currently opened post page
* Feature: choose AND/OR logic for the multiselect filters

= 8.10.1 =
* Fixed: show previous map button functionality

Examples:
* `wp mapsvg export` - Export all MapSVG database tables to SQL file
* `wp mapsvg export --toDomain=mapsvg.com` - Export and replace current domain with a new one in the .sql file
* `wp mapsvg import /path/to/file.sql` - Import MapSVG data from SQL file

= 8.10.0 =
* Feature: added wp-cli export/import commands.

= 8.9.3 =
* Fixed: Windows OS incompatibility

= 8.9.2 =
* Feature: rounded map buttons
* Fixed: check if phpinfo() is available

= 8.9.1 =
* Fixed: styles for multiselect fields

= 8.9.0 =
* Feature: map themes: default, rounded

= 8.8.4 =
* Fixed: map visibility in Classic Post Editor

= 8.8.3 =
* Fixed: tooltip flickering during mirroring near map edges

= 8.8.2 =
* Fixed: Handlebars templates not loading correctly when multiple maps were on the same page
* Fixed: location form styles in Gutenberg

= 8.8.1 = 
* Fixed: not working {{distanceTo ...}} Handlebars tag

= 8.8.0 = 
* Feature: Isolate CSS to shadow root (optional)
* Fixed: blurred map on zoom in Safari

= 8.7.23 = 
* Fixed: XSS vulnerability in shortcode attributes

= 8.7.22 = 
* Feature: Maximum zoom level for clustering
* Fixed: blurred map on zoom in Safari
* Fixed: zoom to cluster on Google Maps

= 8.7.21 = 
* Fixed: empty directory in duplicated maps
* Fixed: "deprecated" error in logs

= 8.7.20 = 
* Fixed: zoom to a cluster did not always reveal its markers

= 8.7.19 = 
* Fixed: filtering by ACF using checkboxes fields
* Fixed: migration issues

= 8.7.18 = 
* Fixed: filtering by ACF select and multiselect fields
* Fixed: added compatibility with PHP 7.4

= 8.7.17 = 
* Fixed: unserialized ACF field values in filter options

= 8.7.16 = 
* Fixed: duplicating maps created in older version of mapsvg was triggering an error about auto_increment
* Fixed: improved world.svg map calibration

= 8.7.15 = 
* Fixed: prevent loading of Google Maps API for incompatible (uncalibrated) maps
* Fixed: user and access token creation (for support)
* Fixed: Clockwork issues in Laravel environment
* Fixed: filter tags were not loading when no filterSchema was provided
* Fixed: passing correct data to custom event handlers on mobile devices

= 8.7.14 = 
* Fixed: SVG file reload failed when regions had no Title field.

= 8.7.13 = 
* Fixed: prevent setting region statuses before the data has loaded from the server

= 8.7.12 = 
* Fixed: marker drag & drop

= 8.7.11 = 
* Fixed: buttons that control enabled/disabled state of the region status options
* Fixed: mobile map scroll issues
* Fixed: WP CPT connection, Gutenberg/Classic Editor scripts loading

= 8.7.10 = 
* Fixed: lite version fatal error

= 8.7.9 = 
* Fixed: visibility toggles for SVG groups
* Fixed: shortcode rendering in templates

= 8.7.8 = 
* Fixed: scroll on mobile devices
* Fixed: null region titles

= 8.7.5 = 
* Fixed: fixed issues of the "show another map in the same container" feature

= 8.7.4 = 
* Fixed: security issue, checking uploaded file type

= 8.7.3 = 
* Fixed: directory rendering issues with enabled categories

= 8.7.2 = 
* Fixed: "Show another map" feature functionality
* Fixed: WP Rocket and LiteSpeed cache plugins were breaking MapSVG code. Added JavaScript files to exclusions in those plugins' settings.
* Fixed: premature jQuery call that was breaking the map when jQuery wasn't immediately available

= 8.7.1 = 
* Fixed: deleting a post did not delete marker from the map
* Fixed: scrolling of css/js/templates code editors on Firefox
* Fixed: map sizing issues with some WP themes

= 8.7.0 = 
* Feature: dynamic Region statuses
* Fixed: incorrect container height issues in the map editor

= 8.6.15 = 
* Fixed: filter by region

= 8.6.14 =
* Fixed: saving settings on the main screen
* Fixed: maps not working with disabled permalinks

= 8.6.13 =
* Fixed: Duplicating a map now correctly updates the map ID in the "CSS" tab, preventing conflicts with the original map.

= 8.6.12 =
* Fixed a few vulnerabilities

= 8.6.11 =
* Fixed unavailable filtering by ACF fields
* Fixed handling of post type names containing dashes

= 8.6.10 =
* Fixed security vulnerabilities related to unauthorized shortcode rendering in templates

= 8.6.9 =
* Minor bugfix for migrations

= 8.6.8 =
* Minor bugfix

= 8.6.7 =
* Added SVG file sanitization for non-admins

= 8.6.6 =
* Fixed post title being rendered as plain text (in popovers / details)

= 8.6.5 =
* Fixed XSS vulnerability
* Fixed SVG file copy vulnerability

= 8.6.4 =
* Fix loading of different kinds of data sources

= 8.6.3 =
* Fix map.destroy() method

= 8.6.0 =
* (Pro only) Filters for WP CPT
* (Pro only) Dynamic markers by WP CPT field values

= 8.5.42 =
* Fix clustering

= 8.5.41 =
* Fix Handlebars WP shortcode rendering

= 8.5.40 =
* Fix "select" field editing
* Fix shortcode rending function (for mapsvg templates)

= 8.5.39 =
* Fix filter tags (show clickable filter tags when filters are hidden)
* Fix search by regions

= 8.5.38 =
* Fix clicking on links in SVG 
* Fix broken options in 8.5.37

= 8.5.37 =
* Fix plugin activation issues

= 8.5.36 =
* Fix shortcode rendering in the templates

= 8.5.35 =
* Add "mapsvg_format_post_data" filter
* Improve upgrading scripts from old MapSVG versions
* Fix flush_rewrite_rules issues

= 8.5.34 =
* Change "Upgrade to Pro" links to mapsvg.com/pricing

= 8.5.32 =
* Fix handle click on HTML links in SVG files
* Fix duplicate map feature
* Fix on mobile devices, on directory item click - properly fit markers on google maps
* Fix keep text search field state on database fetch, when filters are located in directory

= 8.5.31 =
* Fix invisible settings tabs in the Map Editor
* Fix the flush_rewrite_rules() execution issues
* Defer JS scripts loading

= 8.5.30 =
* Fix zero header/footer size, when used with details view

= 8.5.29 =
* Fix mobile buttons list/map

= 8.5.6 =
* Adjust Australia SVG map for better region label placement

= 8.5.7 =
* Fix reloading filters state on directory redraw

= 8.5.8 =
* Limit zoom levels to 1-22 (same as Google Maps)

= 8.5.9 =
* Fix pagination

= 8.5.10 = 
* Fix tables creation on plugin activation
* Fix viewBox reset to initial value

= 8.5.11 = 
* Fix tables creation on plugin activation

= 8.5.14 =
* Fix filters

= 8.5.15 =
* Fixed: filters not working properlywhen "search" button is added to the form

= 8.5.17 =
* Fixed: show popover on directory item click

== Upgrade Notice ==

== Screenshots ==
1. Adding a location
2. Setting up actions
3. Drawing clickable areas on Google Maps