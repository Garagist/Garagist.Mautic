import esbuild from "esbuild";

const watch = process.argv.includes("--watch");

/** @type {import("esbuild").BuildOptions} */
const options = {
    logLevel: "info",
    bundle: true,
    minify: !watch,
    sourcemap: false,
    target: "es2020",
    format: "iife",
    legalComments: "none",
    entryPoints: ["Resources/Private/Assets/Backend.js"],
    outfile: "Resources/Public/Backend/Scripts.js",
}


if (watch) {
    esbuild.context(options).then((ctx) => ctx.watch());
} else {
    esbuild.build(options);
}
