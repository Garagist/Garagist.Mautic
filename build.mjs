import esbuild from "esbuild";
import extensibilityMap from "@neos-project/neos-ui-extensibility/extensibilityMap.json" assert { type: "json" };

const watch = process.argv.includes("--watch");

/** @type {import("esbuild").BuildOptions} */
const globalOptions = {
    logLevel: "info",
    bundle: true,
    minify: !watch,
    sourcemap: false,
    target: "es2020",
    format: "iife",
    legalComments: "none",
}

const editorOptions = {
    ...globalOptions,
    entryPoints: { Plugin: "Resources/Private/Editor/manifest.js" },
    outdir: "Resources/Public/Editor",
    alias: extensibilityMap,
    loader: { '.js': 'jsx' },
};

const backendOptions = {
    ...globalOptions,
    entryPoints: ["Resources/Private/Assets/Backend.js"],
    outfile: "Resources/Public/Backend/Scripts.js",
}


if (watch) {
    esbuild.context(editorOptions).then((ctx) => ctx.watch());
    esbuild.context(backendOptions).then((ctx) => ctx.watch());
} else {
    esbuild.build(editorOptions);
    esbuild.build(backendOptions);
}
