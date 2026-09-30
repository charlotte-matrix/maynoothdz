/**
 * Validates Google Sheets remote URLs for MapSVG CSV import.
 * Google web-view / pubhtml links return HTML+JS that can redirect the page;
 * MapSVG needs a raw CSV stream (`output=csv` or `format=csv`).
 */
;(function (root) {
  function isGoogleSheetsSpreadsheetUrl(url) {
    return /docs\.google\.com\/spreadsheets\//i.test(String(url || ""))
  }

  /**
   * True when the URL is a Google Sheets CSV export / published CSV.
   * Examples:
   *   .../pub?gid=0&single=true&output=csv
   *   .../export?format=csv&gid=0
   */
  function isGoogleSheetsCsvExportUrl(url) {
    var u = String(url || "")
    return /[?&]output=csv(?:&|#|$)/i.test(u) || /[?&]format=csv(?:&|#|$)/i.test(u)
  }

  /**
   * @returns {string|null} User-facing error, or null if URL is OK / not a Sheets link.
   */
  function getGoogleSheetsCsvUrlError(url) {
    if (!url || !isGoogleSheetsSpreadsheetUrl(url)) return null
    if (isGoogleSheetsCsvExportUrl(url)) return null
    return (
      "This Google Sheets link is a web view, not a CSV export. " +
      "Use File → Share → Publish to web → CSV (the URL must contain output=csv or format=csv), e.g. " +
      "…/pub?gid=…&single=true&output=csv"
    )
  }

  var api = {
    isGoogleSheetsSpreadsheetUrl: isGoogleSheetsSpreadsheetUrl,
    isGoogleSheetsCsvExportUrl: isGoogleSheetsCsvExportUrl,
    getGoogleSheetsCsvUrlError: getGoogleSheetsCsvUrlError,
  }

  root.MapSVG = root.MapSVG || {}
  root.MapSVG.isGoogleSheetsSpreadsheetUrl = isGoogleSheetsSpreadsheetUrl
  root.MapSVG.isGoogleSheetsCsvExportUrl = isGoogleSheetsCsvExportUrl
  root.MapSVG.getGoogleSheetsCsvUrlError = getGoogleSheetsCsvUrlError

  if (typeof module === "object" && module.exports) {
    module.exports = api
  }
})(typeof globalThis !== "undefined" ? globalThis : this)
