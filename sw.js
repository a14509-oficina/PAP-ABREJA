const CACHE = 'abreja-v2';
const STATIC = [
  '/',
  '/style.css',
  '/app.js',
  '/manifest.json',
  '/logo.png',
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(STATIC))
  );
});

self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  if (url.pathname.startsWith('/api/')) {
    e.respondWith(fetch(e.request));
    return;
  }
  e.respondWith(
    caches.match(e.request).then(res => res || fetch(e.request).then(r => {
      if (!url.pathname.includes('.php')) {
        const resp = r.clone();
        caches.open(CACHE).then(c => c.put(e.request, resp));
      }
      return r;
    }))
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(keys.map(k => caches.delete(k))))
  );
});
