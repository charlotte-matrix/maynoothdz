;(function ($, window, MapSVG) {
  /**
   * Database objects CSV / Google Sheets import.
   * Remote URL validation (Google Sheets must be output=csv / format=csv) lives on
   * MapSVGAdminCsvController.validateRemoteCsvUrl — inherited by this controller.
   */
  var MapSVGAdminDatabaseCsvController = function (container, admin, mapsvg) {
    this.collectionType = "objects"
    this.database = mapsvg.objectsRepository
    this.showGeocoding = true
    MapSVGAdminCsvController.call(this, container, admin, mapsvg)
  }
  window.MapSVGAdminDatabaseCsvController = MapSVGAdminDatabaseCsvController
  MapSVG.extend(MapSVGAdminDatabaseCsvController, window.MapSVGAdminCsvController)
})(jQuery, window, window.MapSVG)
