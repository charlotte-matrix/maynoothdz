// ── Script properties (persisted across executions) ──────────────────────────
// SETUP_KEY       – random UUID generated once; shown to user during setup
// SECRET          – 32-char random string exchanged with MapSVG during setup
// INITIALIZED     – "true" once setup is complete (prevents re-setup)
// MAPSVG_SYNC_URL – full sync endpoint URL, set in the Setup sidebar
// SHEET_NAME      – name of the sheet tab holding your data (default: "Sheet1")
// ID_COLUMN       – column header used to match rows; must match MapSVG's
//                   "Google Sheet ID column" setting (default: "id")

// ── Auto-run on spreadsheet open ─────────────────────────────────────────────
function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu("MapSVG")
    .addItem("Setup", "openSetupSidebar")
    .addToUi();

  var props = PropertiesService.getScriptProperties();
  if (!props.getProperty("INITIALIZED")) {
    // Not yet connected — open the sidebar automatically so the user sees the setup key
    openSetupSidebar();
  } else {
    showModal("MapSVG Sync Active", [
      { label: "Sheet",     value: getSheetName() },
      { label: "ID column", value: getIdColumn()  },
    ], "To change settings open <strong>MapSVG &rarr; Setup</strong> from the top menu.");
  }
}

// ── Show a Bootstrap-styled modal dialog ──────────────────────────────────────
// rows: array of {label, value} shown as a two-column table.
// note: optional paragraph below the table (HTML allowed, rendered unescaped).
function showModal(title, rows, note) {
  var template  = HtmlService.createTemplateFromFile("Modal");
  template.rows = rows || [];
  template.note = note || "";

  var html = template.evaluate()
    .setWidth(380)
    .setHeight(rows && rows.length ? 160 + rows.length * 36 : 160);

  SpreadsheetApp.getUi().showModalDialog(html, title);
}

// ── Open the Setup sidebar ────────────────────────────────────────────────────
function openSetupSidebar() {
  var html = HtmlService.createHtmlOutputFromFile("Sidebar")
    .setTitle("MapSVG Setup");
  SpreadsheetApp.getUi().showSidebar(html);
}

// ── Called by the sidebar to load current settings ───────────────────────────
function getSetupData() {
  var props = PropertiesService.getScriptProperties();
  var key   = props.getProperty("SETUP_KEY");
  if (!key) {
    key = Utilities.getUuid();
    props.setProperty("SETUP_KEY", key);
  }
  return {
    initialized: props.getProperty("INITIALIZED") === "true",
    setupKey:    key,
    sheetName:   getSheetName(),
    idColumn:    getIdColumn(),
    syncUrl:     props.getProperty("MAPSVG_SYNC_URL") || "",
  };
}

// ── Called by the sidebar Reset button ───────────────────────────────────────
function resetSetupFromSidebar() {
  PropertiesService.getScriptProperties().deleteAllProperties();
  return { ok: true };
}

// ── Read helpers (with safe defaults) ────────────────────────────────────────
function getSheetName() {
  return PropertiesService.getScriptProperties().getProperty("SHEET_NAME") || "Sheet1";
}
function getIdColumn() {
  return PropertiesService.getScriptProperties().getProperty("ID_COLUMN") || "id";
}

// ── GET: return sheet names and column headers (no auth — used by MapSVG UI) ─
function doGet(e) {
  var ss     = SpreadsheetApp.getActiveSpreadsheet();
  var sheets = {};
  ss.getSheets().forEach(function (sheet) {
    var name    = sheet.getName();
    var lastCol = sheet.getLastColumn();
    sheets[name] = lastCol > 0
      ? sheet.getRange(1, 1, 1, lastCol).getValues()[0].filter(function (h) { return h !== ""; })
      : [];
  });
  return ContentService
    .createTextOutput(JSON.stringify({ sheets: sheets }))
    .setMimeType(ContentService.MimeType.JSON);
}

