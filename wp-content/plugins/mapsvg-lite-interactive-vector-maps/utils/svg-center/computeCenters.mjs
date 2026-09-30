#!/usr/bin/env node
/**
 * Compute visual label centers (pole of inaccessibility) for MapSVG region paths
 * and write data-label-x / data-label-y attributes.
 *
 * Usage (from plugin root):
 *   pnpm --dir utils/svg-center install
 *   node utils/svg-center/computeCenters.mjs maps/geo-calibrated/usa.svg
 *   node utils/svg-center/computeCenters.mjs maps/geo-calibrated/
 *   node utils/svg-center/computeCenters.mjs maps/geo-calibrated/ --force --dry-run
 */

import fs from "node:fs"
import path from "node:path"
import { fileURLToPath } from "node:url"

const __dirname = path.dirname(fileURLToPath(import.meta.url))

const HELP = `
mapsvg svg-center — set data-label-x / data-label-y from visual centers

Usage:
  node utils/svg-center/computeCenters.mjs <file-or-dir> [options]

Arguments:
  file-or-dir     One .svg file, or a directory (processed recursively)

Options:
  --force         Overwrite existing data-label-* (default: only fill missing)
  --dry-run       Print results, do not write files
  --precision=N   polylabel precision in SVG units (default: 0.5)
  --samples=N     path samples per SVG unit of length (default: 2)
  --verbose       Log every region
  --help, -h      Show this help

Examples:
  # from plugin root
  pnpm svg-center maps/geo-calibrated/usa.svg
  npm run svg-center -- maps/geo-calibrated/usa.svg

  # from utils/svg-center
  pnpm start -- maps/geo-calibrated/usa.svg
  npm start -- maps/geo-calibrated/usa.svg

  node utils/svg-center/computeCenters.mjs maps/geo-calibrated/ --force
`.trim()

/**
 * @typedef {{ force: boolean, dryRun: boolean, precision: number, samples: number, verbose: boolean, target: string | null }} CliOptions
 */

/**
 * @param {string[]} argv
 * @returns {CliOptions}
 */
function parseArgs(argv) {
  /** @type {CliOptions} */
  const opts = {
    force: false,
    dryRun: false,
    precision: 0.5,
    samples: 2,
    verbose: false,
    target: null,
  }

  for (const arg of argv) {
    if (arg === "--help" || arg === "-h") {
      console.log(HELP)
      process.exit(0)
    }
    if (arg === "--force") {
      opts.force = true
      continue
    }
    if (arg === "--dry-run") {
      opts.dryRun = true
      continue
    }
    if (arg === "--verbose") {
      opts.verbose = true
      continue
    }
    if (arg.startsWith("--precision=")) {
      opts.precision = Number(arg.slice("--precision=".length))
      continue
    }
    if (arg.startsWith("--samples=")) {
      opts.samples = Number(arg.slice("--samples=".length))
      continue
    }
    if (arg.startsWith("-")) {
      console.error(`Unknown option: ${arg}`)
      console.error(HELP)
      process.exit(1)
    }
    if (opts.target) {
      console.error("Only one file/directory argument is allowed.")
      process.exit(1)
    }
    opts.target = arg
  }

  if (!opts.target) {
    console.error("Missing <file-or-dir>.\n")
    console.error(HELP)
    process.exit(1)
  }

  if (!Number.isFinite(opts.precision) || opts.precision <= 0) {
    console.error("--precision must be a positive number")
    process.exit(1)
  }
  if (!Number.isFinite(opts.samples) || opts.samples <= 0) {
    console.error("--samples must be a positive number")
    process.exit(1)
  }

  return opts
}

/**
 * Plugin root = utils/svg-center/../..
 * Resolve target from cwd first, then from plugin root (so
 * `pnpm start -- maps/geo-calibrated/usa.svg` works from this folder).
 * @param {string} target
 * @returns {string}
 */
function resolveTarget(target) {
  if (path.isAbsolute(target)) return target

  const fromCwd = path.resolve(process.cwd(), target)
  if (fs.existsSync(fromCwd)) return fromCwd

  const pluginRoot = path.resolve(__dirname, "../..")
  const fromPlugin = path.resolve(pluginRoot, target)
  if (fs.existsSync(fromPlugin)) return fromPlugin

  return fromCwd
}

/**
 * @param {string} target
 * @returns {string[]}
 */
