/* global Papa, MapSVGAdminController */
;(function ($, window, MapSVG) {
  /**
   * Shared base controller for CSV import / Google Sheets sync.
   * Subclasses must set before calling this constructor:
   *   this.name           – e.g. "database-csv"
   *   this.database       – Repository instance (objectsRepository / regionsDatabase)
   *   this.collectionType – "objects" | "regions"
   */

  // ─── Module-level pure utilities ─────────────────────────────────────────────

  var OPTIONS_TYPES = ["select", "checkboxes", "radio", "status"]
  var OPTIONS_SCAN_TYPES = ["select", "checkboxes", "radio"]
  var IMPORT_SETTINGS_KEYS = [
    "gsSync",
    "gsAutoRefetch",
    "gsSyncMode",
    "gsCsvUrl",
    "gsCsvHash",
    "gsRefetchInterval",
    "gsAutoId",
    "gsIdFieldName",
    "gsSheetName",
    "gsGeocode",
    "gsGeocodeConvertLatLngToAddress",
    "gsGeocodeConvertAddressToLatLng",
    "gsPaidGeocoding",
    "gsAppScriptUrl",
    "gsSecret",
    "gsImportFinishedAt",
    "gsImportStartedAt",
    "gsImportLastUpdatedAt",
    "gsImportEstimatedSeconds",
    "gsImportSource",
    "gsImportSourceValid",
    "gsImportSkipFields",
  ]

  /** Converts a PHP ini size string (e.g. "8M", "256K") to bytes. */
  function parsePhpSize(str) {
    if (!str) return Infinity
    var num = parseInt(str, 10)
    var unit = str.slice(-1).toUpperCase()
    var mult = { K: 1024, M: 1024 * 1024, G: 1024 * 1024 * 1024 }
    return mult[unit] ? num * mult[unit] : num
  }

  /** Adaptive geocoding poll interval: ~3 s when nearly done, up to 60 s for huge queues. */
  function geocodingPollInterval(pending) {
    var min = 2000,
      max = 60000,
      scale = 100000
    return Math.round(min + (Math.log10(Math.max(pending, 1)) / Math.log10(scale)) * (max - min))
  }

  /** localStorage helpers for persisting an in-progress import job across page reloads. */
  function importJobKey(schemaName) {
    return "mapsvg_import_job_" + schemaName
  }
  function saveImportJob(schemaName, token, total) {
    try {
      localStorage.setItem(importJobKey(schemaName), JSON.stringify({ token: token, total: total }))
    } catch (e) {
      // Ignore storage errors (private mode/quota); import can continue without persistence.
    }
  }
  function clearImportJob(schemaName) {
    try {
      localStorage.removeItem(importJobKey(schemaName))
    } catch (e) {
      // Ignore storage errors (private mode/quota); import can continue without persistence.
    }
  }
  function loadImportJob(schemaName) {
    try {
      var raw = localStorage.getItem(importJobKey(schemaName))
      return raw ? JSON.parse(raw) : null
    } catch (e) {
      return null
    }
  }

  function toTitleCase(str) {
    return str
      .replace(/_/g, " ")
      .replace(/([a-z])([A-Z])/g, "$1 $2")
      .replace(/\b\w/g, function (c) {
        return c.toUpperCase()
      })
  }

  function buildTypeSelect() {
    var types = [
      ["text", "Text"],
      ["textarea", "Textarea"],
      ["number", "Number"],
      ["select", "Select"],
      ["checkbox", "Checkbox"],
      ["checkboxes", "Checkboxes"],
      ["radio", "Radio"],
      ["status", "Status"],
      ["image", "Image"],
      ["images", "Images"],
      ["location", "Location"],
      ["date", "Date"],
      ["datetime", "Date & Time"],
      ["post", "Post"],
      ["region", "Region"],
    ]
    var $sel = $("<select>")
      .css({ width: "100%" })
      .addClass("form-select form-select-sm mapsvg-select2")
    types.forEach(function (t) {
      $sel.append($("<option>").val(t[0]).text(t[1]))
    })
    return $sel
  }

  function optionsPreviewText(opts) {
    var shown = opts.slice(0, 5).join(", ")
    return "Options: " + shown + (opts.length > 5 ? " (+" + (opts.length - 5) + " more)" : "")
  }

  function splitFieldNameWords(name) {
    return String(name || "")
      .replace(/[_\-\s]+/g, " ")
      .replace(/([a-z0-9])([A-Z])/g, "$1 $2")
      .toLowerCase()
      .split(/\s+/)
      .filter(Boolean)
  }

  function suggestFieldTypeByName(name) {
    var fieldName = String(name || "")
    var lower = fieldName.toLowerCase()
    var words = splitFieldNameWords(fieldName)
    var optionWords = ["options", "variants", "size", "color"]
    var exactTypes = {
      tags: "select",
      category: "select",
      amenities: "select",
      post: "post",
      location: "location",
      regions: "region",
      description: "textarea",
      title: "text",
      header: "text",
      qty: "text",
      amount: "text",
    }

    if (exactTypes[fieldName]) return exactTypes[fieldName]

    if (/^is_/.test(fieldName) || /^is[A-Z]/.test(fieldName)) return "checkbox"

    if (
      /_at$/i.test(fieldName) ||
      /^(created|updated|last_updated|start)/i.test(fieldName) ||
      /^lastUpdated/.test(fieldName) ||
      /(^|_)date_/.test(lower) ||
      /_date($|_)/.test(lower) ||
      /date[A-Z]/.test(fieldName) ||
      /[A-Z]date/.test(fieldName)
    ) {
      return "date"
    }

    if (
      ["image", "images", "photos"].some(function (token) {
        return lower.indexOf(token) !== -1
      })
    ) {
      return "images"
    }

    if (
      words.some(function (w) {
        return optionWords.indexOf(w) !== -1
      })
    )
      return "select"
    if (lower.indexOf("number") !== -1) return "text"

    return "text"
  }

  function reinitSelect2($el) {
    if ($el.data("select2")) $el.mselect2("destroy")
    $el.mselect2()
  }

  // ─── Constructor ──────────────────────────────────────────────────────────────

  var MapSVGAdminCsvController = function (container, admin, mapsvg) {
    this.name = "csv"
    // this.database, this.collectionType, this.showGeocoding are set by subclass before this call
    this.schema = this.database.schema
    this.server = this.database.server
    this.schemaName = this.database.schema.name
    this.schemaRepo = window.mapsvg.useRepository("schemas", mapsvg)
    this.gsSheetData = null // cache for AppScript sheet/column data
    this.csvIdOptions = { upload: [], remote: [] } // cached ID column options per source
    this.csvIdFieldSelection = { upload: null, remote: null } // source-local ID column; upload must not touch gsIdFieldName
    this.missingFieldsState = { upload: null, remote: null } // missing-fields rows cached per source
    this._activeMissingFieldsSource = null // source currently shown in missing fields UI
    this._uploadValid = false // transient validation flag for upload source
    this._importInProgress = false
    this.importSettings = this.database.importSettings || {}
    this._importSettingsLoaded = !!this.database.importSettingsLoaded
    if (this.showGeocoding === undefined) this.showGeocoding = true
    MapSVGAdminController.call(this, container, admin, mapsvg)
  }
  window.MapSVGAdminCsvController = MapSVGAdminCsvController
  MapSVG.extend(MapSVGAdminCsvController, window.MapSVGAdminController)

  /**
   * Mirrors gs.gsImportSourceValid in templates: upload uses transient _uploadValid; remote uses DB.
   * Treats 1 / "1" / true as valid (avoids mismatch with string values from the API).
   */
  MapSVGAdminCsvController.prototype._isGsImportSourceValid = function () {
    var importSource =
      this.getImportSetting("gsImportSource", "upload") === "remote" ? "remote" : "upload"
    if (importSource === "upload") {
      return !!this._uploadValid
    }
    var v = this.getImportSetting("gsImportSourceValid", 0)
    return v === true || v === 1 || v === "1"
  }

  // ─── Template data ────────────────────────────────────────────────────────────

  MapSVGAdminCsvController.prototype.getTemplateData = function () {
    var options = MapSVGAdminController.prototype.getTemplateData.call(this)
    options.idPrefix = this.collectionType || this.name || "csv"
    options.showGeocoding = this.showGeocoding && this._hasLocationFieldInSchema()
    options.collectionType = this.collectionType
    var importSource =
      this.getImportSetting("gsImportSource", "upload") === "remote" ? "remote" : "upload"
    options.gs = {
      showRefetch: importSource === "remote" && this.getImportSetting("gsCsvUrl", ""),
      isRemote: importSource === "remote",
      gsCsvUrl: this.getImportSetting("gsCsvUrl", ""),
      gsSync: this.getImportSetting("gsSync", 0) ? 1 : 0,
      gsAutoRefetch: this.getImportSetting("gsAutoRefetch", 0) ? 1 : 0,
      gsSyncMode: this.getImportSetting("gsSyncMode", "r"),
      gsRefetchInterval: this.getImportSetting("gsRefetchInterval", 24),
      gsIdFieldName: this.getImportSetting("gsIdFieldName", ""),
      gsSheetName: this.getImportSetting("gsSheetName", "Sheet1"),
      gsGeocode: this.getImportSetting("gsGeocode", 0) ? 1 : 0,
      gsGeocodeConvertLatLngToAddress: this.getImportSetting("gsGeocodeConvertLatLngToAddress", 0)
        ? 1
        : 0,
      gsGeocodeConvertAddressToLatLng:
        this.getImportSetting("gsGeocodeConvertAddressToLatLng", 1) !== 0 ? 1 : 0,
      gsPaidGeocoding: this.getImportSetting("gsPaidGeocoding", 0) ? 1 : 0,
      gsAppScriptUrl: this.getImportSetting("gsAppScriptUrl", ""),
      gsConnected: !!this.getImportSetting("gsSecret", ""),
      gsImportFinishedAt: this.getImportSetting("gsImportFinishedAt", null),
      gsImportStartedAt: this.getImportSetting("gsImportStartedAt", null),
      gsImportEstimatedSeconds: this.getImportSetting("gsImportEstimatedSeconds", null),
      gsImportSourceValid: this._isGsImportSourceValid() ? 1 : 0,
    }
    return options
  }

  MapSVGAdminCsvController.prototype.getImportSetting = function (key, defaultValue) {
    if (
      this.importSettings &&
      this.importSettings[key] !== undefined &&
      this.importSettings[key] !== null
    ) {
      return this.importSettings[key]
    }
    if (this.schema && this.schema[key] !== undefined && this.schema[key] !== null) {
      return this.schema[key]
    }
    return defaultValue
  }

  MapSVGAdminCsvController.prototype.setImportSetting = function (key, value) {
    if (!this.importSettings) {
      this.importSettings = {}
    }
    this.importSettings[key] = value
    if (this.database) {
      this.database.importSettings = this.importSettings
      this.database.importSettingsLoaded = true
    }
  }

  MapSVGAdminCsvController.prototype._getActiveImportSource = function () {
    if (this.view && this.formElements && this.formElements.GsImportSourceInputs) {
      var $radios = this._$fe("GsImportSourceInputs")
      if ($radios.length) {
        return $radios.filter(":checked").val() === "remote" ? "remote" : "upload"
      }
    }
    return this.getImportSetting("gsImportSource", "upload") === "remote" ? "remote" : "upload"
  }

  MapSVGAdminCsvController.prototype.getSelectedIdFieldName = function (source) {
    source = source || this._getActiveImportSource()
    if (source === "upload") {
      var uploadId = this.csvIdFieldSelection ? this.csvIdFieldSelection.upload : null
      return typeof uploadId === "string" ? uploadId : ""
    }
    var value = this.getImportSetting("gsIdFieldName", "")
    return typeof value === "string" ? value : ""
  }

  MapSVGAdminCsvController.prototype.formatIdFieldSummary = function (value) {
    return value ? value : "No ID field in CSV"
  }

  MapSVGAdminCsvController.prototype._hasLocationFieldInSchema = function () {
    return (this.schema.fields || []).some(function (field) {
      return field && field.type === "location"
    })
  }

  MapSVGAdminCsvController.prototype._normalizeColumns = function (columns) {
    return (columns || []).map(function (col) {
      return String(col || "")
        .replace(/^\uFEFF/, "")
        .trim()
    })
  }

  MapSVGAdminCsvController.prototype._resolvePreferredIdField = function (columns) {
    var normalized = this._normalizeColumns(columns)
    var source = this._getActiveImportSource()
    if (source === "upload") {
      var uploadId = this.csvIdFieldSelection ? this.csvIdFieldSelection.upload : null
      if (typeof uploadId === "string" && (uploadId === "" || normalized.indexOf(uploadId) !== -1)) {
        return uploadId
      }
    } else {
      var currentId = this.getSelectedIdFieldName("remote")
      if (currentId && normalized.indexOf(currentId) !== -1) {
        return currentId
      }
    }
    return normalized.indexOf("id") !== -1 ? "id" : ""
  }

  MapSVGAdminCsvController.prototype._renderIdFieldOptions = function (columns) {
    var $idSel = this._$fe("IdField")
    if (!$idSel.length) return

    var normalized = this._normalizeColumns(columns)
    var selectedId = this._resolvePreferredIdField(normalized)
    $idSel.empty()
    $idSel.append(
      $("<option>")
        .val("")
        .text("No ID in CSV file")
        .prop("selected", selectedId === ""),
    )
    normalized.forEach(function (col) {
      $idSel.append(
        $("<option>")
          .val(col)
          .text(col)
          .prop("selected", col === selectedId),
      )
    })
    reinitSelect2($idSel)
  }

  MapSVGAdminCsvController.prototype._syncGeocodingVisibility = function () {
    var $group = this._$fe("GeocodingGroup")
    if (!$group.length) return
    if (!this.showGeocoding || !this._hasLocationFieldInSchema()) {
      this._$fe("Geocode").prop("checked", false)
      this._$fe("OptLatlngToAddress").prop("checked", false)
      this._$fe("OptAddressToLatlng").prop("checked", false)
      this._$fe("OptPaidGeocoding").prop("checked", false)
      this.view.find(this.$id("gs-geocoding-options")).hide()
      $group.hide()
      return
    }
    $group.show()
  }

  MapSVGAdminCsvController.prototype._setImportButtonBusyState = function (isBusy) {
    var $btn = this._$fe("BtnGsImport")
    if (!$btn.length) return
    if (isBusy) {
      if (!$btn.data("mapsvg-original-html")) {
        $btn.data("mapsvg-original-html", $btn.html())
      }
      $btn
        .prop("disabled", true)
        .html(
          '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Import now',
        )
      return
    }
    $btn.prop("disabled", false)
    if ($btn.data("mapsvg-original-html")) {
      $btn.html($btn.data("mapsvg-original-html"))
    }
  }

  MapSVGAdminCsvController.prototype._setImportInProgress = function (isInProgress) {
    this._importInProgress = !!isInProgress
    this._setImportButtonBusyState(this._importInProgress)
  }

  /**
   * Returns a scoped jQuery selector for an element ID used in the CSV template.
   * The CSV template is shared by both objects and regions tabs — all IDs are
   * auto-prefixed via _prefixIds() to avoid duplicates when both tabs are open.
   *
   * @param {string} name - bare ID name without leading #, e.g. "mapsvg-csv-file"
   * @returns {string} selector e.g. "#objects-mapsvg-csv-file"
   */
  MapSVGAdminCsvController.prototype.$id = function (name) {
    return "#" + this.templateData.idPrefix + "-" + name
  }

  /**
   * Selector keys in formElements are resolved against the current DOM under this.view (survives redraw).
   */
  MapSVGAdminCsvController.prototype._$fe = function (key) {
    return this.view.find(this.formElements[key])
  }

  /** First matching node under this.view for an explicit selector (e.g. cached formElements value). */
  MapSVGAdminCsvController.prototype.formFindFirst = function (selector) {
    return this.view.find(selector).first()
  }

  /**
   * Override redraw() to prefix all [id] and label[for] attributes after the
   * parent renders the template. This ensures IDs are unique when the same
   * template is rendered for two different tabs (e.g. objects + regions CSV).
   */
  MapSVGAdminCsvController.prototype.redraw = function () {
    MapSVGAdminController.prototype.redraw.call(this)
    this._prefixIds()
    // Parent invokes setEventHandlers() before viewLoaded(); refresh selector map after each render.
    this._cacheFormElements()
    this._syncSaveSettingsWrap()
    this._syncGeocodingVisibility()
    this._setImportButtonBusyState(this._importInProgress)
  }

  /**
   * Selector strings for CSV template nodes (IDs prefixed in DOM). Use _$fe(key) or delegated events on this.view.
   * Keys sorted alphabetically within each group.
   */
  MapSVGAdminCsvController.prototype._cacheFormElements = function () {
    var $id = this.$id.bind(this)
    var fe = {}
    // Buttons
    fe.BtnCsvCheck = $id("mapsvg-btn-csv-check")
    fe.BtnGsAddFields = $id("mapsvg-btn-gs-add-fields")
    fe.BtnGsCheckCsv = $id("mapsvg-btn-gs-check-csv")
    fe.BtnGsCheckUrl = $id("mapsvg-btn-gs-check-url")
    fe.BtnGsConnect = $id("mapsvg-btn-gs-connect")
    fe.BtnGsImport = $id("mapsvg-btn-gs-import")
    fe.BtnGsReset = $id("mapsvg-btn-gs-reset")
    fe.BtnGsSave = $id("mapsvg-btn-gs-save")
    fe.BtnGsSyncCancel = $id("mapsvg-btn-gs-sync-cancel")
    fe.BtnGsSyncEdit = $id("mapsvg-btn-gs-sync-edit")
    fe.BtnGsSyncSave = $id("mapsvg-btn-gs-sync-save")
    // Inputs & selects
    fe.AutoRefetch = $id("mapsvg-gs-autorefetch")
    fe.CsvUrl = $id("mapsvg-gs-csv-url")
    fe.Geocode = $id("mapsvg-gs-geocode")
    fe.GeocodingGroup = $id("gs-geocoding-group")
    fe.GsImportSourceInputs = 'input[name="gsImportSource"]'
    fe.IdField = $id("mapsvg-gs-id-field")
    fe.Interval = $id("mapsvg-gs-interval")
    fe.OptAddressToLatlng = $id("gs-opt-address-to-latlng")
    fe.OptLatlngToAddress = $id("gs-opt-latlng-to-address")
    fe.OptPaidGeocoding = $id("gs-opt-paid-geocoding")
    fe.SetupKey = $id("mapsvg-gs-setup-key")
    fe.SheetName = $id("mapsvg-gs-sheet-name")
    // File upload
    fe.CsvFile = $id("mapsvg-csv-file")
    // Panels & layout blocks
    fe.AppScriptFields = $id("gs-appscript-fields")
    fe.AppScriptUrl = $id("mapsvg-gs-appscript-url")
    fe.CheckedPanelValidatedBundle =
      $id("gs-common-checked-panel") +
      ", " +
      $id("gs-validated-blocks") +
      ", " +
      $id("gs-validated-actions")
    fe.CommonCheckedEdit = $id("gs-common-checked-edit")
    fe.CommonCheckedPanel = $id("gs-common-checked-panel")
    fe.CommonCheckedSummary = $id("gs-common-checked-summary")
    fe.ConnectionStatus = $id("mapsvg-gs-connection-status")
    fe.MissingFieldsPanel = $id("gs-missing-fields-panel")
    fe.MissingFieldsTable = $id("gs-missing-fields-table")
    fe.RemoteOptions = $id("gs-remote-options")
    fe.RemoteSection = $id("gs-remote-section")
    fe.SaveSettingsWrap = $id("gs-save-settings-wrap")
    fe.SetupKeyRowConnectRows = $id("gs-setup-key-row") + ", " + $id("gs-connect-row")
    fe.UploadSection = $id("gs-upload-section")
    fe.ValidatedBlocksActions = $id("gs-validated-blocks") + ", " + $id("gs-validated-actions")
    // Labels, spinners, inline messages
    fe.AddFieldsStatus = $id("mapsvg-gs-add-fields-status")
    fe.CheckCsvLabel = $id("mapsvg-gs-check-csv-label")
    fe.CheckLabel = $id("mapsvg-gs-check-label")
    fe.ConnectStatus = $id("mapsvg-gs-connect-status")
    fe.CsvFileCheckLabel = $id("mapsvg-csv-file-check-label")
    fe.CsvFileError = $id("mapsvg-csv-file-error")
    fe.CsvFileSpinner = $id("mapsvg-csv-file-spinner")
    fe.GsCsvError = $id("mapsvg-gs-csv-error")
    fe.GsCsvSpinner = $id("mapsvg-gs-csv-spinner")
    fe.SheetsError = $id("mapsvg-gs-sheets-error")
    fe.SheetsSpinner = $id("mapsvg-gs-sheets-spinner")
    fe.SummaryIdField = $id("gs-summary-id-field")
    fe.SummaryMissing = $id("gs-summary-missing")
    fe.SummarySkippedList = $id("gs-summary-skipped-list")
    fe.SyncStatus = $id("mapsvg-gs-sync-status")
    // Geocoding quota toggles
    fe.GeocodingQuotaFree = $id("gs-geocoding-quota-free")
    fe.GeocodingQuotaPaid = $id("gs-geocoding-quota-paid")
    // Status & schema save
    fe.GsStatus = $id("mapsvg-gs-status")
    // AppScript connect: combined selector for disabling inputs
    fe.AppScriptUrlSheetNameIdField =
      $id("mapsvg-gs-appscript-url") +
      ", " +
      $id("mapsvg-gs-sheet-name") +
      ", " +
      $id("mapsvg-gs-id-field")
    // Roles (no prefixed id)
    fe.ImportStatusBar = "[data-csv-role='import-status']"
    fe.InstructionsModal = '[data-csv-role="instructions-modal"]'
    fe.InstructionsTrigger = '[data-csv-role="instructions-trigger"]'
    fe.LogsModal = "[data-csv-role='logs-modal']"
    fe.UploadBlockedAlert = '[data-csv-role="upload-blocked-alert"]'

    this.formElements = fe
  }

  /**
   * Prepend this.templateData.idPrefix to every [id] and matching label[for]
   * inside this.view, avoiding ID collisions between tabs.
   */
  MapSVGAdminCsvController.prototype._prefixIds = function () {
    var prefix = this.templateData.idPrefix + "-"
    this.view.find("[id]").each(function () {
      var $el = $(this)
      $el.attr("id", prefix + $el.attr("id"))
    })
    this.view.find("label[for]").each(function () {
      var $label = $(this)
      $label.attr("for", prefix + $label.attr("for"))
    })
    // mapsvg-toggle-visibility targets #bare-id — keep in sync with prefixed DOM ids
    ;["data-toggle-visibility", "data-toggle-visibility-reverse"].forEach(function (attr) {
      _prefixToggleAttr(this.view, prefix, attr)
    }, this)
  }

  function _prefixToggleAttr($root, prefix, attr) {
    $root.find("[" + attr + "]").each(function () {
      var $el = $(this)
      var sel = $el.attr(attr)
      if (!sel || sel.charAt(0) !== "#") return
      var bareId = sel.slice(1)
      if (bareId.indexOf(prefix) === 0) return
      $el.attr(attr, "#" + prefix + bareId)
    })
  }

  // ─── Lifecycle: init side-effects after DOM is ready ─────────────────────────

  /** One-time init after the template DOM exists and import settings are merged (see viewLoaded). */
  MapSVGAdminCsvController.prototype._finishCsvViewLoaded = function () {
    this.initModal()
    this.initLogsModal()
    this.showPhpLimits()
    this.loadImportStatus()

    this.schema.events.on(
      "update",
      function () {
        this.syncUploadState()
        this._syncGeocodingVisibility()
      }.bind(this),
    )

    // If remote source is already validated, show the summary on page load
    this._initImportSummary()
    // Save-wrap defaults hidden in template; align after summary init (remote validated shows it).
    this._syncSaveSettingsWrap()

    // We don't need to fetch CSV on each view load!
    // var prefillCsvUrl = this.view.find(_this.$id("mapsvg-gs-csv-url")).val().trim()
    // if (prefillCsvUrl) this.checkCsvUrl(prefillCsvUrl)

    // This is for later - don't delete this!
    /*
    var prefillAppScriptUrl = this._$fe('AppScriptUrl').val().trim()
    if (prefillAppScriptUrl && !this.schema.gsSecret) {
      this.loadSheets(prefillAppScriptUrl)
    } else if (prefillAppScriptUrl && this.schema.gsSecret) {
      this._$fe('AppScriptFields').show()
    }
      */

    var storedJob = loadImportJob(this.schemaName)
    if (storedJob) {
      this._setImportInProgress(true)
      $.growl.notice({ title: "", message: "Import was in progress — resuming…" })
      this.driveImport(this.schemaName, storedJob.token, storedJob.total, 0, true)
    } else {
      var startedAt = this.getImportSetting("gsImportStartedAt", null)
      var finishedAt = this.getImportSetting("gsImportFinishedAt", null)
      this._setImportInProgress(!!startedAt && !finishedAt)
    }
  }

  MapSVGAdminCsvController.prototype.viewLoaded = function () {
    MapSVGAdminController.prototype.viewLoaded.call(this)
    if (!this._importSettingsLoaded) {
      var _this = this
      this.fetchImportSettings().always(function () {
        _this._importSettingsLoaded = true
        _this.redraw()
        _this._finishCsvViewLoaded()
      })
      return
    }
    this._finishCsvViewLoaded()
  }

  MapSVGAdminCsvController.prototype.fetchImportSettings = function () {
    var _this = this
    if (this.database && typeof this.database.getImportSettings === "function") {
      return this.database
        .getImportSettings()
        .done(function (settings) {
          _this.importSettings = settings || {}
        })
        .fail(function () {
          _this.importSettings = {}
        })
    }
    var settingsRoute = "schemas/" + this.schema.id + "/import-settings"
    return this.server.get(settingsRoute).done(function (body) {
      var settings = (body && body.importSettings) || {}
      _this.importSettings = settings
    })
  }

  MapSVGAdminCsvController.prototype.saveImportSettings = function (fields) {
    var _this = this
    if (this.database && typeof this.database.updateImportSettings === "function") {
      return this.database.updateImportSettings(fields).done(function (settings) {
        _this.importSettings = settings || {}
      })
    }
    var settingsRoute = "schemas/" + this.schema.id + "/import-settings"
    return this.server.put(settingsRoute, fields).done(function (body) {
      var settings = (body && body.importSettings) || {}
      _this.importSettings = settings
    })
  }

  MapSVGAdminCsvController.prototype._initImportSummary = function () {
    var radioRemote =
      this.view && this._$fe("GsImportSourceInputs").filter(":checked").val() === "remote"
    if (!radioRemote) return

    var remoteDbValid = this._isGsImportSourceValid()
    if (!remoteDbValid) return

    // Align with gs-validated-blocks visibility (template uses gs.gsImportSourceValid)
    this._$fe("ValidatedBlocksActions").show()

    // Show summary panel with saved data
    var $panel = this._$fe("CommonCheckedPanel")
    $panel.show()
    this._$fe("CommonCheckedEdit").hide()
    this._$fe("CommonCheckedSummary").show()
    this._$fe("SummaryIdField").text(this.formatIdFieldSummary(this.getSelectedIdFieldName()))

    var savedSkipFields = this.getImportSetting("gsImportSkipFields", null)
    var skipFields = savedSkipFields ? JSON.parse(savedSkipFields) : []
    if (skipFields.length) {
      this._$fe("SummarySkippedList").text(skipFields.join(", "))
      this._$fe("SummaryMissing").show()
    } else {
      this._$fe("SummaryMissing").hide()
    }

    this._syncSaveSettingsWrap()
  }

  /** Hide "Save settings" for upload source; show only for remote when validated actions are visible. */
  MapSVGAdminCsvController.prototype._syncSaveSettingsWrap = function () {
    if (!this.view || !this.view.length) return
    var $btnSave = this._$fe("BtnGsSave")
    var $radios = this._$fe("GsImportSourceInputs")
    if (!$btnSave.length || !$radios.length) return

    var isRemote = $radios.filter(":checked").val() === "remote"
    // Upload is transient: never show Save settings regardless of validated-blocks visibility.
    if (!isRemote) {
      $btnSave.hide()
      return
    }

    var validatedVisible = this._$fe("ValidatedBlocksActions").is(":visible")
    $btnSave.toggle(validatedVisible)
  }

  MapSVGAdminCsvController.prototype._resetMissingFieldsUi = function () {
    this._$fe("MissingFieldsTable").find("tbody").empty()
    this._$fe("MissingFieldsPanel").hide()
    this._activeMissingFieldsSource = null
  }

  MapSVGAdminCsvController.prototype._renderMissingFields = function (rows, source) {
    var _this = this
    var idPrefix = (_this.templateData && _this.templateData.idPrefix) || "csv"
    var $tbody = _this._$fe("MissingFieldsTable").find("tbody").empty()
    rows.forEach(function (rowData, rowIndex) {
      var opts = rowData.options || []
      var fieldId = idPrefix + "-gs-missing-field-" + rowIndex
      var $check = $("<input>")
        .attr({
          type: "checkbox",
          class: "form-check-input gs-field-add-check",
          id: fieldId,
        })
        .prop("checked", !!rowData.checked)
      var $optPreview = $("<div>")
        .addClass("gs-opts-preview form-text mt-1")
        .data("options", opts)
        .data("multiselect", !!rowData.multiselect)
        .hide()
      var $typeSel = buildTypeSelect()
      $typeSel.on("change", function () {
        var needsOpts = OPTIONS_TYPES.indexOf($(this).val()) !== -1
        needsOpts && opts.length
          ? $optPreview.text(optionsPreviewText(opts)).show()
          : $optPreview.hide()
      })
      $typeSel.val(rowData.type || "text").trigger("change")
      $tbody.append(
        $("<tr>")
          .append(
            $("<td>")
              .addClass("text-center")
              .css({ "vertical-align": "middle", padding: "6px", "padding-top": "14px" })
              .append($check),
          )
          .append(
            $("<td>")
              .css({ "vertical-align": "middle", padding: "6px" })
              .append(
                $("<label>")
                  .attr("for", fieldId)
                  .addClass("form-check-label mb-0")
                  .css("cursor", "pointer")
                  .text(rowData.name),
              ),
          )
          .append(
            $("<td>").append(
              $("<input>")
                .attr({
                  type: "text",
                  class: "form-control form-control-sm",
                  "data-col": rowData.name,
                })
                .val(rowData.label || toTitleCase(rowData.name)),
            ),
          )
          .append($("<td>").append($typeSel).append($optPreview)),
      )
    })
    _this._activeMissingFieldsSource = source
    _this._$fe("MissingFieldsPanel").toggle(rows.length > 0)
    _this
      ._$fe("MissingFieldsTable")
      .find("tbody select")
      .each(function () {
        reinitSelect2($(this))
      })
  }

  MapSVGAdminCsvController.prototype._extractUniqueOptionsFromUploadCsv = function (
    targetColumns,
    onDone,
    onFail,
  ) {
    var fileInput = this._$fe("CsvFile")[0]
    if (!fileInput || !fileInput.files || !fileInput.files[0]) {
      onFail("Please choose a CSV file first.")
      return
    }
    var wantedColumns = Object.keys(targetColumns || {})
    if (!wantedColumns.length) {
      onDone({})
      return
    }
    var uniqueByColumn = {}
    var multiselectByColumn = {}
    wantedColumns.forEach(function (col) {
      uniqueByColumn[col] = new Set()
      multiselectByColumn[col] = false
    })
    var isHeaderRow = true
    Papa.parse(fileInput.files[0], {
      worker: true,
      skipEmptyLines: true,
      step: function (results) {
        var row = results && results.data ? results.data : []
        if (isHeaderRow) {
          isHeaderRow = false
          return
        }
        wantedColumns.forEach(function (col) {
          var colIndex = targetColumns[col]
          if (colIndex === undefined || colIndex < 0) return
          var raw = row[colIndex] != null ? String(row[colIndex]).trim() : ""
          if (!raw) return
          var parts = raw
            .split(",")
            .map(function (v) {
              return v.trim()
            })
            .filter(Boolean)
          if (parts.length > 1) multiselectByColumn[col] = true
          parts.forEach(function (value) {
            uniqueByColumn[col].add(value)
          })
        })
      },
      complete: function () {
        var out = {}
        wantedColumns.forEach(function (col) {
          out[col] = {
            options: Array.from(uniqueByColumn[col]),
            multiselect: !!multiselectByColumn[col],
          }
        })
        onDone(out)
      },
      error: function () {
        onFail("Could not parse CSV file for field options.")
      },
    })
  }

  MapSVGAdminCsvController.prototype.viewDidAppear = function () {
    MapSVGAdminController.prototype.viewDidAppear.call(this)
    this._initImportSummary()
  }

  // ─── Business logic methods ───────────────────────────────────────────────────

  MapSVGAdminCsvController.prototype.initModal = function () {
    var _this = this
    var $instrModal = _this._$fe("InstructionsModal")
    if (!$instrModal.length) return
    $instrModal.appendTo("body")
    var instrModal = new bootstrap.Modal($instrModal[0], { keyboard: true })
    _this.formFindFirst(_this.formElements.InstructionsTrigger).on("click", function (e) {
      e.preventDefault()
      instrModal.show()
    })
  }

  MapSVGAdminCsvController.prototype.showPhpLimits = function () {
    var phpIni = window.mapsvgBackendParams && window.mapsvgBackendParams.phpIni
    if (!phpIni) return
    this._$fe("CsvFile")
      .parent()
      .after(
        '<small class="form-text text-muted mt-1">Server limit: ' +
          phpIni.upload_max_filesize +
          " per file (post_max_size: " +
          phpIni.post_max_size +
          ")</small>",
      )
  }

  MapSVGAdminCsvController.prototype.syncUploadState = function () {
    var blocked = !!this.getImportSetting("gsSync", 0)
    this._$fe("UploadBlockedAlert").toggle(blocked)
    this._$fe("CsvFile").prop("disabled", blocked)
  }

  MapSVGAdminCsvController.prototype.saveSchemaFields = function (
    fields,
    statusEl,
    successMsg,
    onDone,
    onFail,
  ) {
    var _this = this
    var $status = statusEl && statusEl.jquery ? statusEl : _this.view.find(statusEl)
    $status.text("Saving…")
    var schemaFields = {}
    var importFields = {}
    Object.keys(fields || {}).forEach(function (k) {
      if (IMPORT_SETTINGS_KEYS.indexOf(k) !== -1) {
        importFields[k] = fields[k]
      } else {
        schemaFields[k] = fields[k]
      }
    })
    var reqs = []
    if (Object.keys(schemaFields).length) {
      _this.schema.update(schemaFields)
      reqs.push(_this.schemaRepo.update(_this.schema))
    }
    if (Object.keys(importFields).length) {
      reqs.push(_this.saveImportSettings(importFields))
    }
    $.when
      .apply($, reqs)
      .done(function () {
        $status.text("Saved")
        setTimeout(function () {
          $status.text("")
        }, 2000)
        if (successMsg) $.growl.notice({ title: "", message: successMsg })
        if (onDone) onDone()
      })
      .fail(function () {
        $status.text("")
        $.growl.error({ title: "", message: "Failed to save settings" })
        if (onFail) onFail()
      })
  }

  /**
   * Rejects Google Sheets web-view / pubhtml URLs; accepts CSV export links only.
   * @returns {boolean} true when the URL is usable (or not a Sheets link).
   */
  MapSVGAdminCsvController.prototype.validateRemoteCsvUrl = function (url) {
    var getError =
      (window.MapSVG && window.MapSVG.getGoogleSheetsCsvUrlError) ||
      function () {
        return null
      }
    var error = getError(url)
    if (!error) {
      this._$fe("GsCsvError").hide().text("")
      return true
    }
    this._$fe("GsCsvError").text(error).show()
    $.growl.error({ title: "", message: error })
    return false
  }

  // fetch() is intentionally used here — we're fetching an EXTERNAL CSV URL, not a MapSVG endpoint.
  MapSVGAdminCsvController.prototype.checkCsvUrl = function (url) {
    var _this = this
    if (!url) return
    if (!_this.validateRemoteCsvUrl(url)) return
    // If a previously valid remote URL is being re-checked, invalidate it first
    if (_this._isGsImportSourceValid()) {
      _this.setImportSetting("gsImportSourceValid", 0)
      _this.saveImportSettings({ gsImportSourceValid: 0 })
    }
    _this._$fe("GsCsvSpinner").show()
    _this._$fe("CheckCsvLabel").hide()
    _this._$fe("GsCsvError").hide().text("")

    fetch(url)
      .then(function (res) {
        if (!res.ok) throw new Error("HTTP " + res.status)
        return res.text()
      })
      .then(function (text) {
        _this._$fe("GsCsvSpinner").hide()
        _this._$fe("CheckCsvLabel").show()
        _this.applyCsvPreview(text)
        _this._$fe("RemoteOptions").show()
      })
      .catch(function () {
        _this._$fe("GsCsvSpinner").hide()
        _this._$fe("CheckCsvLabel").show()
        _this
          ._$fe("GsCsvError")
          .text("Could not load CSV. Check the URL and ensure it is publicly accessible.")
          .show()
      })
  }

  MapSVGAdminCsvController.prototype.checkUploadedCsv = function () {
    var _this = this
    var fileInput = _this._$fe("CsvFile")[0]
    if (!fileInput || !fileInput.files[0]) {
      _this._$fe("CsvFileError").text("Please choose a CSV file first.").show()
      return
    }
    _this._$fe("CsvFileError").hide().text("")
    _this._$fe("CsvFileSpinner").show()
    _this._$fe("CsvFileCheckLabel").hide()

    var reader = new FileReader()
    reader.onload = function (e) {
      _this._$fe("CsvFileSpinner").hide()
      _this._$fe("CsvFileCheckLabel").show()
      _this.applyCsvPreview(String(e.target.result || ""))
    }
    reader.onerror = function () {
      _this._$fe("CsvFileSpinner").hide()
      _this._$fe("CsvFileCheckLabel").show()
      _this._$fe("CsvFileError").text("Could not read the selected file.").show()
    }
    reader.readAsText(fileInput.files[0])
  }

  MapSVGAdminCsvController.prototype.applyCsvPreview = function (csvText) {
    var _this = this
    var parsed = Papa.parse(csvText, { preview: 501, skipEmptyLines: true })
    if (!parsed.data.length || !parsed.data[0].length) {
      _this._$fe("GsCsvError").text("No columns found in CSV.").show()
      return
    }

    var headers = parsed.data[0].map(function (header) {
      return String(header || "")
        .replace(/^\uFEFF/, "")
        .trim()
    })
    var dataRows = parsed.data.slice(1)
    var columnValues = {}
    var columnMultiselect = {}

    headers.forEach(function (h) {
      columnValues[h] = []
      columnMultiselect[h] = false
    })
    dataRows.forEach(function (row) {
      headers.forEach(function (h, i) {
        var raw = row[i] != null ? String(row[i]).trim() : ""
        if (!raw) return
        var parts = raw
          .split(",")
          .map(function (v) {
            return v.trim()
          })
          .filter(Boolean)
        if (parts.length > 1) columnMultiselect[h] = true
        parts.forEach(function (val) {
          if (columnValues[h].indexOf(val) === -1) columnValues[h].push(val)
        })
      })
    })

    _this._renderIdFieldOptions(headers)

    var schemaFieldNames = (_this.schema.fields || []).map(function (f) {
      return f.name
    })
    schemaFieldNames.push("id")
    var missing = headers.filter(function (h) {
      return schemaFieldNames.indexOf(h) === -1
    })

    var source = _this._$fe("GsImportSourceInputs").filter(":checked").val() || "upload"
    if (missing.length) {
      var savedSkipFields = _this.getImportSetting("gsImportSkipFields", null)
      var skipFields = savedSkipFields ? JSON.parse(savedSkipFields) : []
      var rows = missing.map(function (col) {
        return {
          name: col,
          label: toTitleCase(col),
          type: suggestFieldTypeByName(col),
          options: columnValues[col] || [],
          multiselect: !!columnMultiselect[col],
          checked: skipFields.indexOf(col) === -1,
          columnIndex: headers.indexOf(col),
        }
      })
      _this.missingFieldsState[source] = { headers: headers.slice(), rows: rows }
      _this._renderMissingFields(rows, source)
    } else {
      _this.missingFieldsState[source] = { headers: headers.slice(), rows: [] }
      _this._resetMissingFieldsUi()
      $.growl.notice({
        title: "",
        message: "CSV check passed. No missing fields found — you can proceed to import.",
      })
    }

    // Cache headers for the current import source
    _this.csvIdOptions[source] = headers.slice()
    _this._syncGeocodingVisibility()

    // Remember pre-sync state for Cancel flow
    _this._preSyncValid = _this.getImportSetting("gsImportSourceValid", 0)
    _this._preSyncUploadValid = _this._uploadValid
    _this._preSyncIdField =
      source === "upload" ? _this.csvIdFieldSelection.upload : _this.getSelectedIdFieldName("remote")
    _this._preSyncSkipFields = _this.getImportSetting("gsImportSkipFields", null)

    // Hide validated blocks initially – only show after Continue
    _this._$fe("ValidatedBlocksActions").hide()

    _this._$fe("CommonCheckedPanel").show()
    _this._$fe("CommonCheckedEdit").show()
    _this._$fe("CommonCheckedSummary").hide()
    _this.updateScroll()
  }

  MapSVGAdminCsvController.prototype.addMissingFields = function () {
    var _this = this
    var newFields = []
    _this
      ._$fe("MissingFieldsTable")
      .find("tbody tr")
      .each(function () {
        var $row = $(this)
        if (!$row.find(".gs-field-add-check").is(":checked")) return
        var colName = $row.find("td:nth-child(2)").text().trim()
        var label = $row.find("input[data-col]").val() || colName
        var type = $row.find("select").val() || "text"
        var needsOpts = OPTIONS_TYPES.indexOf(type) !== -1
        var $preview = $row.find(".gs-opts-preview")
        var opts = needsOpts ? $preview.data("options") || [] : []
        newFields.push({
          name: colName,
          label: label,
          type: type,
          multiselect: needsOpts ? !!$preview.data("multiselect") : false,
          options: opts.map(function (v) {
            return { value: v, label: v }
          }),
        })
      })
    if (!newFields.length) return

    var $btn = _this._$fe("BtnGsAddFields")
    $btn.prop("disabled", true)
    _this._$fe("AddFieldsStatus").text("Saving…")

    var updatedFields = (_this.schema.fields || []).concat(newFields)
    _this.schema.update({ fields: updatedFields })
    _this.schemaRepo
      .update(_this.schema)
      .done(function () {
        $btn.prop("disabled", false)
        _this._$fe("MissingFieldsPanel").hide()
        _this._$fe("AddFieldsStatus").text("Added!")
        setTimeout(function () {
          _this._$fe("AddFieldsStatus").text("")
        }, 2500)
        $.growl.notice({ title: "", message: newFields.length + " field(s) added to schema" })
        _this._syncGeocodingVisibility()
        _this.updateScroll()
      })
      .fail(function () {
        $btn.prop("disabled", false)
        _this._$fe("AddFieldsStatus").text("")
        $.growl.error({ title: "", message: "Failed to add fields to schema" })
      })
  }

  MapSVGAdminCsvController.prototype.saveGsSettings = function () {
    var _this = this
    var source = _this._$fe("GsImportSourceInputs").filter(":checked").val() || "upload"
    var fields = {
      gsImportSource: source,
      gsGeocode: _this._$fe("Geocode").is(":checked") ? 1 : 0,
      gsGeocodeConvertLatLngToAddress: _this._$fe("OptLatlngToAddress").is(":checked") ? 1 : 0,
      gsGeocodeConvertAddressToLatLng: _this._$fe("OptAddressToLatlng").is(":checked") ? 1 : 0,
      gsPaidGeocoding: _this._$fe("OptPaidGeocoding").is(":checked") ? 1 : 0,
    }
    var $idField = _this._$fe("IdField")
    if (source === "remote" && $idField.length) {
      fields.gsIdFieldName = $idField.val() || ""
    }

    if (source === "remote") {
      var csvUrl = _this._$fe("CsvUrl").val().trim()
      if (!_this.validateRemoteCsvUrl(csvUrl)) return
      fields.gsCsvUrl = csvUrl
      fields.gsAutoRefetch = _this._$fe("AutoRefetch").is(":checked") ? 1 : 0
      fields.gsRefetchInterval = parseInt(_this._$fe("Interval").val(), 10) || 24
      fields.gsSyncMode = _this.getImportSetting("gsSyncMode", "r")
    } else {
      fields.gsAutoRefetch = 0
    }

    _this.saveSchemaFields(fields, _this._$fe("GsStatus"), "Settings saved")
  }

  MapSVGAdminCsvController.prototype.importNow = function () {
    var _this = this
    if (_this._importInProgress) {
      return
    }
    var source = _this._$fe("GsImportSourceInputs").filter(":checked").val() || "upload"
    if (source === "upload") {
      _this.uploadCsv()
    } else {
      _this.importFromUrl()
    }
  }

  MapSVGAdminCsvController.prototype.isPrimaryKeyNumeric = function () {
    var fields = this.schema.fields || []
    var idField = null
    if (fields.find) {
      idField = fields.find(function (f) {
        return f.type === "id"
      })
    }
    var dbType = idField && idField.db_type ? String(idField.db_type).toLowerCase() : "int(11)"
    return dbType.indexOf("int") !== -1
  }

  MapSVGAdminCsvController.prototype.resolvePreflightAction = function (summary) {
    if (!summary || summary.idProfile !== "string" || !this.isPrimaryKeyNumeric()) {
      return ""
    }
    var clear = window.confirm(
      "CSV IDs are strings but current table ID column is numeric.\n\nOK: clear table before import.\nCancel: convert existing numeric IDs to strings and append.",
    )
    return clear ? "clear" : "convert_to_string"
  }

  MapSVGAdminCsvController.prototype.runCsvPreflight = function (payload) {
    return this.server.post("collection/" + this.schemaName + "/import-csv/preflight", payload)
  }

  MapSVGAdminCsvController.prototype.importFromUrl = function () {
    var _this = this
    var url = _this._$fe("CsvUrl").val().trim()
    if (!url) {
      $.growl.error({ title: "", message: "Enter a CSV URL first" })
      return
    }
    if (!_this.validateRemoteCsvUrl(url)) return

    _this._setImportInProgress(true)
    _this.setImportProgress("Importing…")

    var useGeocode = _this._$fe("Geocode").is(":checked")
    var selectedId = _this._$fe("IdField").length ? _this._$fe("IdField").val() || "" : ""
    var preflightPayload = { csvUrl: url, idFieldName: selectedId }
    var convertLatlngToAddress =
      useGeocode && _this._$fe("OptLatlngToAddress").is(":checked") ? 1 : 0
    var convertAddressToLatLng =
      useGeocode && _this._$fe("OptAddressToLatlng").is(":checked") ? 1 : 0
    var paidGeocoding = useGeocode && _this._$fe("OptPaidGeocoding").is(":checked") ? 1 : 0
    _this
      .runCsvPreflight(preflightPayload)
      .then(function (pf) {
        var resolution = _this.resolvePreflightAction(pf.summary || {})
        return _this.server.post("collection/" + _this.schemaName + "/import-csv-url", {
          preflightToken: pf.preflightToken,
          preflightResolution: resolution,
          convertLatlngToAddress: convertLatlngToAddress,
          convertAddressToLatLng: convertAddressToLatLng,
          paidGeocoding: paidGeocoding,
        })
      })
      .done(function (body) {
        if (body.token) {
          saveImportJob(_this.schemaName, body.token, body.total || 0)
          _this.setImportProgress("Importing " + (body.total || 0) + " rows…")
          _this.driveImport(_this.schemaName, body.token, body.total || 0, 0, true)
        } else if (body.error) {
          _this._setImportInProgress(false)
          _this.setImportProgress("Error: " + body.error, "danger")
        } else {
          _this._setImportInProgress(false)
        }
      })
      .fail(function () {
        _this._setImportInProgress(false)
        _this.setImportProgress("Network error during import", "danger")
      })
  }

  // fetch() is used here — AppScript is an external Google URL, not a MapSVG endpoint.
  MapSVGAdminCsvController.prototype.loadSheets = function (url, onSuccess) {
    var _this = this
    if (!url) return
    _this._$fe("SheetsSpinner").show()
    _this._$fe("CheckLabel").hide()
    _this._$fe("SheetsError").hide().text("")

    fetch(url)
      .then(function (res) {
        return res.json()
      })
      .then(function (data) {
        _this._$fe("SheetsSpinner").hide()
        _this._$fe("CheckLabel").show()
        if (!data.sheets || typeof data.sheets !== "object") {
          _this
            ._$fe("SheetsError")
            .text("Unexpected response. Ensure AppScript is deployed as a Web App.")
            .show()
          return
        }
        _this.gsSheetData = data.sheets
        var sheetNames = Object.keys(data.sheets)
        var $sheetSel = _this._$fe("SheetName")
        var currentSheet = _this.getImportSetting("gsSheetName", "Sheet1")
        $sheetSel.empty()
        sheetNames.forEach(function (name) {
          $sheetSel.append(
            $("<option>")
              .val(name)
              .text(name)
              .prop("selected", name === currentSheet),
          )
        })
        _this.populateColumns($sheetSel.val())
        reinitSelect2($sheetSel)
        _this._$fe("AppScriptFields").show()
        _this.updateScroll()
        if (onSuccess) onSuccess()
      })
      .catch(function () {
        _this._$fe("SheetsSpinner").hide()
        _this._$fe("CheckLabel").show()
        _this
          ._$fe("SheetsError")
          .text(
            "Could not load sheets. Check the URL or ensure AppScript is deployed as a Web App with access: Anyone.",
          )
          .show()
      })
  }

  MapSVGAdminCsvController.prototype.populateColumns = function (sheetName) {
    var _this = this
    if (!_this.gsSheetData || !_this.gsSheetData[sheetName]) return
    var columns = _this.gsSheetData[sheetName]
    _this._renderIdFieldOptions(columns)
  }

  MapSVGAdminCsvController.prototype.connectAppScript = function () {
    var _this = this
    var appScriptUrl = _this._$fe("AppScriptUrl").val().trim()
    var setupKey = _this._$fe("SetupKey").val().trim()
    var idFieldName = _this._$fe("IdField").val() || ""
    var sheetName = _this._$fe("SheetName").val() || "Sheet1"

    if (!appScriptUrl || !setupKey) {
      $.growl.error({ title: "", message: "Enter both the AppScript URL and the setup key" })
      return
    }
    var $btn = _this._$fe("BtnGsConnect")
    $btn.prop("disabled", true)
    _this._$fe("ConnectStatus").text("Saving…")

    _this.saveSchemaFields(
      {
        gsSync: 1,
        gsSyncMode: "w",
        gsAppScriptUrl: appScriptUrl,
        gsIdFieldName: idFieldName,
        gsSheetName: sheetName,
      },
      _this._$fe("ConnectStatus"),
      null,
      function () {
        _this._$fe("ConnectStatus").text("Connecting…")
        _this.server
          .post("collection/" + _this.schemaName + "/setup-appscript", {
            setupKey: setupKey,
            appScriptUrl: appScriptUrl,
          })
          .done(function (body) {
            $btn.prop("disabled", false)
            if (body.error) {
              _this._$fe("ConnectStatus").text("")
              $.growl.error({ title: "", message: body.error })
              return
            }
            _this.setImportSetting("gsSecret", "set")
            _this._$fe("SetupKey").val("")
            _this._$fe("ConnectStatus").text("")
            _this._$fe("AppScriptUrlSheetNameIdField").prop("disabled", true)
            _this
              ._$fe("ConnectionStatus")
              .html(
                '<span class="badge bg-success me-2"><i class="bi bi-check-circle me-1"></i>Connected</span>' +
                  '<button type="button" class="btn btn-sm btn-outline-danger ms-2" id="mapsvg-btn-gs-reset">' +
                  '<i class="bi bi-x-circle me-1"></i>Reset connection</button>',
              )
            _this._$fe("SetupKeyRowConnectRows").hide()
            _this.view.find(_this.$id("mapsvg-btn-gs-reset")).on("click", function () {
              _this.resetAppScript()
            })
            $.growl.notice({ title: "", message: "AppScript connected successfully!" })
          })
          .fail(function () {
            $btn.prop("disabled", false)
            _this._$fe("ConnectStatus").text("")
            $.growl.error({ title: "", message: "Network error during connect" })
          })
      },
      function () {
        $btn.prop("disabled", false)
      },
    )
  }

  MapSVGAdminCsvController.prototype.resetAppScript = function () {
    var _this = this
    if (
      !confirm(
        "Reset the AppScript connection? This will also reset AppScript and generate a new setup key.",
      )
    )
      return
    var $resetBtn = _this._$fe("BtnGsReset")
    $resetBtn.prop("disabled", true)
    _this.server
      .post("collection/" + _this.schemaName + "/reset-appscript", {})
      .done(function (body) {
        if (body.error) {
          $resetBtn.prop("disabled", false)
          $.growl.error({ title: "", message: "Reset failed: " + body.error })
          return
        }
        _this.setImportSetting("gsSecret", "")
        _this.setImportSetting("gsAppScriptUrl", "")
        _this._$fe("AppScriptUrlSheetNameIdField").prop("disabled", false)
        _this
          ._$fe("ConnectionStatus")
          .html('<span class="badge bg-secondary me-2">Not connected</span>')
        _this._$fe("SetupKeyRowConnectRows").show()
        _this.updateScroll()
        $.growl.notice({
          title: "",
          message: "Connection reset. A new setup key has been generated in AppScript.",
        })
      })
      .fail(function () {
        $resetBtn.prop("disabled", false)
        $.growl.error({ title: "", message: "Network error during reset" })
      })
  }

  MapSVGAdminCsvController.prototype.uploadCsv = function () {
    var _this = this
    var fileInput = _this._$fe("CsvFile")[0]

    if (!fileInput || !fileInput.files[0]) {
      $.growl.error({ title: "", message: "Please choose a file" })
      return
    }

    var phpIni = window.mapsvgBackendParams && window.mapsvgBackendParams.phpIni
    if (phpIni) {
      var maxBytes = Math.min(
        parsePhpSize(phpIni.upload_max_filesize),
        parsePhpSize(phpIni.post_max_size),
      )
      if (fileInput.files[0].size > maxBytes) {
        $.growl.error({
          title: "",
          message:
            "File is too large (" +
            (fileInput.files[0].size / 1024 / 1024).toFixed(1) +
            " MB). " +
            "Your server allows up to " +
            phpIni.upload_max_filesize +
            " per upload.",
        })
        return
      }
    }

    _this._setImportInProgress(true)

    var useGeocoding = _this._$fe("Geocode").is(":checked")
    var convertLatlngToAddress = useGeocoding && _this._$fe("OptLatlngToAddress").is(":checked")
    var convertAddressToLatLng = useGeocoding && _this._$fe("OptAddressToLatlng").is(":checked")
    var paidGeocoding = useGeocoding && _this._$fe("OptPaidGeocoding").is(":checked")
    var regionsTableName =
      _this.mapsvg.regionsRepository && _this.mapsvg.regionsRepository.schema
        ? _this.mapsvg.regionsRepository.schema.name
        : ""

    var formData = new FormData()
    formData.append("csv", fileInput.files[0])
    formData.append("convertLatlngToAddress", convertLatlngToAddress ? "1" : "0")
    formData.append("convertAddressToLatLng", convertAddressToLatLng ? "1" : "0")
    formData.append("paidGeocoding", paidGeocoding ? "1" : "0")
    formData.append("regionsTableName", regionsTableName)

    var preflightForm = new FormData()
    preflightForm.append("csv", fileInput.files[0])
    preflightForm.append("idFieldName", _this._$fe("IdField").val() || "")

    _this
      .runCsvPreflight(preflightForm)
      .then(function (pf) {
        var resolution = _this.resolvePreflightAction(pf.summary || {})
        var startData = new FormData()
        startData.append("preflightToken", pf.preflightToken)
        startData.append("preflightResolution", resolution)
        startData.append("convertLatlngToAddress", convertLatlngToAddress ? "1" : "0")
        startData.append("convertAddressToLatLng", convertAddressToLatLng ? "1" : "0")
        startData.append("paidGeocoding", paidGeocoding ? "1" : "0")
        startData.append("regionsTableName", regionsTableName)
        return _this.server.post("collection/" + _this.schemaName + "/import-csv", startData)
      })
      .done(function (body) {
        if (body.error) {
          _this._setImportInProgress(false)
          $.growl.error({ title: "", message: body.error || "Upload failed" })
          return
        }
        var token = body.token,
          total = body.total || 0
        saveImportJob(_this.schemaName, token, total)
        $.growl.notice({ title: "", message: "File uploaded. Importing " + total + " rows…" })
        _this.driveImport(_this.schemaName, token, total, 0, true)
      })
      .fail(function (jqXHR) {
        _this._setImportInProgress(false)
        var msg = (jqXHR.responseJSON && jqXHR.responseJSON.error) || "Network error during import"
        console.error(
          "[CLIENT-027] HTTP error during CSV import — https://mapsvg.com/docs/errors#CLIENT-027",
        )
        $.growl.error({ title: "", message: msg })
      })
  }

  // ─── Event handler bindings ───────────────────────────────────────────────────

  MapSVGAdminCsvController.prototype.setEventHandlers = function () {
    var _this = this
    var fe = this.formElements
    var ns = ".mapsvgCsvForm"
    var $root = this.view

    $root.off(ns)

    // Source radio toggle (delegated — survives contentView redraw)
    $root.on("change" + ns, fe.GsImportSourceInputs, function () {
      var source = $(this).val()
      _this.setImportSetting("gsImportSource", source)
      var isRemote = source === "remote"
      _this._$fe("UploadSection").toggle(!isRemote)
      _this._$fe("RemoteSection").toggle(isRemote)
      _this._$fe("RemoteOptions").toggle(isRemote)

      // Load cached ID options for the selected source
      var $idSel = _this._$fe("IdField")
      if ($idSel.length && _this.csvIdOptions[source].length) {
        _this._renderIdFieldOptions(_this.csvIdOptions[source])
      } else if ($idSel.length) {
        _this._renderIdFieldOptions([])
      }

      // Switching to remote: restore summary + bottom blocks if URL was already verified (DB flag)
      if (isRemote) {
        var remoteDbValid = _this._isGsImportSourceValid()
        if (remoteDbValid) {
          _this._initImportSummary()
        } else {
          _this._$fe("CommonCheckedPanel").hide()
          _this._$fe("ValidatedBlocksActions").hide()
        }
      } else {
        // Switching to upload — reset transient flag
        _this._uploadValid = false
        _this._$fe("CheckedPanelValidatedBundle").hide()
      }
      var sourceState = _this.missingFieldsState[source]
      if (sourceState && sourceState.rows && sourceState.rows.length) {
        _this._renderMissingFields(sourceState.rows, source)
      } else {
        _this._resetMissingFieldsUi()
      }
      _this._syncSaveSettingsWrap()
      _this._syncGeocodingVisibility()
      _this.updateScroll()
    })

    $root.on("change" + ns, fe.CsvFile, function () {
      _this.csvIdOptions["upload"] = []
      _this.csvIdFieldSelection.upload = null
      _this.missingFieldsState["upload"] = null
      _this._activeMissingFieldsSource = null
      _this._uploadValid = false
      _this._$fe("CheckedPanelValidatedBundle").hide()
      _this._syncGeocodingVisibility()
      _this.updateScroll()
    })

    $root.on("input" + ns + " paste" + ns + " change" + ns, fe.CsvUrl, function () {
      _this.csvIdOptions["remote"] = []
      _this.csvIdFieldSelection.remote = null
      _this.missingFieldsState["remote"] = null
      _this._activeMissingFieldsSource = null
      _this.setImportSetting("gsImportSourceValid", 0)
      _this._$fe("CheckedPanelValidatedBundle").hide()
      _this._syncGeocodingVisibility()
      _this.updateScroll()
    })

    $root.on("click" + ns, fe.BtnGsCheckCsv, function () {
      _this.checkCsvUrl(_this._$fe("CsvUrl").val().trim())
    })
    $root.on("click" + ns, fe.BtnCsvCheck, function () {
      _this.checkUploadedCsv()
    })
    $root.on("click" + ns, fe.BtnGsSave, function () {
      _this.saveGsSettings()
    })

    // Save handler for the sync panel (ID field + missing fields)
    $root.on("click" + ns, fe.BtnGsSyncSave, function () {
      var source = _this._$fe("GsImportSourceInputs").filter(":checked").val() || "upload"
      if (_this._activeMissingFieldsSource && _this._activeMissingFieldsSource !== source) {
        $.growl.error({
          title: "",
          message: "Please click Sync fields for the selected source before continuing.",
        })
        return
      }
      var $idField = _this._$fe("IdField")
      var idFieldName = $idField.length ? $idField.val() || "" : _this.getSelectedIdFieldName(source)
      if (source === "upload") {
        _this.csvIdFieldSelection.upload = idFieldName
      } else {
        _this.csvIdFieldSelection.remote = idFieldName
        _this.setImportSetting("gsIdFieldName", idFieldName)
      }

      // Collect checked (new) fields and unchecked (skipped) field names
      var newFields = []
      var skipFields = []
      _this
        ._$fe("MissingFieldsTable")
        .find("tbody tr")
        .each(function () {
          var $row = $(this)
          var colName = $row.find("td:nth-child(2)").text().trim()
          if (!$row.find(".gs-field-add-check").is(":checked")) {
            skipFields.push(colName)
            return
          }
          var label = $row.find("input[data-col]").val() || colName
          var type = $row.find("select").val() || "text"
          var needsOpts = OPTIONS_TYPES.indexOf(type) !== -1
          var $preview = $row.find(".gs-opts-preview")
          var opts = needsOpts ? $preview.data("options") || [] : []
          newFields.push({
            name: colName,
            label: label,
            type: type,
            multiselect: needsOpts ? !!$preview.data("multiselect") : false,
            options: opts.map(function (v) {
              return { value: v, label: v }
            }),
          })
        })

      var updatedFields = (_this.schema.fields || []).concat(newFields)
      var hasNewFields = newFields.length > 0
      var hasSkipFields = skipFields.length > 0
      var postFieldCount = updatedFields.filter(function (field) {
        return field.type === "post"
      }).length
      if (postFieldCount > 1) {
        $.growl.error({
          title: "",
          message: "Only one field of type 'Post' is allowed in a schema.",
        })
        return
      }

      if (source === "remote") {
        _this.setImportSetting("gsImportSourceValid", 1)
      } else {
        _this._uploadValid = true
      }

      function showContinueSuccess() {
        _this._$fe("CommonCheckedEdit").hide()
        _this._$fe("CommonCheckedSummary").show()
        _this._$fe("SummaryIdField").text(_this.formatIdFieldSummary(idFieldName))

        if (skipFields.length) {
          _this._$fe("SummarySkippedList").text(skipFields.join(", "))
          _this._$fe("SummaryMissing").show()
        } else {
          _this._$fe("SummaryMissing").hide()
        }

        _this._$fe("ValidatedBlocksActions").show()
        _this._syncSaveSettingsWrap()
        _this._syncGeocodingVisibility()
        _this._$fe("SyncStatus").text("Done")
        setTimeout(function () {
          _this._$fe("SyncStatus").text("")
        }, 2000)
        _this.updateScroll()
        if (newFields.length) {
          $.growl.notice({ title: "", message: newFields.length + " field(s) added to schema" })
        }
      }

      // Upload: only new field definitions are saved; ID column & skip list stay transient (no schema.update)
      if (source === "upload" && !hasNewFields) {
        showContinueSuccess()
        return
      }

      _this._$fe("SyncStatus").text("Saving…")

      var saveData = {}
      if (source === "remote") {
        saveData = {
          fields: updatedFields,
          gsIdFieldName: idFieldName,
          gsImportSkipFields: hasSkipFields ? JSON.stringify(skipFields) : null,
          gsImportSourceValid: 1,
        }
      } else {
        // Upload: new fields only — never gsIdFieldName or gsImportSkipFields
        saveData.fields = updatedFields
      }

      function saveFinal(dataToSave) {
        _this.saveSchemaFields(
          dataToSave,
          _this._$fe("SyncStatus"),
          null,
          function () {
            _this.missingFieldsState[source] = null
            _this._activeMissingFieldsSource = null
            showContinueSuccess()
          },
          function () {
            _this._$fe("SyncStatus").text("")
            $.growl.error({ title: "", message: "Failed to save fields" })
          },
        )
      }

      if (source === "upload" && hasNewFields) {
        var needsFullScan = newFields.some(function (field) {
          return OPTIONS_SCAN_TYPES.indexOf(field.type) !== -1
        })
        if (needsFullScan) {
          var state = _this.missingFieldsState.upload
          var columnIndexes = {}
          newFields.forEach(function (field) {
            if (OPTIONS_SCAN_TYPES.indexOf(field.type) === -1) return
            var rowState = (state && state.rows ? state.rows : []).find(function (row) {
              return row.name === field.name
            })
            if (!rowState || rowState.columnIndex === undefined || rowState.columnIndex < 0) return
            columnIndexes[field.name] = rowState.columnIndex
          })
          _this._extractUniqueOptionsFromUploadCsv(
            columnIndexes,
            function (scannedOptions) {
              saveData.fields = saveData.fields.map(function (field) {
                if (OPTIONS_SCAN_TYPES.indexOf(field.type) === -1) return field
                var scanned = scannedOptions[field.name]
                if (!scanned) return field
                field.multiselect = !!scanned.multiselect
                field.options = (scanned.options || []).map(function (value) {
                  return { value: value, label: value }
                })
                return field
              })
              saveFinal(saveData)
            },
            function (message) {
              _this._$fe("SyncStatus").text("")
              $.growl.error({ title: "", message: message })
            },
          )
          return
        }
      }

      saveFinal(saveData)
    })

    $root.on("click" + ns, fe.BtnGsSyncCancel, function () {
      var source = _this._$fe("GsImportSourceInputs").filter(":checked").val() || "upload"
      _this.csvIdOptions[source] = []
      _this.missingFieldsState[source] = null
      _this._activeMissingFieldsSource = null
      if (source === "remote") {
        _this.setImportSetting("gsImportSourceValid", _this._preSyncValid || 0)
      } else {
        _this._uploadValid = _this._preSyncUploadValid || false
        _this.csvIdFieldSelection.upload =
          _this._preSyncIdField === undefined ? null : _this._preSyncIdField
      }

      _this._$fe("CommonCheckedEdit").hide()
      _this._$fe("CommonCheckedSummary").show()
      _this._$fe("SummaryIdField").text(_this.formatIdFieldSummary(_this._preSyncIdField))

      var skipFields = _this._preSyncSkipFields ? JSON.parse(_this._preSyncSkipFields) : []
      if (skipFields.length) {
        _this._$fe("SummarySkippedList").text(skipFields.join(", "))
        _this._$fe("SummaryMissing").show()
      } else {
        _this._$fe("SummaryMissing").hide()
      }

      var isValid =
        source === "remote" ? _this.getImportSetting("gsImportSourceValid", 0) : _this._uploadValid
      if (isValid) {
        _this._$fe("ValidatedBlocksActions").show()
      } else {
        _this._$fe("ValidatedBlocksActions").hide()
      }
      _this._syncSaveSettingsWrap()
      _this.updateScroll()
    })

    $root.on("click" + ns, fe.BtnGsSyncEdit, function () {
      _this._$fe("CommonCheckedSummary").hide()
      _this._$fe("ValidatedBlocksActions").hide()

      var source = _this._$fe("GsImportSourceInputs").filter(":checked").val() || "upload"
      if (source === "remote") {
        _this.checkCsvUrl(_this._$fe("CsvUrl").val().trim())
      } else {
        _this.checkUploadedCsv()
      }
    })
    $root.on("click" + ns, fe.BtnGsImport, function () {
      _this.importNow()
    })
    $root.on("change" + ns, fe.SheetName, function () {
      _this.populateColumns($(this).val())
    })
    $root.on("click" + ns, fe.BtnGsCheckUrl, function () {
      var url = _this._$fe("AppScriptUrl").val().trim()
      if (url && !_this.getImportSetting("gsSecret", "")) _this.loadSheets(url)
    })
    $root.on("click" + ns, fe.BtnGsConnect, function () {
      _this.connectAppScript()
    })
    $root.on("click" + ns, fe.BtnGsReset, function () {
      _this.resetAppScript()
    })

    $root.on("change" + ns, fe.OptPaidGeocoding, function () {
      var paid = $(this).is(":checked")
      _this._$fe("GeocodingQuotaFree").toggle(!paid)
      _this._$fe("GeocodingQuotaPaid").toggle(paid)
    })

    $root.on("click" + ns, '[data-csv-role="show-logs"]', function (e) {
      e.preventDefault()
      _this.showLogsModal()
    })
  }

  // ─── Background import driver ─────────────────────────────────────────────────

  /**
   * Drives background CSV import by calling importCsvProcess() back-to-back
   * until the server reports status === 'complete' or 'failed'.
   */
  MapSVGAdminCsvController.prototype.driveImport = function (
    schemaName,
    token,
    total,
    processed,
    isFirstCall,
  ) {
    var _this = this
    setTimeout(
      function () {
        _this.database
          .importCsvProcess(token)
          .done(function (body) {
            var newProcessed = body.processed || 0
            var percent = total > 0 ? Math.round((newProcessed / total) * 100) : 0

            if (body.status === "complete") {
              clearImportJob(schemaName)
              _this._setImportInProgress(false)
              if (body.geocoding_queued) {
                _this.setImportProgress("Geocoding in progress…")
                _this.pollGeocodingQueue(schemaName)
              } else if (body.importedAt) {
                _this.setImportSetting("gsImportFinishedAt", body.importedAt)
                _this.updateImportStatus(body.importedAt, body.error_count || 0)
              }
              _this.database.find()
              return
            }

            if (body.status === "failed") {
              clearImportJob(schemaName)
              _this._setImportInProgress(false)
              _this.setImportProgress("Import failed: " + (body.error || "unknown error"), "danger")
              return
            }

            if (body.error && body.error.indexOf("not found") !== -1) {
              clearImportJob(schemaName)
              _this._setImportInProgress(false)
              _this.setImportProgress("Import job expired. Please re-upload the file.", "danger")
              return
            }

            _this.setImportProgress(
              "Importing… " + percent + "% (" + newProcessed + " / " + total + ")",
            )
            var perpage = _this.database.query.perpage || 30
            var belowPage = _this.database.objects.length < perpage
            // objects.length === 0 means nothing loaded yet — always try once;
            // otherwise only refresh if the server indicated more pages exist.
            var canLoadMore = _this.database.objects.length === 0 || _this.database.hasMore
            if (belowPage && canLoadMore) {
              _this.database.find()
            }
            _this.driveImport(schemaName, token, total, newProcessed, false)
          })
          .fail(function () {
            setTimeout(function () {
              _this.driveImport(schemaName, token, total, processed, false)
            }, 5000)
          })
      },
      isFirstCall ? 500 : 0,
    )
  }

  // ─── Geocoding queue poller ───────────────────────────────────────────────────

  /**
   * Polls /geocoding/queue with an adaptive interval until pending reaches 0.
   */
  MapSVGAdminCsvController.prototype.pollGeocodingQueue = function (schemaName) {
    var _this = this
    var poll = function () {
      _this.server
        .get("geocoding/queue")
        .done(function (body) {
          var queue = body.queue && !Array.isArray(body.queue) ? body.queue : {}
          var pending = queue[schemaName] ? queue[schemaName].pending : 0
          if (pending === 0) {
            _this.loadImportStatus()
            _this.database.find()
          } else {
            _this.setImportProgress("Geocoding: " + pending + " rows remaining…")
            setTimeout(poll, geocodingPollInterval(pending))
          }
        })
        .fail(function () {
          setTimeout(poll, 10000)
        })
    }
    setTimeout(poll, 2000)
  }

  // ─── Import Logs ──────────────────────────────────────────────────────────────

  /**
   * Initialise the Bootstrap modal for import logs.
   * The modal HTML must live in the template (added in html task).
   */
  MapSVGAdminCsvController.prototype.initLogsModal = function () {
    var $modal = this._$fe("LogsModal")
    if ($modal.length) {
      this.logsModalBody = $modal.find("[data-csv-role='logs-modal-body']")
      $modal.appendTo("body")
      this.logsModal = new bootstrap.Modal($modal[0], { backdrop: true })
    }
  }

  /**
   * Show a transient progress/status message inside the import status bar.
   * Does NOT touch the "Last import" state — that is only set by updateImportStatus().
   * @param {string} text
   * @param {string} [variant]  Bootstrap text color variant, e.g. 'danger', 'warning'. Default 'muted'.
   */
  MapSVGAdminCsvController.prototype.setImportProgress = function (text, variant) {
    var $bar = this._$fe("ImportStatusBar")
    if (!$bar.length) return
    var cls = "text-" + (variant || "muted")
    $bar.html('<small class="' + cls + '">' + text + "</small>")
  }

  /**
   * Render the import status bar from the current schema's gsImportFinishedAt value.
   * Called on viewLoaded and after each completed import.
   */
  MapSVGAdminCsvController.prototype.loadImportStatus = function () {
    var importedAt = this.getImportSetting("gsImportFinishedAt", null)
    this.updateImportStatus(importedAt, null)
  }

  /**
   * Update the status bar text.
   * @param {string|null} importedAt  MySQL datetime string
   * @param {number|null} errorCount  null = unknown (don't show error badge), 0+ = known
   */
  MapSVGAdminCsvController.prototype.updateImportStatus = function (importedAt, errorCount) {
    var $bar = this._$fe("ImportStatusBar")
    if (!$bar.length) return

    if (!importedAt) {
      $bar.html('No import yet. <a href="#" data-csv-role="show-logs">See logs</a>')
      return
    }

    var d = new Date(importedAt.replace(" ", "T"))
    var formatted =
      d.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" }) +
      " " +
      d.toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" })

    var badge
    if (errorCount === null) {
      badge = ""
    } else if (errorCount === 0) {
      badge = '<span class="badge bg-success ms-1">success</span>'
    } else {
      badge =
        '<span class="badge bg-danger ms-1">' +
        errorCount +
        " error" +
        (errorCount !== 1 ? "s" : "") +
        "</span>"
    }

    $bar.html(
      "Last import: " +
        formatted +
        " " +
        badge +
        ' <a href="#" data-csv-role="show-logs" class="ms-2">See logs</a>',
    )
  }

  /**
   * Fetch logs for this schema and show them in the logs modal.
   */
  MapSVGAdminCsvController.prototype.showLogsModal = function () {
    if (!this.logsModal) return
    var _this = this
    var $body = this.logsModalBody
    $body.html('<p class="text-muted">Loading…</p>')
    this.logsModal.show()

    this.server
      .get("import-logs", { schemaName: this.schemaName })
      .done(function (data) {
        var items = data.items || []
        if (!items.length) {
          $body.html('<p class="text-muted">No log entries.</p>')
          return
        }
        var rows = items.map(function (log) {
          var badgeClass =
            log.type === "error" ? "danger" : log.type === "warning" ? "warning" : "secondary"
          var counter =
            log.counter > 1
              ? ' <span class="badge bg-light text-dark">×' + log.counter + "</span>"
              : ""
          return (
            "<tr>" +
            '<td><span class="badge bg-' +
            badgeClass +
            '">' +
            log.type +
            "</span></td>" +
            "<td>" +
            $("<span>").text(log.message).html() +
            counter +
            "</td>" +
            "<td>" +
            (log.createdAt || "") +
            "</td>" +
            "</tr>"
          )
        })
        $body.html(
          '<table class="table table-sm table-bordered">' +
            "<thead><tr><th>Type</th><th>Message</th><th>Date</th></tr></thead>" +
            "<tbody>" +
            rows.join("") +
            "</tbody>" +
            "</table>",
        )
      })
      .fail(function () {
        $body.html('<p class="text-danger">Failed to load logs.</p>')
      })
  }
})(jQuery, window, window.MapSVG)
