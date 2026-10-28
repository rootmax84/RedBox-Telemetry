self.addEventListener('install', (e) => {
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key === 'redbox-telemetry') {
                        return caches.delete(key);
                    }
                })
            );
        })
    );
});