function collectSvgFiles(target) {
  const abs = resolveTarget(target)
  if (!fs.existsSync(abs)) {
    throw new Error(`Path not found: ${abs}`)
  }

  const stat = fs.statSync(abs)
  if (stat.isFile()) {
    if (!abs.toLowerCase().endsWith(".svg")) {
      throw new Error(`Not an .svg file: ${abs}`)
    }
    return [abs]
  }

  if (!stat.isDirectory()) {
    throw new Error(`Not a file or directory: ${abs}`)
  }

  /** @type {string[]} */
  const files = []

  /**
   * @param {string} dir
   */
  function walk(dir) {
    for (const name of fs.readdirSync(dir)) {
      if (name === "node_modules" || name === ".git") continue
      const full = path.join(dir, name)
      const st = fs.statSync(full)
      if (st.isDirectory()) {
        walk(full)
      } else if (st.isFile() && name.toLowerCase().endsWith(".svg")) {
        files.push(full)
      }
    }
  }

  walk(abs)
  files.sort()
  return files
}

/**
 * @param {number[][]} ring
 * @returns {number}
 */
function ringArea(ring) {
  let area = 0
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    area += ring[j][0] * ring[i][1] - ring[i][0] * ring[j][1]
  }
  return Math.abs(area / 2)
}

/**
 * @param {number[][]} ring
 * @returns {{ minX: number, minY: number, maxX: number, maxY: number, cx: number, cy: number }}
 */
function ringBBox(ring) {
  let minX = Infinity
  let minY = Infinity
  let maxX = -Infinity
  let maxY = -Infinity
  for (const [x, y] of ring) {
    if (x < minX) minX = x
    if (y < minY) minY = y
    if (x > maxX) maxX = x
    if (y > maxY) maxY = y
  }
  return {
    minX,
    minY,
    maxX,
    maxY,
    cx: (minX + maxX) / 2,
    cy: (minY + maxY) / 2,
  }
}

/**
 * Ray-cast point-in-polygon (ring must be closed).
 * @param {number} x
 * @param {number} y
 * @param {number[][]} ring
 * @returns {boolean}
 */
function pointInRing(x, y, ring) {
  let inside = false
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const xi = ring[i][0]
    const yi = ring[i][1]
    const xj = ring[j][0]
    const yj = ring[j][1]
    const intersect =
      yi > y !== yj > y && x < ((xj - xi) * (y - yi)) / (yj - yi + 0.0) + xi
    if (intersect) inside = !inside
  }
  return inside
}

/**
 * Min distance from point to ring edges.
 * @param {number} px
 * @param {number} py
 * @param {number[][]} ring
 * @returns {number}
 */
function distToBoundary(px, py, ring) {
  let min = Infinity
  for (let i = 0; i < ring.length - 1; i++) {
    const x1 = ring[i][0]
    const y1 = ring[i][1]
    const x2 = ring[i + 1][0]
    const y2 = ring[i + 1][1]
    const dx = x2 - x1
    const dy = y2 - y1
    const len2 = dx * dx + dy * dy
    let t = len2 === 0 ? 0 : ((px - x1) * dx + (py - y1) * dy) / len2
    if (t < 0) t = 0
    else if (t > 1) t = 1
    const qx = x1 + t * dx
    const qy = y1 + t * dy
    const d = Math.hypot(px - qx, py - qy)
    if (d < min) min = d
  }
  return min
}

/**
 * @param {string} absD
 * @returns {string[]}
 */
function splitSubpaths(absD) {
  const trimmed = absD.trim()
  if (!trimmed) return []
  return trimmed
    .split(/(?=[Mm])/)
    .map((s) => s.trim())
    .filter(Boolean)
}

/**
 * @param {string} subpathD
 * @param {number} samplesPerUnit
 * @param {{ svgPathProperties: typeof import('svg-path-properties').svgPathProperties }} libs
 * @returns {number[][] | null}
 */
function sampleRing(subpathD, samplesPerUnit, libs) {
  let props
  try {
    props = new libs.svgPathProperties(subpathD)
  } catch {
    return null
  }

  const length = props.getTotalLength()
  if (!Number.isFinite(length) || length < 0.5) return null

  const count = Math.max(12, Math.ceil(length * samplesPerUnit))
  /** @type {number[][]} */
  const ring = []

  for (let i = 0; i <= count; i++) {
    const pt = props.getPointAtLength((i / count) * length)
    ring.push([pt.x, pt.y])
  }

  const [x0, y0] = ring[0]
  const [xn, yn] = ring[ring.length - 1]
  if (x0 !== xn || y0 !== yn) {
    ring.push([x0, y0])
  }

  if (ring.length < 4) return null
  return ring
}

