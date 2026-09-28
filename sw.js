const CACHE = "oh-v28";
const PRECACHE = [
  "./manifest.json",
  "./assets/app.css?v=28",
  "./assets/app.js?v=28",
  "./assets/catalog.js?v=28",
  "./assets/icons/icon-192.png",
  "./assets/icons/icon-512.png",
];

self.addEventListener("install", (e) => {
  e.waitUntil(
    caches.open(CACHE).then((c) => c.addAll(PRECACHE).catch(() => {})).then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (e) => {
  e.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (e) => {
  const req = e.request;
  if (req.method !== "GET") return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  const path = url.pathname;
  if (path.endsWith(".php") || path.includes("api.php") || path.includes("nx.php")) return;
  if (req.mode === "navigate") {
    e.respondWith(fetch(req).catch(() => caches.match("./")));
    return;
  }

  const netFirst = () =>
    fetch(req).then((res) => {
      if (res && res.ok) {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(req, copy));
      }
      return res;
    }).catch(() => caches.match(req).then((hit) => hit || caches.match("./")));

  // JS/CSS must not stick on an old ?v= — network first, cache as fallback
  if (path.includes("/assets/") || path.endsWith(".js") || path.endsWith(".css")) {
    e.respondWith(netFirst());
    return;
  }

  e.respondWith(netFirst());
});
