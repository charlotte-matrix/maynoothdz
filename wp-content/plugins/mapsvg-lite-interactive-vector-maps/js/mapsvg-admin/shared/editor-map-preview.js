/**
 * Shared helpers for live MapSVG preview in WP editors (Gutenberg / Elementor).
 * Handles parent-vs-iframe mapsvg loading and stylesheet injection.
 */

/**
 * @param {Window} view
 * @returns {object|null}
 */
export function getMapsvgClient(view) {
  if (view && view.mapsvg && view.mapsvg.map) {
    return view.mapsvg
  }
  if (typeof window !== "undefined" && window.mapsvg && window.mapsvg.map) {
    return window.mapsvg
  }
  return null
}

/**
 * @param {string} url
 * @returns {string}
 */
export function toAbsoluteUrl(url) {
  if (!url) {
    return url
  }
  if (/^https?:\/\//i.test(url)) {
    return url
  }
  try {
    return new URL(url, window.location.origin).href
  } catch (e) {
    return url
  }
}

/**
 * @param {ParentNode} target
 * @param {Document} ownerDocument
 * @param {object} mapsvg
 * @param {object} map
 */
export function injectEditorMapStyles(target, ownerDocument, mapsvg, map) {
  target.querySelectorAll("link[data-mapsvg-editor-style]").forEach((node) => node.remove())
  target.querySelectorAll("style[data-mapsvg-editor-style]").forEach((node) => node.remove())

  const params = window.mapsvgFrontendParams || {}
  const styles =
    mapsvg.styles && mapsvg.styles.length ? mapsvg.styles : params.styles || []

  styles.forEach((style) => {
    const link = ownerDocument.createElement("link")
    link.rel = "stylesheet"
    link.setAttribute("data-mapsvg-editor-style", style.name || "style")
    link.href =
      toAbsoluteUrl(style.url) +
      (style.version ? `?ver=${encodeURIComponent(style.version)}` : "")
    target.appendChild(link)
  })

  const root = (mapsvg.routes && mapsvg.routes.root) || (params.routes && params.routes.root) || ""
  const themeName =
    (map.options && map.options.theme && map.options.theme.name) || "default"
  const themeLink = ownerDocument.createElement("link")
  themeLink.rel = "stylesheet"
  themeLink.setAttribute("data-mapsvg-editor-style", "theme")
  themeLink.href = toAbsoluteUrl(`${root}themes/${themeName}/assets/css/styles.css`)
  target.appendChild(themeLink)

  if (map.options && map.options.css) {
    const styleEl = ownerDocument.createElement("style")
    styleEl.setAttribute("data-mapsvg-editor-style", "custom")
    styleEl.textContent = String(map.options.css).replace(/%id%/g, String(map.id))
    target.appendChild(styleEl)
  }
}

/**
 * @param {Window} view
 * @param {number} timeoutMs
 * @returns {Promise<object>}
 */
export function waitForMapsvg(view, timeoutMs = 15000) {
  return new Promise((resolve, reject) => {
    const existing = getMapsvgClient(view)
    if (existing) {
      resolve(existing)
      return
    }

    const onReady = () => {
      const client = getMapsvgClient(view)
      if (client) {
        cleanup()
        resolve(client)
      }
    }

    const cleanup = () => {
      if (view && view.removeEventListener) {
        view.removeEventListener("mapsvgClientInitialized", onReady)
      }
      window.removeEventListener("mapsvgClientInitialized", onReady)
      window.clearInterval(pollId)
      window.clearTimeout(timeoutId)
    }

    if (view && view.addEventListener) {
      view.addEventListener("mapsvgClientInitialized", onReady)
    }
    window.addEventListener("mapsvgClientInitialized", onReady)
    const pollId = window.setInterval(onReady, 100)
    const timeoutId = window.setTimeout(() => {
      cleanup()
      reject(new Error("MapSVG failed to initialize in the editor canvas."))
    }, timeoutMs)
  })
}

/**
 * Mount a read-only MapSVG instance into a host element for editor preview.
 *
 * @param {object} options
 * @param {HTMLElement} options.host outer host (kept intact on destroy)
 * @param {number} options.mapId
 * @param {string} [options.selected]
 * @param {string} [options.instanceId]
 * @param {object} [options.existingMap] previous instance to destroy
 * @returns {Promise<object|null>} map instance
 */
export async function mountEditorMapPreview({
  host,
  mapId,
  selected = "",
  instanceId = "preview",
  existingMap = null,
}) {
  if (!host || !mapId) {
    return null
  }

  const ownerDocument = host.ownerDocument
  const view = ownerDocument.defaultView
  const mapsvg = await waitForMapsvg(view)

  if (existingMap) {
    try {
      existingMap.destroy()
    } catch (e) {
      // ignore
    }
  }

  host.innerHTML = ""
  const container = ownerDocument.createElement("div")
  container.id = `mapsvg-editor-${instanceId}`
  container.className = "mapsvg"
  container.setAttribute("data-id", String(mapId))
  container.style.width = "100%"
  container.style.height = "0"
  container.style.paddingBottom = "56.25%"
  if (selected) {
    container.setAttribute("selected", String(selected).replace(/ /g, "_"))
  }
  host.appendChild(container)

  const regionId = selected ? String(selected).replace(/ /g, "_") : ""
  const map = new mapsvg.map(container, {
    id: mapId,
    options: { id: mapId, useShadowRoot: true },
  })

  map.events.on("beforeInit", () => {
    map.options.useShadowRoot = true
    if (map.options.scroll) {
      map.options.scroll.on = false
      map.options.scroll.spacebar = false
    }
  })

  await new Promise((resolve) => {
    map.events.once("afterInit", () => {
      const styleTarget =
        (map.containers && map.containers.shadowRoot) || ownerDocument.head

      if (styleTarget.querySelectorAll) {
        styleTarget.querySelectorAll("link[rel='stylesheet']").forEach((node) => node.remove())
        styleTarget.querySelectorAll("style").forEach((node) => node.remove())
      }

      injectEditorMapStyles(styleTarget, ownerDocument, mapsvg, map)

      if (regionId) {
        try {
          map.selectRegion(regionId)
        } catch (e) {
          // ignore missing region
        }
      }
      resolve()
    })
  })

  return map
}

/**
 * @param {object|null} map
 */
export function destroyEditorMapPreview(map) {
  if (!map) {
    return
  }
  try {
    map.destroy()
  } catch (e) {
    // ignore
  }
}
