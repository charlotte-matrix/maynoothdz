const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
require('dotenv').config();

module.exports = {
  mode: process.env.NODE_ENV || 'development',

  entry: {
    app: './assets/js/app.js',
    styles: './assets/css/app.css',
  },

  output: {
    filename: '[name].js',
    path: path.resolve(__dirname, 'dist'),
    publicPath: '/wp-content/themes/matrix-starter/dist/',
  },

  module: {
    rules: [
      {
        test: /\.css$/,
        use: [
          MiniCssExtractPlugin.loader,
          'css-loader',
          'postcss-loader',
        ],
      },
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: { presets: ['@babel/preset-env'] },
        },
      },
    ],
  },

  plugins: [
    new MiniCssExtractPlugin({ filename: '[name].css' }),
  ],

  optimization: {
    minimize: process.env.NODE_ENV === 'production',
    minimizer: [
      `...`, // <-- Keeps default JS minimizer (Terser)
      new CssMinimizerPlugin(), // <-- Minify CSS
    ],
  },

  devServer: {
    static: { directory: path.join(__dirname), watch: true },
    compress: true,
    port: process.env.DEV_SERVER_PORT || 3000,
    client: {
      // Hide noisy browser runtime overlay for cross-origin "Script error."
      // while preserving build/compile errors in terminal output.
      overlay: {
        errors: true,
        warnings: false,
        runtimeErrors: false,
      },
    },
    proxy: {
      '/': {
        target: process.env.WP_HOME || 'http://localhost:10054',
        changeOrigin: true,
        secure: false,
      },
    },
    hot: false,
    // Never write dev-server bundles into dist/; they include WDS client code.
    // Production assets must come only from `npm run build`.
    devMiddleware: { writeToDisk: false },
    watchFiles: ['assets/**/*.{js,css}'],
  },
};
