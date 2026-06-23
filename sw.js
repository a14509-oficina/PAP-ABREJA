const CACHE = 'abreja-v1';
const urls = [
  '/',
  '/index.php',
  '/style.css',
  '/app.js',
  '/manifest.json',
  '/logo.png',
  '/includes/config.php',
  '/includes/db.php',
  '/includes/auth.php',
  '/includes/helpers.php',
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(urls))
  );
});

self.addEventListener('fetch', e => {
  e.respondWith(
    caches.match(e.request).then(res => res || fetch(e.request))
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
  );
});
