const CACHE_NAME = 'plt-translator-cache-v1';
const ASSETS_TO_CACHE = [
  './',
  'PLT_MainPage.php',
  'TranslationPage.php',
  'plt-main.css',
  'admin.css',
  'Logo.png'
];

// Install event: cache assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('Service Worker: Caching assets');
        return Promise.all(
          ASSETS_TO_CACHE.map((url) => {
            return cache.add(url).catch((error) => {
              console.error(`Service Worker: Failed to cache ${url}:`, error);
            });
          })
        );
      })
  );
});

// Fetch event: serve cached assets if available
self.addEventListener('fetch', (event) => {
  event.respondWith(
    caches.match(event.request)
      .then((response) => {
        // Cache hit - return response
        if (response) {
          return response;
        }
        return fetch(event.request);
      })
  );
});

// Activate event: clean up old caches (optional but good practice)
self.addEventListener('activate', (event) => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});
