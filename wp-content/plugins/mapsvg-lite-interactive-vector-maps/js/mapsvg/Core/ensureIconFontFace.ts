/**
 * Icon-font @font-face must live on the document, not only inside a Shadow Root.
 * Browsers ignore @font-face from stylesheets attached to a shadow tree, so
 * .mapsvg-icon-* buttons render as empty squares in mobile Map/List toolbar.
 */

export const ICON_FONT_STYLE_ATTR = "data-mapsvg-icon-font"

export function buildIconFontFaceCss(pluginRootUrl: string, version?: string): string {
  const root = pluginRootUrl.endsWith("/") ? pluginRootUrl : `${pluginRootUrl}/`
  const cacheBust = version ? `?ver=${encodeURIComponent(version)}` : ""
  const font = `${root}css/font/mapsvg-icons`

  return `@font-face{font-family:"mapsvg-icons";font-weight:normal;font-style:normal;src:url("${font}.eot${cacheBust}");src:url("${font}.eot${cacheBust}#iefix") format("embedded-opentype"),url("${font}.woff2${cacheBust}") format("woff2"),url("${font}.woff${cacheBust}") format("woff"),url("${font}.ttf${cacheBust}") format("truetype"),url("${font}.svg${cacheBust}#mapsvg-icons") format("svg")}`
}

export function ensureIconFontFace(
  pluginRootUrl: string,
  version?: string,
  doc?: Pick<Document, "head" | "createElement">,
): void {
  const target = doc ?? (typeof document !== "undefined" ? document : undefined)
  if (!target?.head) {
    return
  }
  if (target.head.querySelector(`style[${ICON_FONT_STYLE_ATTR}]`)) {
    return
  }

  const style = target.createElement("style")
  style.setAttribute(ICON_FONT_STYLE_ATTR, "")
  style.textContent = buildIconFontFaceCss(pluginRootUrl, version)
  target.head.appendChild(style)
}
