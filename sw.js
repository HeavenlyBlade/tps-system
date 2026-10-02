// TPS System Service Worker
// Uses relative paths so it works at any mount point (/ in production, /tps-system/ locally).
const CACHE_NAME = 'tps-v5';

// Derive the base path from the SW script location so asset URLs resolve correctly.
const SW_BASE = self.location.pathname.replace(/\/sw\.js$/, '');

const STATIC_CACHE = [
  SW_BASE + '/assets/style.css',
  SW_BASE + '/bootstrap/css/bootstrap.min.css',
  SW_BASE + '/bootstrap/css/bootstrap-grid.min.css',
  SW_BASE + '/bootstrap/fonts/bootstrap-icons.css',
  SW_BASE + '/bootstrap/js/bootstrap.bundle.min.js',
];

// Install — cache only static assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_CACHE))
      .then(() => self.skipWaiting())
  );
});

// Activate — purge old caches and claim clients
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

// Fetch strategy
self.addEventListener('fetch', event => {
  // Only handle GET requests
  if (event.request.method !== 'GET') return;

  const url = new URL(event.request.url);

  // Never cache PHP files or AJAX calls — always network
  if (url.pathname.endsWith('.php') || url.pathname.includes('/ajax/')) {
    event.respondWith(
      fetch(event.request).catch(() =>
        new Response(JSON.stringify({ error: 'Offline' }), {
          status: 503,
          headers: { 'Content-Type': 'application/json' },
        })
      )
    );
    return;
  }

  // Cache-first for static assets (CSS, JS, fonts, images, video)
  const isStatic = ['.css', '.js', '.svg', '.png', '.jpg', '.jpeg', '.webp', '.mp4', '.woff', '.woff2']
    .some(ext => url.pathname.endsWith(ext));

  if (isStatic) {
    event.respondWith(
      caches.match(event.request).then(cached => {
        if (cached) return cached;
        return fetch(event.request).then(response => {
          if (response.status === 200) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
          }
          return response;
        });
      })
    );
    return;
  }

  // Everything else — network only
  event.respondWith(fetch(event.request));
});
