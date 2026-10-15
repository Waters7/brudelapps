// Intentionally cache only this public allowlist. Never cache HTML sessions,
// Livewire requests, private documents, uploads, or authentication responses.
// rev Fc9CvmnUP8FdSsuhnTzcpV9YXPoudZlNBKu4EdIeR5keIVatfrAsStXHgZioVZC4qH_XCYJhlCY02pI1m1g7qA
const CACHE = 'brudel-public-v1';
const PUBLIC = ['/offline.html', '/brand/icon-192x192.png', '/brand/icon-512x512.png', '/brand/logo-mark.svg'];
self.addEventListener('install', event => { event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(PUBLIC)).then(() => self.skipWaiting())); });
self.addEventListener('activate', event => { event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('brudel-public-') && key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', event => {
    const req=event.request, url=new URL(req.url);
    if (req.method !== 'GET' || url.origin !== self.location.origin) return;
    if (req.mode === 'navigate') { event.respondWith(fetch(req).catch(() => caches.match('/offline.html'))); return; }
    if (PUBLIC.includes(url.pathname) && !url.search) event.respondWith(caches.match(req).then(cached => cached || fetch(req)));
});
