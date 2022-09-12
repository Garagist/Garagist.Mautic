const colors = require("tailwindcss/colors");

module.exports = {
    content: ["Resources/Private/{Assets,Fusion,Modules}/**/*.{js,fusion,pcss}"],
    important: ".mautic",
    theme: {
        colors: {
            transparent: "transparent",
            current: "currentColor",
            black: colors.black,
            white: colors.white,
            slate: colors.slate,
            red: colors.red,
            yellow: colors.amber,
            green: colors.emerald,
            neos: {
                "blue-light": "#39c6ff",
                blue: "#00b5ff",
                green: "#01a338",
                red: "#ff460d",
                lightest: "#adadad",
                lighter: "#5b5b5b",
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
