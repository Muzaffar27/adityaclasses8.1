import "./bootstrap";
import { createApp } from "vue";
import { createPinia } from "pinia";
import router from "./router";
import App from "./components/App.vue";
import "../sass/app.scss";
import { useAuthStore } from "./stores/auth";

document.documentElement.classList.add("theme-dark");

function displayMode() {
    if (window.matchMedia?.("(display-mode: standalone)")?.matches) {
        return "standalone";
    }

    if (window.navigator.standalone === true) {
        return "ios-standalone";
    }

    return "browser";
}

function serializeError(error) {
    if (!error) return {};

    if (typeof error === "string") {
        return { message: error };
    }

    return {
        message: error.message || String(error),
        stack: error.stack || "",
    };
}

function reportClientError(type, error, extra = {}) {
    const serialized = serializeError(error);
    const buildAsset = document.querySelector('script[type="module"]')?.src || "";
    const buildAssetOrigin = buildAsset ? new URL(buildAsset, window.location.href).origin : "";
    const payload = {
        type,
        message: serialized.message || extra.message || "",
        stack: serialized.stack || extra.stack || "",
        url: window.location.href,
        source: extra.source || "",
        userAgent: window.navigator.userAgent,
        displayMode: displayMode(),
        serviceWorker: {
            supported: "serviceWorker" in navigator,
            controlled: Boolean(navigator.serviceWorker?.controller),
        },
        buildAsset,
        assetOriginMismatch: Boolean(buildAssetOrigin && buildAssetOrigin !== window.location.origin),
        ...extra,
    };

    const key = `${payload.type}:${payload.message}:${payload.url}`;
    window.__adityaReportedErrors = window.__adityaReportedErrors || new Set();

    if (window.__adityaReportedErrors.has(key)) return;
    window.__adityaReportedErrors.add(key);

    try {
        const body = JSON.stringify(payload);

        if (navigator.sendBeacon) {
            navigator.sendBeacon("/api/client-error", new Blob([body], { type: "application/json" }));
            return;
        }

        fetch("/api/client-error", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body,
            keepalive: true,
        }).catch(() => {});
    } catch (reportingError) {
        console.error("Client error reporting failed:", reportingError);
    }
}

window.reportAdityaClientError = reportClientError;

window.adityaPwaInstallPrompt = null;
window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    window.adityaPwaInstallPrompt = event;
    window.dispatchEvent(new Event("aditya-pwa-installable"));
});

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);
app.config.errorHandler = (error) => {
    console.error("Vue startup error:", error);
    reportClientError("vue-error", error);
};

window.addEventListener("error", (event) => {
    console.error("Window error:", event.error || event.message);
    reportClientError("window-error", event.error || event.message, {
        source: event.filename || "",
        line: event.lineno,
        column: event.colno,
    });
});

window.addEventListener("unhandledrejection", (event) => {
    console.error("Unhandled promise rejection:", event.reason);
    reportClientError("unhandled-rejection", event.reason);
});

// Restore session on every page load
const auth = useAuthStore();
app.mount("#app");
window.__ADITYA_APP_MOUNTED = true;

auth.fetchUser().catch((error) => {
    reportClientError("initial-user-fetch-failed", error);
});

const canUseServiceWorker =
    "serviceWorker" in navigator &&
    (window.location.protocol === "https:" ||
        ["localhost", "127.0.0.1"].includes(window.location.hostname));

if (canUseServiceWorker) {
    let refreshing = false;

    navigator.serviceWorker.addEventListener("controllerchange", () => {
        if (refreshing) return;

        refreshing = true;
        window.location.reload();
    });

    window.addEventListener("load", () => {
        navigator.serviceWorker
            .register("/sw.js", { updateViaCache: "none" })
            .then((registration) => {
                registration.update();

                if (registration.waiting) {
                    registration.waiting.postMessage({ type: "SKIP_WAITING" });
                }

                registration.addEventListener("updatefound", () => {
                    const worker = registration.installing;
                    if (!worker) return;

                    worker.addEventListener("statechange", () => {
                        if (worker.state === "installed" && navigator.serviceWorker.controller) {
                            worker.postMessage({ type: "SKIP_WAITING" });
                        }
                    });
                });
            })
            .catch((error) => {
                console.error("Service worker registration failed:", error);
                reportClientError("service-worker-registration-failed", error);
            });
    });
}