/**
 * @param {string} d
 * @param {number} samplesPerUnit
 * @param {{ SvgPath: typeof import('svgpath'), svgPathProperties: typeof import('svg-path-properties').svgPathProperties }} libs
 * @returns {number[][][]}
 */
function pathToRings(d, samplesPerUnit, libs) {
  const absD = new libs.SvgPath(d).abs().unshort().unarc().toString()
  const parts = splitSubpaths(absD)
  /** @type {number[][][]} */
  const rings = []

  for (const part of parts) {
    const ring = sampleRing(part, samplesPerUnit, libs)
    if (ring) rings.push(ring)
  }

  return rings
}

/**
 * Visual center for a path: polylabel on the largest subpath, then prefer a
 * more bbox-centered point when it sits on the same near-max clearance plateau
 * (avoids arbitrary left/right picks on nearly rectangular regions like SD).
 *
 * @param {string} d
 * @param {{ precision: number, samples: number }} opts
 * @param {{ polylabel: typeof import('polylabel'), SvgPath: any, svgPathProperties: any }} libs
 * @returns {{ x: number, y: number } | null}
 */
function visualCenter(d, opts, libs) {
  const rings = pathToRings(d, opts.samples, libs)
  if (!rings.length) return null

  rings.sort((a, b) => ringArea(b) - ringArea(a))
  const largest = rings[0]

  try {
    const pole = libs.polylabel([largest], opts.precision)
    if (!pole || !Number.isFinite(pole[0]) || !Number.isFinite(pole[1])) {
      return null
    }

    const r = typeof pole.distance === "number" ? pole.distance : 0
    const bb = ringBBox(largest)
    /** @type {{ x: number, y: number }[]} */
    const candidates = [
      { x: pole[0], y: pole[1] },
      { x: bb.cx, y: bb.cy },
      { x: bb.cx, y: pole[1] },
      { x: pole[0], y: bb.cy },
    ]

    // Accept points that keep ~polylabel clearance; among them pick nearest to bbox center.
    const threshold = r * 0.95
    let best = { x: pole[0], y: pole[1] }
    let bestToCenter = Math.hypot(pole[0] - bb.cx, pole[1] - bb.cy)

    for (const c of candidates) {
      if (!pointInRing(c.x, c.y, largest)) continue
      const clearance = distToBoundary(c.x, c.y, largest)
      if (clearance < threshold) continue
      const toCenter = Math.hypot(c.x - bb.cx, c.y - bb.cy)
      if (toCenter < bestToCenter) {
        bestToCenter = toCenter
        best = c
      }
    }

    return {
      x: Math.round(best.x),
      y: Math.round(best.y),
    }
  } catch {
    return null
  }
}

/**
 * @param {string} tag
 * @param {string} name
 * @returns {string | null}
 */
function getAttr(tag, name) {
  const re = new RegExp(`\\b${name}\\s*=\\s*("([^"]*)"|'([^']*)')`, "i")
  const m = tag.match(re)
  if (!m) return null
  return m[2] ?? m[3] ?? null
}

/**
 * @param {string} pathEl
 * @param {number} x
 * @param {number} y
 * @returns {string}
 */
