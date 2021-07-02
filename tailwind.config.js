const colors = require("tailwindcss/colors");

module.exports = {
  mode: "jit",
  purge: ["Resources/Private/{Assets,Fusion}/**/*.{js,fusion,pcss}"],
  important: ".mautic",
  darkMode: false, // or 'media' or 'class'
  theme: {
    colors: {
      transparent: "transparent",
      current: "currentColor",
      black: colors.black,
      white: colors.white,
      gray: colors.coolGray,
      red: colors.red,
      yellow: colors.amber,
      green: colors.emerald,
    },
    fontFamily: {
      sans: [
        '"Noto Sans"',
        "sans-serif",
        '"Apple Color Emoji"',
        '"Segoe UI Emoji"',
        '"Segoe UI Symbol"',
        '"Noto Color Emoji"',
      ],
    },
  },
};
