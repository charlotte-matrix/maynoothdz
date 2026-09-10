module.exports = {
  mode: 'jit',
  important: '.wp-editor-content',     // <— scope all utilities to the editor iframe body
  corePlugins: { preflight: false },   // <— keep off to avoid nuking TinyMCE defaults
  content: ['assets/css/editor.css'],
  theme: { extend: {} },
  plugins: [require('@tailwindcss/typography')]
};