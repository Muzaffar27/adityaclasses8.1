import "./bootstrap";
import { createApp } from "vue";
import { createPinia } from "pinia";
import router from "./router";
import App from "./components/App.vue";
import "../sass/app.scss";
import { useAuthStore } from "./stores/auth";

document.documentElement.classList.add("theme-dark");

window.adityaPwaInstallPrompt = null;
window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    window.adityaPwaInstallPrompt = event;
    console.info("Aditya Classes PWA install prompt is available.");
    window.dispatchEvent(new Event("aditya-pwa-installable"));
});

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);

// Restore session on every page load
const auth = useAuthStore();
auth.fetchUser().finally(() => app.mount("#app"));

const canUseServiceWorker =
    "serviceWorker" in navigator &&
    (window.location.protocol === "https:" ||
        ["localhost", "127.0.0.1"].includes(window.location.hostname));

if (canUseServiceWorker) {
    window.addEventListener("load", () => {
        navigator.serviceWorker.register("/sw.js").catch((error) => {
            console.error("Service worker registration failed:", error);
        });
    });
}