// ── Main HTTP entry point (receives pushes from MapSVG) ──────────────────────
function doPost(e) {
  try {
    var props = PropertiesService.getScriptProperties();
    var body  = JSON.parse(e.postData.contents);

    // ── Setup handshake (INITIALIZED not yet set) ─────────────────────────────
    if (!props.getProperty("INITIALIZED")) {
      var storedKey = props.getProperty("SETUP_KEY");
      if (!storedKey || body.setupKey !== storedKey) {
        return jsonError("Bad setup key");
      }
      if (!body.secret || body.secret.length < 16) {
        return jsonError("Secret too short");
      }
      var newProps = { SECRET: body.secret, INITIALIZED: "true" };
      if (body.syncUrl)   newProps.MAPSVG_SYNC_URL = body.syncUrl;
      if (body.sheetName) newProps.SHEET_NAME       = body.sheetName;
      if (body.idColumn)  newProps.ID_COLUMN        = body.idColumn;
      props.setProperties(newProps);
      return jsonOk({});
    }

    // ── Ongoing: push from MapSVG → sheet ─────────────────────────────────────
    var secret    = props.getProperty("SECRET");
    var timestamp = body.timestamp || 0;

    // Date.now() is ms → convert to seconds; body.timestamp is already seconds (PHP time())
    if (Math.abs(Date.now() / 1000 - timestamp) > 30) {
      return jsonError("Stale request");
    }

    var signature = body.signature;
    var signable  = timestamp + ":" + (body.action || "upsert");
    if (!verifyHmac(signable, secret, signature)) {
      return jsonError("Bad signature");
    }

    var action   = body.action || "upsert";
    var row      = body.row || {};
    var idColumn = getIdColumn();

    if (action === "reset") {
      // MapSVG is disconnecting — wipe all properties and generate a fresh setup key
      // so the user can reconnect later without re-deploying.
      props.deleteAllProperties();
      var newKey = Utilities.getUuid();
      props.setProperty("SETUP_KEY", newKey);
      return jsonOk({ setupKey: newKey });
    }

    if (action === "delete") {
      deleteRowById(row[idColumn]);
    } else {
      upsertRowInSheet(row);
    }

    return jsonOk({});
  } catch (err) {
    return jsonError(err.message);
  }
}

// ── Installable edit trigger: push changed row to MapSVG ─────────────────────
// Install via: Extensions → Apps Script → Triggers → Add trigger →
//   onEditInstallable → From spreadsheet → On edit
//
// ID write-back: when MapSVG creates a new record it assigns an auto-increment
// integer ID and returns it. AppScript writes this back into the ID_COLUMN cell
// so subsequent edits to that row will be matched as updates, not creates.
//
// Circular-trigger guard: writing the ID back fires onEditInstallable again.
// LockService.tryLock(0) prevents a second processing pass — the write-back
// call immediately returns because the lock is still held from the outer call.
function onEditInstallable(e) {
  var lock = LockService.getScriptLock();
  if (!lock.tryLock(0)) return; // ID write-back in progress — skip

  try {
    var props = PropertiesService.getScriptProperties();
    if (!props.getProperty("INITIALIZED")) return;

    var syncUrl = props.getProperty("MAPSVG_SYNC_URL");
    if (!syncUrl) return;

    var secret    = props.getProperty("SECRET");
    var sheetName = getSheetName();
    var idColumn  = getIdColumn();
    var sheet     = e.source.getActiveSheet();
    if (sheet.getName() !== sheetName) return;

    var rowIndex = e.range.getRow();
    if (rowIndex === 1) return; // Header row — skip

    var headers    = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
    var idColIndex = headers.indexOf(idColumn); // -1 if column not found
    var row        = getRowData(sheet, rowIndex, headers);

    // Remember whether this row already had an ID before sending it to MapSVG.
    // Auto-increment: idColumn is empty → MapSVG will create + return the new ID.
    // Text/user-set: idColumn is already filled → MapSVG will update, no write-back needed.
    var currentId   = row[idColumn];
    var hadIdBefore = currentId !== undefined && currentId !== null && currentId !== "";

    var timestamp = Math.floor(Date.now() / 1000);
    var action    = "upsert";
    var signature = computeHmacHex(timestamp + ":" + action, secret);

    var response = UrlFetchApp.fetch(syncUrl, {
      method:             "post",
      contentType:        "application/json",
      headers:            { "X-MapSVG-Signature": signature },
      payload:            JSON.stringify({ timestamp: timestamp, action: action, row: row }),
      muteHttpExceptions: true,
    });

    // For new rows (no ID yet): read the MapSVG-assigned ID from the response
    // and write it back into the ID column of this row.
    // The lock is still held here, so the resulting cell edit will not be
    // processed as a new sync push.
    if (!hadIdBefore && idColIndex >= 0) {
      try {
        var result     = JSON.parse(response.getContentText());
        var assignedId = extractIdFromResponse(result);
        if (assignedId !== null && assignedId !== undefined && assignedId !== "") {
          sheet.getRange(rowIndex, idColIndex + 1).setValue(assignedId);
        }
      } catch (parseErr) {
        // Ignore malformed response
      }
    }
  } catch (err) {
    // Silently ignore network errors so the user's edit is not blocked
  } finally {
    lock.releaseLock();
  }
}

