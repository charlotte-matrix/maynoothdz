# MapSVG AppScript Template

Google Apps Script code for the user's Google Sheet to enable bi-directional sync with MapSVG.

## Files

| File | Purpose |
|---|---|
| `Code.gs` | All server-side logic — triggers, HTTP handler, HMAC, sheet helpers |
| `Sidebar.html` | Setup sidebar UI (opened via MapSVG menu or automatically on first open) |
| `Modal.html` | Template for Bootstrap-styled modal dialogs (used by `showModal()` in Code.gs) |
| `appsscript.json` | Manifest — declares required OAuth scopes so `showSidebar` works without errors |

---

## Security model

All ongoing requests are authenticated with **HMAC-SHA256** keyed on a shared `SECRET`.  
The signable string is `"timestamp:action"` (not the full payload) to avoid JSON
key-ordering discrepancies between PHP and JavaScript.

Every request must include a Unix timestamp (seconds) within **30 seconds** of the
receiver's clock to prevent replay attacks.

### Signature placement

| Direction | Where the signature goes |
|---|---|
| MapSVG → AppScript | `signature` field inside the JSON body (`doPost` cannot read custom headers) |
| AppScript → MapSVG | `X-MapSVG-Signature` HTTP header (via `UrlFetchApp`, read in PHP as `x_mapsvg_signature`) |

---

## Script properties

All configuration is stored in Apps Script **Script Properties** (not hardcoded):

| Property | Set by | Description |
|---|---|---|
| `SETUP_KEY` | Auto-generated on first sidebar open | One-time key used during the MapSVG handshake |
| `SECRET` | MapSVG (sent during Connect) | Shared HMAC secret; stored server-side only |
| `INITIALIZED` | Set to `"true"` after Connect | Prevents re-running the setup handshake |
| `MAPSVG_SYNC_URL` | User (via sidebar) | Full `/sync` endpoint URL |
| `SHEET_NAME` | User (via sidebar) | Sheet tab name holding the data (default: `Sheet1`) |
| `ID_COLUMN` | User (via sidebar) | Column header used to match rows (default: `id`) |

---

## Setup instructions

1. **Make a copy** of the MapSVG Google Sheet template.
2. Open the copy. A **MapSVG Setup** sidebar opens automatically.  
   If it doesn't appear, click **MapSVG → Setup** in the top menu bar.
3. The sidebar shows your one-time **Setup key**. Copy it.
4. In MapSVG, go to **Database (or Regions) → Auto-sync → Google Sheets**.
5. Enter the deployed **AppScript Web App URL** and the **setup key**, then click **Connect**.
6. Back in the sidebar, fill in:
   - **MapSVG sync URL** — shown in MapSVG after connecting  
     e.g. `https://example.com/wp-json/mapsvg/v1/objects/my_db/sync`
   - **Sheet tab name** — name of the sheet holding your data (default: `Sheet1`)
   - **ID column header** — must match MapSVG's "Google Sheet ID column" setting (default: `id`)
7. Click **Save** in the sidebar.
8. Save settings in MapSVG with sync mode set to **Google Sheets**.
9. Install the edit trigger:  
   **Extensions → Apps Script → Triggers → Add trigger → `onEditInstallable` → From spreadsheet → On edit**

---

## Notes

- The setup key is single-use: once `INITIALIZED=true`, setup requests are rejected.
- To change sync settings later (sheet name, ID column, sync URL) use **MapSVG → Setup** from the top menu — no redeployment needed.
- To disconnect and reconnect, click **Reset connection** in the sidebar. You will need to go through the setup flow again.
- `onEditInstallable` fires on any cell edit in the configured sheet and sends the entire changed row to MapSVG. Row **deletions** are not automatically detected; to push a deletion, mark the row with a special value or call `doPost` directly.
- When MapSVG creates a new record it returns the auto-assigned ID. AppScript writes it back into the `ID_COLUMN` cell of the new row so future edits are matched as updates. `LockService` prevents this write-back from triggering a second sync cycle.
