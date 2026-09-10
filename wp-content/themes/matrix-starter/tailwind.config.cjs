// Pure CJS config. No ESM imports. No TypeScript syntax.
const plugin = require('tailwindcss/plugin');
const safelist = require('./tailwind-safelist.cjs');

const baseConfig = {
  // Tailwind v3+: JIT is always on; do NOT set mode:'jit'
  content: [
    './*.php',
    './acf-fields/**/*.php',
    './inc/**/*.php',
    './template-parts/**/*.php',
    './woocommerce/**/*.php',
    './assets/js/**/*.js',
    '../plugins/matrix-donations/**/*.php',
    '../plugins/matrix-donations/assets/js/**/*.js',
  ],
  theme: {
    extend: {
      fontFamily: {
        primary: ['var(--font-primary, "Public Sans")', 'sans-serif'],
        sans: ['var(--font-primary, "Public Sans")', 'sans-serif'],
        montserrat: ['var(--font-montserrat, "Montserrat")', 'sans-serif'],
        comfortaa: ['var(--font-comfortaa, "Comfortaa")', 'sans-serif'],
        heading: ['Great Vibes', 'cursive'],
        decorative: ['Great Vibes', 'cursive'],
      },
      colors: {
        primary: { DEFAULT:'#041227', light:'#9AA770', dark:'#788941', 200:'#BBC4A0', 100:'#DDE2CF', 50:'#EEF0E7' },
        secondary:{ DEFAULT:'#AA4A44', light:'#E9A777', dark:'#DE7C34', 200:'#F0C5A5', 100:'#F8E2D2', 50:'#FBF0E8' },
        tertiary: { DEFAULT:'#F6F6F6' },
        background:{ DEFAULT:'#ffffff', light:'#EFF5EC', dark:'#000000' },
        neutral:{ DEFAULT:'#101828', 800:'#1D2939', 700:'#344054', 600:'#475467', 500:'#667085', 400:'#98A2B3', 300:'#D0D5DD', 200:'#EAECF0', 100:'#F2F4F7', 50:'#F9FAFB', 25:'#FCFCFD' },
        highlight:{ primary:'#101828', secondary:'#041227', light:'#FFF9E2' },
        text:{ primary:'#1D2939', secondary:'#ffffff', accent:'#1D2939' },
        hover:{ bg:'#F6F6F6', text:'#000' },
        /* Design system: SR / Turquoise / Mint */
        turquoise: { 500: '#1C959B' },
        'blue-sr': { 10: '#EBF9FF', 400: '#008BCC' },
        mint: { 500: '#87CEB7' },
      },
      backgroundImage: {
        'gradient-blue-3': 'linear-gradient(313deg, #059DED 24.08%, #28B2FA 63%)',
      },
      backgroundColor: { hover:'#F6F6F6' },
      textColor: { hover:'#041227' },
      fontSize: { xs:'14px', base:'16px', relative_p:'20px' },
      width: { 'container-md':'1084px','container-lg':'1280px','container':'1280px' },
      maxWidth: { container:'1440px', xxs:'320px', xs:'480px', mob:'575px', sm:'640px', md:'768px', lg:'1200px', xl:'1280px', xxl:'1440px', ultrawide:'1920px' },
      borderRadius: { custom:'0px','custom-sm':'4px','custom-md':'8px','custom-lg':'16px','custom-xl':'40px','custom-full':'100%','btn':'0px' },
      animation: { scroll300:'scroll 300s linear infinite' },
      keyframes: { scroll: { '0%':{ transform:'translateX(0)' }, '100%':{ transform:'translateX(-100%)' } } },
    },
    screens: { xxs:'320px', xs:'480px', mob:'575px', sm:'640px', md:'768px', lg:'1100px', xl:'1280px', xxl:'1440px', ultrawide:'1920px' },
    container: {
      center:true,
      padding:'1.5rem',
      screens: { xxs:'320px', xs:'480px', mob:'575px', sm:'640px', md:'768px', lg:'1024px', xl:'1280px', xxl:'1480px', ultrawide:'1920px' },
    },
  },
  variants: {
    extend: {
      backgroundColor: ['not-first'],
      display: ['before', 'after'],
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography'),
    // If this ESM plugin caused issues, keep it disabled:
    // require('tailwindcss-pseudo')({ empty:true, before:true, after:true }),
  ],
  safelist,
};

// Choose build by env var (optional)
const buildTarget = process.env.BUILD_TARGET || 'main';

let config;
switch (buildTarget) {
  case 'editor':
    config = {
      ...baseConfig,
      important: true,
      content: ['./assets/css/editor.css'],
      corePlugins: { preflight: false },
      plugins: [require('@tailwindcss/typography')],
      safelist: ['highlight-text','btn-primary','btn-secondary','info-box','warning-box'],
    };
    break;

  case 'woocommerce':
    config = {
      ...baseConfig,
      important: '.woocommerce',
      content: [
        './woocommerce/**/*.php',
        './assets/css/woocommerce.css',
        './templates/woocommerce/**/*.php',
      ],
      plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/typography'),
        plugin(({ addComponents, theme }) => {
          addComponents({
            '.wc-button': {
              backgroundColor: theme('colors.primary.DEFAULT'),
              color: theme('colors.text.secondary'),
              padding: `${theme('spacing.3')} ${theme('spacing.6')}`,
              borderRadius: theme('borderRadius.btn'),
              fontWeight: theme('fontWeight.medium'),
              '&:hover': { backgroundColor: theme('colors.primary.dark') },
            },
            '.wc-price': { color: theme('colors.primary.DEFAULT'), fontWeight: theme('fontWeight.bold'), fontSize: theme('fontSize.lg') },
            '.wc-sale-badge': {
              backgroundColor: theme('colors.secondary.DEFAULT'),
              color: theme('colors.text.secondary'),
              padding: `${theme('spacing.1')} ${theme('spacing.2')}`,
              borderRadius: theme('borderRadius.custom-sm'),
              fontSize: theme('fontSize.xs'),
              fontWeight: theme('fontWeight.bold'),
            },
          });
        }),
      ],
      safelist: [
        'wc-product-card','wc-btn-primary','wc-btn-secondary','wc-sale-badge','wc-price',
        'wc-message-success','wc-message-error','wc-star-rating',
        'grid-cols-1','grid-cols-2','grid-cols-3','grid-cols-4',
        'md:grid-cols-2','md:grid-cols-3','lg:grid-cols-4',
      ],
    };
    break;

  default:
    config = baseConfig;
}

module.exports = config;
