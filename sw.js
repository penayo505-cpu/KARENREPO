const CACHE = 'vital-dent-shell-v1';
const ASSETS = ['./', './index.html', './styles.css', './app.js', './manifest.json', './icons/icon.svg', './icons/icon-192.png', './icons/icon-512.png'];
self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(ASSETS)));
});
self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k.startsWith('vital-dent-shell-') && k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  // Only public application files are cached; never clinical data or external resources.
  if (event.request.method !== 'GET' || url.origin !== self.location.origin) return;
  const allowed = ASSETS.map(path => new URL(path, self.registration.scope).pathname);
  if (!event.request.mode.includes('navigate') && !allowed.includes(url.pathname)) return;
  event.respondWith(caches.open(CACHE).then(async cache => {
    if (event.request.mode === 'navigate') {
      try { return await fetch(event.request); }
      catch { return await cache.match('./index.html'); }
    }
    return await cache.match(event.request, {ignoreSearch:true}) || fetch(event.request);
  }));
});
