/**
 * MapSVG Gutenberg block — edit view with live map preview.
 */
import { useEffect, useState, useRef } from "@wordpress/element"
import mapsvgBlockIcon from "./icon.jsx"
import {
  mountEditorMapPreview,
  destroyEditorMapPreview,
} from "../../shared/editor-map-preview.js"

const { __ } = wp.i18n
const { useBlockProps, InspectorControls, BlockControls } = wp.blockEditor
const {
  Placeholder,
  SelectControl,
  Spinner,
  PanelBody,
  TextControl,
  ToggleControl,
  ToolbarGroup,
  ToolbarButton,
  Notice,
} = wp.components
const { useRefEffect } = wp.compose
const apiFetch = wp.apiFetch

/**
 * @param {object} props
 * @returns {JSX.Element}
 */
export default function Edit({ attributes, setAttributes, clientId }) {
  const { mapId, title, selected, lazy } = attributes
  const [maps, setMaps] = useState([])
  const [loadingMaps, setLoadingMaps] = useState(true)
  const [loadError, setLoadError] = useState("")
  const [previewError, setPreviewError] = useState("")
  const mapInstanceRef = useRef(null)

  useEffect(() => {
    let cancelled = false
    setLoadingMaps(true)
    setLoadError("")

    apiFetch({ path: "/mapsvg/v1/maps" })
      .then((response) => {
        if (cancelled) return
        const items = response && response.items ? response.items : []
        setMaps(items)
      })
      .catch(() => {
        if (cancelled) return
        setLoadError(__("Could not load maps list.", "mapsvg"))
      })
      .finally(() => {
        if (!cancelled) setLoadingMaps(false)
      })

    return () => {
      cancelled = true
    }
  }, [])

  const mapOptions = [
    { label: __("Select a map…", "mapsvg"), value: "0" },
    ...maps.map((map) => ({
      label: map.title || `Map #${map.id}`,
      value: String(map.id),
    })),
  ]

  const onSelectMap = (value) => {
    const id = Number(value) || 0
    const found = maps.find((m) => Number(m.id) === id)
    setAttributes({
      mapId: id,
      title: found ? found.title || "" : "",
    })
    setPreviewError("")
  }

  const clearMap = () => {
    setAttributes({ mapId: 0, title: "" })
    setPreviewError("")
  }

  const previewRef = useRefEffect(
    (element) => {
      if (!mapId) {
        return undefined
      }

      let cancelled = false

      const run = async () => {
        try {
          const map = await mountEditorMapPreview({
            host: element,
            mapId,
            selected,
            instanceId: clientId,
            existingMap: mapInstanceRef.current,
          })
          if (cancelled) {
            destroyEditorMapPreview(map)
            return
          }
          mapInstanceRef.current = map
        } catch (err) {
          if (!cancelled) {
            setPreviewError(
              err && err.message
                ? err.message
                : __("Map preview failed to load.", "mapsvg"),
            )
          }
        }
      }

      run()

      return () => {
        cancelled = true
        destroyEditorMapPreview(mapInstanceRef.current)
        mapInstanceRef.current = null
        element.innerHTML = ""
      }
    },
    [mapId, selected, clientId],
  )

  const blockProps = useBlockProps({
    className: "mapsvg-block",
  })

  if (!mapId) {
    return (
      <div {...blockProps}>
        <Placeholder
          icon={mapsvgBlockIcon}
          label={__("MapSVG", "mapsvg")}
          instructions={__("Choose a map to embed on this page.", "mapsvg")}
        >
          {loadingMaps && <Spinner />}
          {loadError && (
            <Notice status="error" isDismissible={false}>
              {loadError}
            </Notice>
          )}
          {!loadingMaps && !loadError && (
            <SelectControl
              label={__("Map", "mapsvg")}
              value="0"
              options={mapOptions}
              onChange={onSelectMap}
            />
          )}
        </Placeholder>
      </div>
    )
  }

  return (
    <>
      <BlockControls>
        <ToolbarGroup>
          <ToolbarButton icon="edit" label={__("Change map", "mapsvg")} onClick={clearMap} />
        </ToolbarGroup>
      </BlockControls>
      <InspectorControls>
        <PanelBody title={__("Map settings", "mapsvg")} initialOpen={true}>
          <SelectControl
            label={__("Map", "mapsvg")}
            value={String(mapId)}
            options={mapOptions}
            onChange={onSelectMap}
          />
          <TextControl
            label={__("Selected Region ID", "mapsvg")}
            value={selected || ""}
            onChange={(value) => setAttributes({ selected: value })}
          />
          <ToggleControl
            label={__("Lazy load", "mapsvg")}
            help={__("Load the map when it enters the viewport (front end only).", "mapsvg")}
            checked={!!lazy}
            onChange={(value) => setAttributes({ lazy: !!value })}
          />
        </PanelBody>
      </InspectorControls>
      <div {...blockProps}>
        {previewError && (
          <Notice status="error" isDismissible={false}>
            {previewError}
          </Notice>
        )}
        <div
          className="mapsvg-block__preview"
          style={{
            position: "relative",
            width: "100%",
            minHeight: "280px",
            userSelect: "none",
          }}
        >
          <div ref={previewRef} className="mapsvg-block__canvas" />
          <div
            className="mapsvg-block__interaction-shield"
            style={{
              position: "absolute",
              inset: 0,
              zIndex: 5,
            }}
            aria-hidden="true"
          />
          {(title || mapId) && (
            <div
              style={{
                position: "absolute",
                left: 8,
                bottom: 8,
                zIndex: 6,
                padding: "2px 8px",
                background: "rgba(0,0,0,0.55)",
                color: "#fff",
                fontSize: 12,
                borderRadius: 2,
                pointerEvents: "none",
              }}
            >
              {title || `Map #${mapId}`}
            </div>
          )}
        </div>
      </div>
    </>
  )
}
