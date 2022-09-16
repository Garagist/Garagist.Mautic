const colors = require("tailwindcss/colors");
const plugin = require("tailwindcss/plugin");

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
            inherit: "inherit",
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
    plugins: [
        plugin(function ({ addUtilities }) {
            addUtilities({
                ".auto-grow-textarea": {
                    display: "grid",

                    "& > *": {
                        "grid-area": "1 / 1 / 2 / 2",
                        width: "100%",
                        display: "block",
                        padding: "14px !important",
                        "border-width": "2px",
                        font: "inherit",
                        "line-height": "1.4",
                    },

                    "& > span": {
                        "white-space": "pre-wrap",
                        visibility: "hidden",
                        "border-color": "transparent",
                        "border-style": "solid",
                    },

                    "& > textarea": {
                        resize: "none",
                        overflow: "hidden",
                    },
                },
            });
        }),
    ],
};
