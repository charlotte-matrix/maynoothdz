/**
 * MapSVG block icon (from img/logo-icon.svg).
 */
const { createElement } = wp.element

const mapsvgBlockIcon = createElement(
  "svg",
  {
    xmlns: "http://www.w3.org/2000/svg",
    viewBox: "0 0 192 192",
  },
  createElement("path", {
    d: "M95.2,0.2C42.5,0.2,0.1,43.4,0.1,96.1S43.4,192,96.1,192S192,148.8,192,96.1S147.9,0.2,95.2,0.2z M158.3,139.3H33c-5.2,0-8.6-6-6-10.4L90,20c2.6-4.3,9.5-4.3,12.1,0l63.1,108.9C166.9,133.2,163.5,139.3,158.3,139.3z",
  }),
)

export default mapsvgBlockIcon
