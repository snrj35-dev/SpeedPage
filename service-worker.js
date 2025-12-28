const CACHE_NAME = "speedpage-cache-v1";

const BASE_PATH = "/"; // settings.php ile aynı olmalı

const URLsToCache = [
    // Static assets only — do not cache dynamic HTML pages like index.php
    BASE_PATH + "cdn/css/style.css",
    BASE_PATH + "cdn/css/bootstrap.min.css",
    BASE_PATH + "cdn/js/bootstrap.bundle.min.js",
    BASE_PATH + "cdn/js/router.js",
    BASE_PATH + "cdn/js/dark.js",
    BASE_PATH + "cdn/js/lang.js",
    BASE_PATH + "manifest.json"
];

// Install
self.addEventListener("install", event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => cache.addAll(URLsToCache))
    );
});

// Clean up old caches on activation
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
        ))
    );
});

// Fetch (cache-first)
self.addEventListener("fetch", event => {
    // Use network-first for navigation/HTML requests so dynamic pages reflect session state
    const isNavigate = event.request.mode === 'navigate' || (event.request.headers.get('accept') || '').includes('text/html');

    if (isNavigate) {
        event.respondWith(
            fetch(event.request, { credentials: 'include' })
                .then(response => {
                    // Update cache for fallback, but return network response
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, response.clone()));
                    return response;
                })
                .catch(() => caches.match(event.request).then(cached => cached || caches.match(BASE_PATH + 'index.php'))
            )
        );
        return;
    }

    // For other requests (static assets) use cache-first
    event.respondWith(
        caches.match(event.request).then(cached => cached || fetch(event.request))
    );
});

