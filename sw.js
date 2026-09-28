const CACHE = "oh-v31";
const PRECACHE = [
  "./manifest.json",
  "./assets/app.css?v=30",
  "./assets/app.js?v=30",
  "./assets/catalog.js?v=30",
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

/* ---------------- Web Push notifications ---------------- */

self.addEventListener("push", (event) => {
  let data = {};
  if (event.data) {
    try { data = event.data.json(); } catch (e) {
      try { data = { title: "OfferHub", body: event.data.text() }; } catch (e2) { data = {}; }
    }
  }
  const title = data.title || "OfferHub";
  const url = data.url || "./";
  const options = {
    body: data.body || "",
    icon: "./assets/icons/icon-192.png",
    badge: "./assets/icons/icon-192.png",
    tag: data.tag || "oh-notify",
    renotify: true,
    vibrate: [90, 50, 90],
    timestamp: Date.now(),
    data: { url },
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();
  const url = (event.notification.data && event.notification.data.url) || "./";
  event.waitUntil(
    (async () => {
      const target = new URL(url, self.location.origin);
      const list = await clients.matchAll({ type: "window", includeUncontrolled: true });
      for (const c of list) {
        try {
          const cu = new URL(c.url);
          if (cu.origin === target.origin && "focus" in c) {
            await c.focus();
            if (cu.pathname !== target.pathname && "navigate" in c) {
              try { await c.navigate(url); } catch (e) {}
            }
            return;
          }
        } catch (e) {}
      }
      if (clients.openWindow) await clients.openWindow(url);
    })()
  );
});
