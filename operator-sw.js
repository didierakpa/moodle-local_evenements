/* Service worker of the door app: the application shell stays available without a network.
 * Data requests (operator_api.php) are never cached: the app keeps its own offline list. */
var CACHE = 'evt-operator-v1';
var SHELL = ['operator.php', 'pwa/operator.js', 'pwa/operator.css', 'pwa/icon-192.png', 'pwa/apple-touch-icon.png'];

self.addEventListener('install', function(event) {
    event.waitUntil(caches.open(CACHE).then(function(cache) {
        return cache.addAll(SHELL.map(function(u) { return new Request(u, {cache: 'reload'}); }));
    }).then(function() { return self.skipWaiting(); }));
});

self.addEventListener('activate', function(event) {
    event.waitUntil(caches.keys().then(function(keys) {
        return Promise.all(keys.filter(function(k) { return k !== CACHE; }).map(function(k) { return caches.delete(k); }));
    }).then(function() { return self.clients.claim(); }));
});

self.addEventListener('fetch', function(event) {
    var req = event.request;
    var url = new URL(req.url);
    if (req.method !== 'GET' || url.pathname.indexOf('operator_api.php') !== -1) { return; }
    var inShell = url.pathname.indexOf('/pwa/') !== -1 || url.pathname.slice(-12) === 'operator.php';
    if (!inShell) { return; }
    // Network first, cache as fallback: the app updates itself when the network is there.
    event.respondWith(fetch(req).then(function(res) {
        var copy = res.clone();
        caches.open(CACHE).then(function(cache) { cache.put(url.pathname.slice(-12) === 'operator.php' ? 'operator.php' : req, copy); });
        return res;
    }).catch(function() {
        return caches.match(url.pathname.slice(-12) === 'operator.php' ? 'operator.php' : req, {ignoreSearch: true});
    }));
});
