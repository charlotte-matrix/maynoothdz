;(function ($, window, MapSVG) {
  var MapSVGAdminRegionsSettingsController = function (container, admin, mapsvg) {
    this.name = "regions-settings"
    this.database = mapsvg.regionsDatabase

    MapSVGAdminController.call(this, container, admin, mapsvg)
  }
  window.MapSVGAdminRegionsSettingsController = MapSVGAdminRegionsSettingsController
  MapSVG.extend(MapSVGAdminRegionsSettingsController, window.MapSVGAdminController)

  MapSVGAdminRegionsSettingsController.prototype.viewLoaded = function () {
    var _this = this
    this.mapsvg.regionsRepository.getSchema().events.on("update", () => this.render())
    this.database.events.on("afterLoad", function () {
      _this.render()
    })
  }

  MapSVGAdminRegionsSettingsController.prototype.countOrphanedRegions = function () {
    var loaded = this.database.getLoaded ? this.database.getLoaded() : []
    var count = 0
    loaded.forEach(function (region) {
      var data = region.getData ? region.getData() : region
      if (data && data.orphaned) {
        count++
      }
    })
    return count
  }

  MapSVGAdminRegionsSettingsController.prototype.setEventHandlers = function () {
    var _this = this
    this.view.on("click", "#mapsvg-clear-regions-btn", function () {
      if (confirm("Are you sure you want to clear the list of Regions?")) {
        _this.database
          .clear()
          .done(function () {
            $.growl.notice({
              title: "",
              message: "The list of Regions is cleared",
              duration: 700,
            })
          })
          .fail(function () {
            $.growl.error({
              title: "Server error",
              message: "Can't clear the list of regions",
            })
          })
      }
    })
    this.view.on("click", "#mapsvg-clean-orphan-regions-btn", function (e) {
      e.preventDefault()
      var count = _this.countOrphanedRegions()
      if (
        !confirm(
          "Delete " +
            count +
            " orphaned region record(s) and detach linked objects? This cannot be undone.",
        )
      ) {
        return
      }
      var schema = _this.database.getSchema()
      var path = "collection/" + schema.name + "/orphans"
      _this.database.server
        .delete(path)
        .done(function (response) {
          var deleted = response && response.deleted != null ? response.deleted : count
          $.growl.notice({
            title: "",
            message: "Removed " + deleted + " orphaned region record(s)",
            duration: 2000,
          })
          return _this.database.find(_this.database.query || { perpage: 0 })
        })
        .done(function () {
          _this.render()
        })
        .fail(function () {
          $.growl.error({
            title: "Server error",
            message: "Can't clean orphaned region records",
          })
        })
    })
    this.view.on("click", "#mapsvg-set-prefix-btn", function (e) {
      e.preventDefault()
      _this.admin.save().done(function () {
        window.location.reload()
      })
    })
  }

  MapSVGAdminRegionsSettingsController.prototype.getTemplateData = function () {
    var _this = this
    var statusField = _this.mapsvg.regionsRepository.getSchema().getFieldByType("status")
    var options = statusField ? statusField.options : []
    var mapOptions = _this.mapsvg.options || {}
    return {
      regionPrefix: mapOptions.regionPrefix,
      cursor: mapOptions.cursor,
      multiSelect: mapOptions.multiSelect,
      disableAll: mapOptions.disableAll,
      regionsDynamicStatus: mapOptions.regionsDynamicStatus,
      statuses: options,
      orphanedCount: _this.countOrphanedRegions(),
    }
  }
})(jQuery, window, window.MapSVG)
