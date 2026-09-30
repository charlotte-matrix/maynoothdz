const SHEETS_API_BASE = "https://sheets.googleapis.com/v4/spreadsheets"

/**
 * Fetch the first row (column headers) and up to 5 data rows from a
 * public Google Sheet using an API key.
 *
 * This module is intended to be dynamically imported in the admin UI only
 * when the user clicks "Preview Sheet", keeping it out of the main bundle.
 */
export async function previewGoogleSheet(sheetId, range, apiKey) {
  if (!sheetId) {
    return { headers: [], sampleRows: [], error: "Spreadsheet ID is required." }
  }
  if (!apiKey) {
    return { headers: [], sampleRows: [], error: "API Key is required for sheet preview." }
  }

  const safeRange = range || "Sheet1"
  const url = `${SHEETS_API_BASE}/${encodeURIComponent(sheetId)}/values/${encodeURIComponent(safeRange)}?key=${encodeURIComponent(apiKey)}`

  let response
  try {
    response = await fetch(url)
  } catch (err) {
    return { headers: [], sampleRows: [], error: "Network error while fetching sheet." }
  }

  if (!response.ok) {
    let message = `HTTP ${response.status}`
    try {
      const body = await response.json()
      if (body?.error?.message) message = body.error.message
    } catch {
      // ignore parse error
    }
    return { headers: [], sampleRows: [], error: `Google Sheets API error: ${message}` }
  }

  const body = await response.json()
  const values = body.values ?? []

  if (values.length === 0) {
    return { headers: [], sampleRows: [], error: "The sheet appears to be empty." }
  }

  const [headerRow, ...dataRows] = values
  return {
    headers: headerRow,
    sampleRows: dataRows.slice(0, 5),
  }
}
