// Lets the app work with a weak connection by saving key files on the device.
const CACHE_NAME = 'imali-smart-shell-v1';
const SHELL = ['index.php', 'css/variables.css', 'css/global.css', 'css/layout.css', 'css/components.css', 'css/responsive.css', 'css/landing.css', 'js/app.js', 'manifest.webmanifest'];
self.addEventListener('install', (event) => { event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting())); });
self.addEventListener('activate', (event) => { event.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', (event) => { const url = new URL(event.request.url); if (event.request.method !== 'GET' || url.pathname.includes('/api/')) return; event.respondWith(fetch(event.request).catch(() => caches.match(event.request))); });
