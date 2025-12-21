const CACHE_NAME = "speedpage-cache-v1";

const BASE_PATH = "/yeni/"; // settings.php ile aynı olmalı

const URLsToCache = [
    BASE_PATH,
    BASE_PATH + "index.php",
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

// Fetch (cache-first)
self.addEventListener("fetch", event => {
    event.respondWith(
        caches.match(event.request).then(cached => {
            return cached || fetch(event.request);
        })
    );
});

