const CACHE_NAME = 'vardiya-sistemi-v1';
const CACHE_URLS = [
    '/',
    '/index.php',
    '/personel.php',
    '/izin.php',
    '/rapor.php',
    '/tercihler.php',
    '/style.css',
    '/manifest.json',
    'https://cdn.jsdelivr.net/npm/chart.js'
];

// Service Worker Kurulumu
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                return cache.addAll(CACHE_URLS);
            })
    );
});

// Service Worker Aktivasyonu
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

// Fetch İsteklerini Yönetme
self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                // Cache'de varsa cache'den döndür
                if (response) {
                    return response;
                }

                // Cache'de yoksa network'den al
                return fetch(event.request).then(
                    (response) => {
                        // Geçersiz yanıt ise direkt döndür
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }

                        // Yanıtı cache'e ekle
                        const responseToCache = response.clone();
                        caches.open(CACHE_NAME)
                            .then((cache) => {
                                cache.put(event.request, responseToCache);
                            });

                        return response;
                    }
                );
            })
    );
});

// Push Bildirimlerini Yönetme
self.addEventListener('push', (event) => {
    const options = {
        body: event.data.text(),
        icon: 'icons/icon-192x192.png',
        badge: 'icons/badge-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'Görüntüle',
                icon: 'icons/checkmark.png'
            },
            {
                action: 'close',
                title: 'Kapat',
                icon: 'icons/xmark.png'
            }
        ]
    };

    event.waitUntil(
        self.registration.showNotification('Vardiya Sistemi', options)
    );
}); 