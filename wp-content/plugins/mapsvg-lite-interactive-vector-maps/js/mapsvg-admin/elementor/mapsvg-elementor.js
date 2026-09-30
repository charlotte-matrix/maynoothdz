/**
 * Elementor preview: mount live MapSVG maps for the mapsvg widget.
 */
import {
  mountEditorMapPreview,
  destroyEditorMapPreview,
} from "../shared/editor-map-preview.js"

const instances = new WeakMap()

/**
 * @param {JQuery} $element
 */
async function onWidgetReady($element) {
  const host = $element[0].querySelector(".mapsvg-elementor-host")
  if (!host) {
    return
  }

  const mapId = Number(host.getAttribute("data-map-id") || 0)
  if (!mapId) {
    return
  }

  const selected = host.getAttribute("data-selected") || ""
  const widgetId = host.getAttribute("data-widget-id") || host.id || String(mapId)
  const existing = instances.get($element[0]) || null

  try {
    const map = await mountEditorMapPreview({
      host,
      mapId,
      selected,
      instanceId: `elementor-${widgetId}`,
      existingMap: existing,
    })
    if (map) {
      instances.set($element[0], map)
    }
  } catch (err) {
    // eslint-disable-next-line no-console
    console.error("[MapSVG Elementor]", err)
  }
}

/**
 * @param {HTMLElement} element
 */
function destroyWidgetMap(element) {
  const map = instances.get(element)
  if (map) {
    destroyEditorMapPreview(map)
    instances.delete(element)
  }
}

function bindElementorHooks() {
  if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
    return false
  }

  window.elementorFrontend.hooks.addAction(
    "frontend/element_ready/mapsvg.default",
    ($element) => {
      onWidgetReady($element)
    },
  )

  // Cleanup when Elementor removes a widget from the preview.
  if (window.elementor && window.elementor.channels && window.elementor.channels.editor) {
    // no-op: element_ready remount handles re-renders; destroy via host replace
  }

  return true
}

function boot() {
  if (bindElementorHooks()) {
    return
  }

  // Preview iframe may load our script before elementorFrontend is ready.
  window.addEventListener("elementor/frontend/init", () => {
    bindElementorHooks()
  })

  // Fallback poll — Elementor preview timing varies by version.
  let attempts = 0
  const pollId = window.setInterval(() => {
    attempts += 1
    if (bindElementorHooks() || attempts > 40) {
      window.clearInterval(pollId)
    }
  }, 250)
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", boot)
} else {
  boot()
}
