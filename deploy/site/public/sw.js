// Tennis Records service worker: makes the site installable and shows a friendly page when offline.
// Pages always come fresh from the server (scores change often); only design files are cached.
const VERSION = 'tr-v6';
const OFFLINE_URL = '/offline.html';
const PRECACHE = [OFFLINE_URL, '/css/theme.css?v=6', '/icons/icon-192.png?v=2', '/icons/icon-512.png?v=2', '/favicon.ico'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(VERSION).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;                       // forms (saving scores) always go to the server

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;            // fonts etc.: let the browser handle them

    // Pages: always from the network; if there is no internet, show the offline page
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    // Design files (CSS, JS, icons, pictures): use the saved copy, refresh it in the background
    if (/^\/(build|css|icons|storage)\//.test(url.pathname) || url.pathname === '/favicon.ico') {
        event.respondWith(
            caches.open(VERSION).then((cache) =>
                cache.match(request).then((cached) => {
                    const network = fetch(request).then((response) => {
                        if (response.ok) cache.put(request, response.clone());
                        return response;
                    }).catch(() => cached);
                    return cached || network;
                })
            )
        );
    }
});