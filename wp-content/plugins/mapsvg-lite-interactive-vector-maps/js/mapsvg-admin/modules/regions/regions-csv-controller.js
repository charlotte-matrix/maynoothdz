;(function ($, window, MapSVG) {
  /**
   * Regions CSV / Google Sheets import.
   * Remote URL validation (Google Sheets must be output=csv / format=csv) lives on
   * MapSVGAdminCsvController.validateRemoteCsvUrl — inherited by this controller.
   */
  var MapSVGAdminRegionsCsvController = function (container, admin, mapsvg) {
    this.collectionType = "regions"
    this.database = mapsvg.regionsDatabase
    this.showGeocoding = false
    MapSVGAdminCsvController.call(this, container, admin, mapsvg)
  }
  window.MapSVGAdminRegionsCsvController = MapSVGAdminRegionsCsvController
  MapSVG.extend(MapSVGAdminRegionsCsvController, window.MapSVGAdminCsvController)
})(jQuery, window, window.MapSVG)
