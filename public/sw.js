const CACHE_VERSION = "aditya-classes-v5";
const APP_SHELL_CACHE = `${CACHE_VERSION}-shell`;
const RUNTIME_CACHE = `${CACHE_VERSION}-runtime`;

const APP_SHELL_URLS = [
    "/",
    "/manifest.webmanifest",
    "/logo.png",
    "/menu_logo.png",
    "/pwa-icons/mobile-icon-192.png",
    "/pwa-icons/mobile-icon-512.png",
    "/pwa-icons/mobile-maskable-512.png",
    "/pwa-icons/apple-touch-icon.png",
    "/pwa-screenshots/wide.png",
    "/favicon.ico",
];

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches
            .open(APP_SHELL_CACHE)
            .then((cache) => cache.addAll(APP_SHELL_URLS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((cacheNames) =>
                Promise.all(
                    cacheNames
                        .filter((cacheName) => !cacheName.startsWith(CACHE_VERSION))
                        .map((cacheName) => caches.delete(cacheName))
                )
            )
            .then(() => self.clients.claim())
    );
});

self.addEventListener("fetch", (event) => {
    const { request } = event;

    if (request.method !== "GET") {
        return;
    }

    const url = new URL(request.url);

    if (request.mode === "navigate") {
        event.respondWith(networkFirst(request, "/"));
        return;
    }

    if (url.origin === self.location.origin && url.pathname.startsWith("/api/")) {
        event.respondWith(networkFirst(request));
        return;
    }

    if (url.origin === self.location.origin) {
        event.respondWith(staleWhileRevalidate(request));
    }
});

async function networkFirst(request, fallbackUrl = null) {
    try {
        const response = await fetch(request);
        const cache = await caches.open(RUNTIME_CACHE);
        cache.put(request, response.clone());
        return response;
    } catch (error) {
        const cachedResponse = await caches.match(request);

        if (cachedResponse) {
            return cachedResponse;
        }

        if (fallbackUrl) {
            return caches.match(fallbackUrl);
        }

        throw error;
    }
}

async function staleWhileRevalidate(request) {
    const cache = await caches.open(RUNTIME_CACHE);
    const cachedResponse = await cache.match(request);

    const fetchPromise = fetch(request)
        .then((networkResponse) => {
            cache.put(request, networkResponse.clone());
            return networkResponse;
        })
        .catch(() => cachedResponse);

    return cachedResponse || fetchPromise;
}
