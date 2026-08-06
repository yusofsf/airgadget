const CACHE_NAME = 'airgadget-pwa-v1';
const CORE_ASSETS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/airgadget-logo.png',
    '/pwa/icon-192.png',
    '/pwa/icon-512.png',
    '/pwa/icon-maskable-192.png',
    '/pwa/icon-maskable-512.png',
    '/pwa/apple-touch-icon.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(CORE_ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((names) => Promise.all(names.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
        return;
    }

    const isStaticAsset = url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/pwa/')
        || url.pathname === '/airgadget-logo.png'
        || url.pathname === '/manifest.webmanifest';

    if (!isStaticAsset) return;

    event.respondWith(
        caches.match(request).then((cached) => cached || fetch(request).then((response) => {
            if (response.ok) {
                const copy = response.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
            }
            return response;
        })),
    );
});
