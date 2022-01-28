const colors = require("tailwindcss/colors");

module.exports = {
    content: ["Resources/Private/{Assets,Fusion,Modules}/**/*.{js,fusion,pcss}"],
    important: ".mautic",
    darkMode: false,
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
            neos: {
                blue: "#00b5ff",
                green: "#01a338",
                red: "#ff460d",
                light: "#3f3f3f",
                DEFAULT: "#323232",
                dark: "#222222",
                darker: "#141414",
            },
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
