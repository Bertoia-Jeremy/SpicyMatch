// tailwind.config.js
module.exports = {
  content: [
    './templates/**/*.html.twig',
    './assets/**/*.{js,jsx,ts,tsx,vue}',
  ],
  theme: {
    extend: {
      colors: {
        'jaune': '#FFFF00',
        'ocre': '#dfaf2c',
        'orange': '#FF4500',
        'vermillion': '#d9381e',
        'rouge': '#FF0000',
        'pourpre': '#800080',
        'violet': '#EE82EE',
        'indigo': '#4B0082',
        'bleu': '#0000FF',
        'turquoise': '#25fde9',
        'vert': '#008000',
        'chartreuse': '#7FFF00',
      },
    },
  },
  plugins: [],
};
