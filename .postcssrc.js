// cssnano and svgo optimisation has some issues with pleeease-filters
module.exports = {
    plugins: {
        "postcss-import": true,
        "tailwindcss/nesting": true,
        tailwindcss: true,
        autoprefixer: true,
        cssnano: {
            preset: ["default", { discardComments: { removeAll: true }, svgo: false }],
        },
    },
};
