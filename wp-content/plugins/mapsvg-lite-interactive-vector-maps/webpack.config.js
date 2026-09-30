import { dirname } from "path"
import { fileURLToPath } from "url"

const __dirname = dirname(fileURLToPath(import.meta.url))

export default {
  entry: {
    gutenberg: "./js/mapsvg-admin/gutenberg/mapsvg-gutenberg.jsx",
    block: "./js/mapsvg-admin/gutenberg/block/index.jsx",
    elementor: "./js/mapsvg-admin/elementor/mapsvg-elementor.js",
  },
  output: {
    path: __dirname,
    filename: "dist/mapsvg-[name].build.js",
  },
  externals: {
    "@wordpress/element": "wp.element",
    "@wordpress/blocks": "wp.blocks",
    "@wordpress/block-editor": "wp.blockEditor",
    "@wordpress/components": "wp.components",
    "@wordpress/i18n": "wp.i18n",
    "@wordpress/api-fetch": "wp.apiFetch",
    "@wordpress/compose": "wp.compose",
    jquery: "jQuery",
  },
  resolve: {
    extensions: [".js", ".jsx"],
  },
  module: {
    rules: [
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: [
          {
            loader: "babel-loader",
            options: {
              presets: [
                [
                  "@babel/preset-react",
                  {
                    pragma: "wp.element.createElement",
                    pragmaFrag: "wp.element.Fragment",
                  },
                ],
              ],
            },
          },
        ],
      },
    ],
  },
}
