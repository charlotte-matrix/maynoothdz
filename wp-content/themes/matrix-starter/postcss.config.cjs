// postcss.config.cjs
module.exports = {
  plugins: {
    tailwindcss: { config: process.env.TAILWIND_CONFIG || './tailwind.config.js' },
    autoprefixer: {},
  },
};