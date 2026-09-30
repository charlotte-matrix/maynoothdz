/**
 * MapSVG Gutenberg block registration.
 */
import Edit from "./edit.jsx"
import mapsvgBlockIcon from "./icon.jsx"

const { registerBlockType, createBlock } = wp.blocks

/**
 * Parse [mapsvg ...] shortcode text into block attributes.
 *
 * @param {string} text
 * @returns {{ mapId: number, title: string, selected: string, lazy: boolean } | null}
 */
function parseMapsvgShortcode(text) {
  if (!text || !/\[mapsvg\b/i.test(text)) {
    return null
  }

  const idMatch = text.match(/\bid=["']?(\d+)/i)
  if (!idMatch) {
    return null
  }

  const titleMatch = text.match(/\btitle=["']([^"']*)["']/i)
  const selectedMatch = text.match(/\bselected=["']([^"']*)["']/i)
  const lazy = /\blazy=["']?true["']?/i.test(text)

  return {
    mapId: Number(idMatch[1]),
    title: titleMatch ? titleMatch[1] : "",
    selected: selectedMatch ? selectedMatch[1] : "",
    lazy,
  }
}

registerBlockType("mapsvg/map", {
  icon: mapsvgBlockIcon,
  edit: Edit,
  save: () => null,
  transforms: {
    from: [
      {
        type: "block",
        blocks: ["core/shortcode"],
        isMatch: (attributes) => !!parseMapsvgShortcode(attributes.text || ""),
        transform: (attributes) => {
          const parsed = parseMapsvgShortcode(attributes.text || "")
          return createBlock("mapsvg/map", parsed)
        },
      },
      {
        type: "shortcode",
        tag: "mapsvg",
        attributes: {
          mapId: {
            type: "number",
            shortcode: ({ named }) => {
              return named.id ? Number(named.id) : 0
            },
          },
          title: {
            type: "string",
            shortcode: ({ named }) => named.title || "",
          },
          selected: {
            type: "string",
            shortcode: ({ named }) => named.selected || "",
          },
          lazy: {
            type: "boolean",
            shortcode: ({ named }) =>
              named.lazy === true || named.lazy === "true" || named.lazy === "1",
          },
        },
      },
    ],
  },
})