// ── Helper: extract the ID from MapSVG's response ────────────────────────────
// MapSVG returns {<objectNameSingular>: {id: ..., fields...}}
// e.g. {"object": {"id": 42, "name": "..."}} or {"region": {"id": "US-TX", ...}}
// We find the first top-level object value that has an "id" field.
function extractIdFromResponse(result) {
  if (!result || result.error) return null;
  for (var key in result) {
    if (result.hasOwnProperty(key)) {
      var val = result[key];
      if (val && typeof val === "object" && val.id !== undefined) {
        return val.id;
      }
    }
  }
  return null;
}

// ── Helper: read a row as a key→value object ──────────────────────────────────
// Pass pre-fetched headers to avoid redundant reads when called from a trigger.
function getRowData(sheet, rowIndex, headers) {
  if (!headers) {
    headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  }
  var values = sheet.getRange(rowIndex, 1, 1, headers.length).getValues()[0];
  var obj    = {};
  headers.forEach(function (h, i) {
    if (h) obj[h] = values[i];
  });
  return obj;
}

// ── Helper: find a row index by ID column value ───────────────────────────────
// Works for both numeric (auto-increment) and text IDs.
function findRowIndexById(idValue) {
  if (idValue === undefined || idValue === null || idValue === "") return -1;
  var sheet   = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(getSheetName());
  var headers = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  var idCol   = headers.indexOf(getIdColumn());
  if (idCol === -1) return -1;

  var data = sheet.getDataRange().getValues();
  for (var i = 1; i < data.length; i++) {
    // Compare as strings so numeric IDs ("42") match number cells (42)
    if (String(data[i][idCol]) === String(idValue)) {
      return i + 1; // 1-based sheet row index
    }
  }
  return -1;
}

// ── Helper: upsert a row in the sheet ────────────────────────────────────────
// Finds an existing row by ID_COLUMN value and updates it; appends if not found.
// The ID can be auto-increment (numeric, may be empty for new rows) or text (user-set).
function upsertRowInSheet(rowObj) {
  var idColumn = getIdColumn();
  var sheet    = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(getSheetName());
  var headers  = sheet.getRange(1, 1, 1, sheet.getLastColumn()).getValues()[0];
  var values   = headers.map(function (h) {
    return h ? (rowObj[h] !== undefined ? rowObj[h] : "") : "";
  });

  var idValue  = rowObj[idColumn];
  // Only try to match an existing row if the ID is non-empty
  var rowIndex = (idValue !== undefined && idValue !== null && idValue !== "")
    ? findRowIndexById(idValue)
    : -1;

  if (rowIndex > 0) {
    sheet.getRange(rowIndex, 1, 1, values.length).setValues([values]);
  } else {
    sheet.appendRow(values);
  }
}

// ── Helper: delete a row by ID ────────────────────────────────────────────────
function deleteRowById(idValue) {
  var rowIndex = findRowIndexById(idValue);
  if (rowIndex > 0) {
    var sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(getSheetName());
    sheet.deleteRow(rowIndex);
  }
}

// ── HMAC helpers ──────────────────────────────────────────────────────────────
function computeHmacHex(message, secret) {
  var keyBytes     = Utilities.newBlob(secret).getBytes();
  var messageBytes = Utilities.newBlob(message).getBytes();
  var sigBytes     = Utilities.computeHmacSha256Signature(messageBytes, keyBytes);
  return sigBytes.map(function (b) {
    return ("0" + (b & 0xff).toString(16)).slice(-2);
  }).join("");
}

function verifyHmac(message, secret, expectedHex) {
  if (!expectedHex) return false;
  var computed = computeHmacHex(message, secret);
  // Constant-time comparison (same length check first)
  if (computed.length !== expectedHex.length) return false;
  var result = 0;
  for (var i = 0; i < computed.length; i++) {
    result |= computed.charCodeAt(i) ^ expectedHex.charCodeAt(i);
  }
  return result === 0;
}

// ── JSON response helpers ─────────────────────────────────────────────────────
function jsonOk(extra) {
  return ContentService
    .createTextOutput(JSON.stringify(Object.assign({ ok: true }, extra || {})))
    .setMimeType(ContentService.MimeType.JSON);
}

function jsonError(message) {
  return ContentService
    .createTextOutput(JSON.stringify({ error: message }))
    .setMimeType(ContentService.MimeType.JSON);
}