function upsertLabelAttrs(pathEl, x, y) {
  const xAttr = `data-label-x="${x}"`
  const yAttr = `data-label-y="${y}"`

  let next = pathEl

  if (/\bdata-label-x\s*=/.test(next)) {
    next = next.replace(/\bdata-label-x\s*=\s*("[^"]*"|'[^']*')/, xAttr)
  }
  if (/\bdata-label-y\s*=/.test(next)) {
    next = next.replace(/\bdata-label-y\s*=\s*("[^"]*"|'[^']*')/, yAttr)
  }

  const hasX = /\bdata-label-x\s*=/.test(next)
  const hasY = /\bdata-label-y\s*=/.test(next)

  if (hasX && hasY) return next

  const insertBlock =
    (hasX ? "" : `\n     ${xAttr}`) + (hasY ? "" : `\n     ${yAttr}`)

  if (/\btitle\s*=/.test(next)) {
    return next.replace(/(\btitle\s*=\s*("[^"]*"|'[^']*'))/, `$1${insertBlock}`)
  }
  if (/\bid\s*=/.test(next)) {
    return next.replace(/(\bid\s*=\s*("[^"]*"|'[^']*'))/, `$1${insertBlock}`)
  }

  return next.replace(/<path\b/i, `<path${insertBlock}`)
}

/**
 * @param {string} svg
 * @returns {{ full: string, index: number }[]}
 */
function findPathElements(svg) {
  /** @type {{ full: string, index: number }[]} */
  const found = []
  const re = /<path\b[\s\S]*?(?:\/>|><\/path>)/gi
  let m
  while ((m = re.exec(svg)) !== null) {
    found.push({ full: m[0], index: m.index })
  }
  return found
}

/**
 * @param {string} filePath
 * @param {CliOptions} opts
 * @param {{ polylabel: any, SvgPath: any, svgPathProperties: any }} libs
 * @returns {{ updated: number, skipped: number, failed: number, total: number }}
 */
function processFile(filePath, opts, libs) {
  const original = fs.readFileSync(filePath, "utf8")
  const paths = findPathElements(original)

  let updated = 0
  let skipped = 0
  let failed = 0
  let output = original
  /** @type {{ from: number, to: number, text: string }[]} */
  const replacements = []

  for (const { full, index } of paths) {
    const d = getAttr(full, "d")
    if (!d) {
      skipped++
      continue
    }

    const id = getAttr(full, "id") || "(no-id)"
    const existingX = getAttr(full, "data-label-x")
    const existingY = getAttr(full, "data-label-y")

    if (existingX && existingY && !opts.force) {
      skipped++
      if (opts.verbose) {
        console.log(`  skip ${id} (already has ${existingX},${existingY})`)
      }
      continue
    }

    const center = visualCenter(
      d,
      { precision: opts.precision, samples: opts.samples },
      libs,
    )

    if (!center) {
      failed++
      console.warn(`  fail ${id}: could not compute center`)
      continue
    }

    const next = upsertLabelAttrs(full, center.x, center.y)
    if (next === full) {
      skipped++
      continue
    }

    replacements.push({ from: index, to: index + full.length, text: next })
    updated++

    if (opts.verbose || opts.dryRun) {
      const prev =
        existingX && existingY ? ` (was ${existingX},${existingY})` : ""
      console.log(`  ${id} → ${center.x},${center.y}${prev}`)
    }
  }

  if (updated > 0 && !opts.dryRun) {
    replacements.sort((a, b) => b.from - a.from)
    for (const r of replacements) {
      output = output.slice(0, r.from) + r.text + output.slice(r.to)
    }
    fs.writeFileSync(filePath, output, "utf8")
  }

  return { updated, skipped, failed, total: paths.length }
}

async function loadLibs() {
  const localNm = path.join(__dirname, "node_modules")
  if (!fs.existsSync(localNm)) {
    const rel = path.relative(process.cwd(), __dirname) || "."
    throw new Error(
      `Dependencies not installed.\nRun: pnpm --dir ${rel} install`,
    )
  }

  const [{ default: polylabel }, pathProps, { default: SvgPath }] =
    await Promise.all([
      import("polylabel"),
      import("svg-path-properties"),
      import("svgpath"),
    ])

  return {
    polylabel,
    svgPathProperties: pathProps.svgPathProperties,
    SvgPath,
  }
}

async function main() {
  const opts = parseArgs(process.argv.slice(2))
  const libs = await loadLibs()

  let files
  try {
    files = collectSvgFiles(opts.target)
  } catch (err) {
    console.error(err instanceof Error ? err.message : err)
    process.exit(1)
  }

  if (!files.length) {
    console.error("No .svg files found.")
    process.exit(1)
  }

  console.log(
    `Processing ${files.length} file(s)${opts.dryRun ? " [dry-run]" : ""}${opts.force ? " [force]" : ""}…`,
  )

  let totalUpdated = 0
  let totalSkipped = 0
  let totalFailed = 0

  for (const file of files) {
    const rel = path.relative(process.cwd(), file)
    console.log(`\n${rel}`)
    const stats = processFile(file, opts, libs)
    totalUpdated += stats.updated
    totalSkipped += stats.skipped
    totalFailed += stats.failed
    console.log(
      `  paths: ${stats.total}, updated: ${stats.updated}, skipped: ${stats.skipped}, failed: ${stats.failed}`,
    )
  }

  console.log(
    `\nDone. updated=${totalUpdated} skipped=${totalSkipped} failed=${totalFailed}`,
  )

  if (totalFailed > 0) process.exitCode = 2
}

main().catch((err) => {
  console.error(err instanceof Error ? err.message : err)
  process.exit(1)
})
