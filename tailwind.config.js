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
                green: "var(--green)",
                orange: "var(--orange)",
                red: "var(--warning)",
                blue: "var(--blue)",
                "blue-light": "var(--blueLight)",
                "blue-dark": "var(--blueDark)",

                "subtle-light": "var(--textSubtleLight)",
                subtle: "var(--textSubtle)",

                "gray-lighter": "var(--grayLighter)",
                "gray-light": "var(--grayLight)",
                "gray-medium": "var(--grayMedium)",
                "gray-dark": "var(--grayDark)",
                "gray-darker": "var(--grayDarker)",
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
