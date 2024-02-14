self.addEventListener('install', (e) => {
  e.waitUntil(
    caches.open('redbox-telemetry').then((cache) => cache.addAll([
      '/',
    ])),
  );
});

self.addEventListener('fetch', (e) => {
  console.log(e.request.url);
  e.respondWith(
    caches.match(e.request).then((response) => response || fetch(e.request)),
  );
});