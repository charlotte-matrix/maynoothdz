import assert from "node:assert/strict"
import fs from "node:fs"
import { describe, it } from "node:test"
import vm from "node:vm"
import { fileURLToPath } from "node:url"
import path from "node:path"

const srcPath = path.join(path.dirname(fileURLToPath(import.meta.url)), "googleSheetsCsvUrl.js")
const code = fs.readFileSync(srcPath, "utf8")
const module = { exports: {} }
const sandbox = {
  module,
  exports: module.exports,
  globalThis: null,
}
sandbox.globalThis = sandbox
vm.runInNewContext(code, sandbox, { filename: "googleSheetsCsvUrl.js" })

const {
  isGoogleSheetsSpreadsheetUrl,
  isGoogleSheetsCsvExportUrl,
  getGoogleSheetsCsvUrlError,
} = module.exports

const CSV_PUB =
  "https://docs.google.com/spreadsheets/d/e/2PACX-1vRfFyy-VR3Sn9ZkF73xVFbwcfiM3azQZn1eFtXcL7AYS0p432l8QmesfdE4wZddg41xQPegkWAicjIa/pub?gid=1867256673&single=true&output=csv"
const CSV_EXPORT = "https://docs.google.com/spreadsheets/d/1abc/export?format=csv&gid=0"
const PUBHTML =
  "https://docs.google.com/spreadsheets/d/e/2PACX-1vRfFyy/pubhtml?widget=true&headers=false"
const EDIT = "https://docs.google.com/spreadsheets/d/1abc/edit?usp=sharing"
const OTHER_CSV = "https://example.com/data.csv"

describe("googleSheetsCsvUrl", () => {
  it("detects Google Spreadsheets hosts", () => {
    assert.equal(isGoogleSheetsSpreadsheetUrl(CSV_PUB), true)
    assert.equal(isGoogleSheetsSpreadsheetUrl(PUBHTML), true)
    assert.equal(isGoogleSheetsSpreadsheetUrl(OTHER_CSV), false)
  })

  it("accepts published CSV and export?format=csv", () => {
    assert.equal(isGoogleSheetsCsvExportUrl(CSV_PUB), true)
    assert.equal(isGoogleSheetsCsvExportUrl(CSV_EXPORT), true)
    assert.equal(getGoogleSheetsCsvUrlError(CSV_PUB), null)
    assert.equal(getGoogleSheetsCsvUrlError(CSV_EXPORT), null)
  })

  it("rejects pubhtml / edit web-view links", () => {
    assert.equal(isGoogleSheetsCsvExportUrl(PUBHTML), false)
    assert.equal(isGoogleSheetsCsvExportUrl(EDIT), false)
    assert.match(getGoogleSheetsCsvUrlError(PUBHTML), /web view|output=csv/i)
    assert.match(getGoogleSheetsCsvUrlError(EDIT), /web view|output=csv/i)
  })

  it("allows non-Google remote CSV URLs", () => {
    assert.equal(getGoogleSheetsCsvUrlError(OTHER_CSV), null)
    assert.equal(getGoogleSheetsCsvUrlError(""), null)
  })
})
