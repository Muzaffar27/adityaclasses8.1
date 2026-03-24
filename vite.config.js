import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import laravel from "laravel-vite-plugin";
import path from "path";

export default defineConfig({
    base: "/dev/",
    server: {
        host: "0.0.0.0",
        port: 5173,
        hmr: {
            host: "localhost",
        },
    },
    plugins: [
        vue(),
        laravel({
            input: "resources/js/app.js",
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            "@": path.resolve(__dirname, "resources/js"),
            vue: "vue/dist/vue.esm-bundler.js",
        },
    },
});
