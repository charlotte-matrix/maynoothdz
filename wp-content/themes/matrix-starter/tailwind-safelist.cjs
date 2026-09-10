// CommonJS export — NOT ESM
/** @type {import('tailwindcss').Config['safelist']} */
module.exports = [
  // JS-toggled header classes (Headroom/Alpine)
  'fixed', 'top-0', 'left-0', 'w-full', 'z-50',
  'transition-transform', 'duration-300', 'ease-in-out',
  'translate-y-0', '-translate-y-full',

  // Button component (design system primary CTA)
  'btn-primary',

  // PACE / brand fonts
  'font-montserrat',
  'font-comfortaa',

  // Visibility/opacity/layout toggles
  'hidden', 'block', 'flex', 'grid',
  'opacity-0', 'opacity-100',

  // Common grids
  'grid-cols-1', 'grid-cols-2', 'grid-cols-3', 'grid-cols-4',
  'md:grid-cols-2', 'md:grid-cols-3', 'lg:grid-cols-4',

  // Image sizing helpers you’re using
  'max-w-none', 'max-w-full', 'max-w-[50%]', 'w-1/2', 'w-full',
  'max-w-[1158px]',

  // Optional patterns (regex must match Tailwind’s generated class names)
  { pattern: /-?translate-(x|y)-(0|full|\d+)/ },
  { pattern: /opacity-(0|25|50|75|100)/ },

  ...Array.from({ length: 101 }, (_, i) => `p-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `px-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `py-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `pt-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `pb-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `pl-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `pr-[${i + 1}rem]`),
  // Dynamic padding classes devices
  ...Array.from({ length: 101 }, (_, i) => `xs:p-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xs:px-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xs:py-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xs:pt-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xs:pb-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xs:pl-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xs:pr-[${i + 1}rem]`),
  //mob
  ...Array.from({ length: 101 }, (_, i) => `mob:p-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `mob:px-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `mob:py-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `mob:pt-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `mob:pb-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `mob:pl-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `mob:pr-[${i + 1}rem]`),
  //sm
  ...Array.from({ length: 101 }, (_, i) => `sm:p-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `sm:px-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `sm:py-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `sm:pt-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `sm:pb-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `sm:pl-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `sm:pr-[${i + 1}rem]`),
  //md
  ...Array.from({ length: 101 }, (_, i) => `md:p-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `md:px-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `md:py-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `md:pt-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `md:pb-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `md:pl-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `md:pr-[${i + 1}rem]`),
  //lg
  ...Array.from({ length: 101 }, (_, i) => `lg:p-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `lg:px-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `lg:py-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `lg:pt-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `lg:pb-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `lg:pl-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `lg:pr-[${i + 1}rem]`),
  //xl
  ...Array.from({ length: 101 }, (_, i) => `xl:p-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xl:px-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xl:py-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xl:pt-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xl:pb-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xl:pl-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xl:pr-[${i + 1}rem]`),
  //xxl
  ...Array.from({ length: 101 }, (_, i) => `xxl:p-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xxl:px-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xxl:py-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xxl:pt-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xxl:pb-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xxl:pl-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xxl:pr-[${i + 1}rem]`),
  //ultrawide
  ...Array.from({ length: 101 }, (_, i) => `ultrawide:p-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `ultrawide:px-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `ultrawide:py-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `ultrawide:pt-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `ultrawide:pb-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `ultrawide:pl-[${i + 1}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `ultrawide:pr-[${i + 1}rem]`),
  // Dynamic border classes
  ...Array.from({ length: 101 }, (_, i) => `border-[${i}px]`),
  ...Array.from({ length: 101 }, (_, i) => `border-t-[${i}px]`),
  ...Array.from({ length: 101 }, (_, i) => `border-b-[${i}px]`),
  ...Array.from({ length: 101 }, (_, i) => `border-l-[${i}px]`),
  ...Array.from({ length: 101 }, (_, i) => `border-r-[${i}px]`),
  // text size
  ...Array.from({ length: 101 }, (_, i) => `text-[${i}px]`),
  // text size for devices
  ...Array.from({ length: 101 }, (_, i) => `xs:text-[${i}px]`),
  ...Array.from({ length: 101 }, (_, i) => `mob:text-[${i}px]`),
  ...Array.from({ length: 101 }, (_, i) => `sm:text-[${i}px]`),
  ...Array.from({ length: 101 }, (_, i) => `md:text-[${i}px]`),
  ...Array.from({ length: 101 }, (_, i) => `lg:text-[${i}px]`),
  ...Array.from({ length: 101 }, (_, i) => `xl:text-[${i}px]`),
  ...Array.from({ length: 101 }, (_, i) => `xxl:text-[${i}px]`),
  ...Array.from({ length: 101 }, (_, i) => `ultrawide:text-[${i}px]`),
  // text size for rem
  ...Array.from({ length: 101 }, (_, i) => `text-[${i}rem]`),
  // text size for devices
  ...Array.from({ length: 101 }, (_, i) => `xs:text-[${i}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `mob:text-[${i}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `sm:text-[${i}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `md:text-[${i}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `lg:text-[${i}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xl:text-[${i}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `xxl:text-[${i}rem]`),
  ...Array.from({ length: 101 }, (_, i) => `ultrawide:text-[${i}rem]`),
  // text size for em
  ...Array.from({ length: 101 }, (_, i) => `text-[${i}em]`),
  // text size for devices
  ...Array.from({ length: 101 }, (_, i) => `xs:text-[${i}em]`),
  ...Array.from({ length: 101 }, (_, i) => `mob:text-[${i}em]`),
  ...Array.from({ length: 101 }, (_, i) => `sm:text-[${i}em]`),
  ...Array.from({ length: 101 }, (_, i) => `md:text-[${i}em]`),
  ...Array.from({ length: 101 }, (_, i) => `lg:text-[${i}em]`),
  ...Array.from({ length: 101 }, (_, i) => `xl:text-[${i}em]`),
  ...Array.from({ length: 101 }, (_, i) => `xxl:text-[${i}em]`),
  ...Array.from({ length: 101 }, (_, i) => `ultrawide:text-[${i}em]`),
  // dynamic arbitrary color classes
  ...Array.from({ length: 101 }, (_, i) => `text-color-[${i}]`),
  ...Array.from({ length: 101 }, (_, i) => `bg-color-[${i}]`),
  // line height
  ...Array.from({ length: 101 }, (_, i) => `line-height-[${i}]`),
  // line height for devices
  ...Array.from({ length: 101 }, (_, i) => `xs:line-height-[${i}]`),
  ...Array.from({ length: 101 }, (_, i) => `mob:line-height-[${i}]`),
  ...Array.from({ length: 101 }, (_, i) => `sm:line-height-[${i}]`),
  ...Array.from({ length: 101 }, (_, i) => `md:line-height-[${i}]`),
  ...Array.from({ length: 101 }, (_, i) => `lg:line-height-[${i}]`),
  ...Array.from({ length: 101 }, (_, i) => `xl:line-height-[${i}]`),
  ...Array.from({ length: 101 }, (_, i) => `xxl:line-height-[${i}]`),
  ...Array.from({ length: 101 }, (_, i) => `ultrawide:line-height-[${i}]`),
];
