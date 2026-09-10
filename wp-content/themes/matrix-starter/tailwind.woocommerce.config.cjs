// WooCommerce-scoped Tailwind build (used by build:css:woo)
process.env.BUILD_TARGET = 'woocommerce';
module.exports = require('./tailwind.config.cjs');
