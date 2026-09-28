(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const app = $("#app");

  const state = {
    token: localStorage.getItem("oh_token") || "",
    user: null,
    settings: null,
    operators: [],
    bills: [],
    offers: [],
    methods: [],
    orders: [],
    payments: [],
    notes: [],
    unread: 0,
    page: "home",
    op: "all",
    type: "all",
    q: "",
    cat: "all",
    overlay: null,
    billId: "",
    wdId: "",
    busy: false,
    lastNote: "",
    splash: true,
    isAdmin: false,
  };
  const BILL_CATALOG = [
    { id: "desco", name: "DESCO", name_bn: "ডেসকো", short: "DESCO", type: "electricity", color: "#0f766e", hint: "কাস্টমার নম্বর দিন", status: "active" },
    { id: "dpdc", name: "DPDC", name_bn: "ডিপিডিসি", short: "DPDC", type: "electricity", color: "#1d4ed8", hint: "অ্যাকাউন্ট নম্বর দিন", status: "active" },
    { id: "nesco", name: "NESCO", name_bn: "নেসকো", short: "NESCO", type: "electricity", color: "#047857", hint: "কাস্টমার নম্বর দিন", status: "active" },
    { id: "bpdb", name: "BPDB", name_bn: "বিপিডিবি", short: "BPDB", type: "electricity", color: "#b45309", hint: "কাস্টমার নম্বর দিন", status: "active" },
    { id: "breb", name: "BREB", name_bn: "পল্লী বিদ্যুৎ", short: "BREB", type: "electricity", color: "#15803d", hint: "কাস্টমার নম্বর দিন", status: "active" },
    { id: "titas", name: "Titas Gas", name_bn: "তিতাস গ্যাস", short: "Titas", type: "gas", color: "#c2410c", hint: "কাস্টমার কোড দিন", status: "active" },
    { id: "wasa", name: "WASA", name_bn: "ওয়াসা", short: "WASA", type: "water", color: "#0369a1", hint: "কাস্টমার আইডি দিন", status: "active" },
    { id: "inet", name: "Internet", name_bn: "ইন্টারনেট বিল", short: "NET", type: "internet", color: "#7c3aed", hint: "ইউজার আইডি / অ্যাকাউন্ট", status: "active" },
  ];
  if (!state.bills.length) state.bills = BILL_CATALOG;
  const OH_CATALOG = [];
  if (Array.isArray(window.__OH_OFFERS) && window.__OH_OFFERS.length) {
    state.offers = window.__OH_OFFERS;
  } else if (OH_CATALOG.length) {
    state.offers = OH_CATALOG;
    window.__OH_OFFERS = OH_CATALOG;
  }

  const icons = {
    home: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z"/></svg>',
    offers: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M8 4v16"/></svg>',
    wallet: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="14" rx="2"/><path d="M16 12h4v4h-4a2 2 0 0 1 0-4z"/></svg>',
    hist: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    user: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="3.5"/><path d="M5 19a7 7 0 0 1 14 0"/></svg>',
    bell: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9a6 6 0 1 1 12 0c0 7 3 7 3 9H3c0-2 3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/></svg>',
    logo: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3z" fill="#fff" opacity=".9"/><circle cx="12" cy="12" r="3.2" fill="#0f766e"/></svg>',
    bill: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>',
    withdraw: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v12"/><path d="M8 12l4 4 4-4"/><path d="M5 20h14"/></svg>',
    send: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2 11 13"/><path d="M22 2 15 22 11 13 2 9z"/></svg>',
    support: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>',
  };

  function money(n) {
    const x = Number(n) || 0;
    return x.toLocaleString("en-BD", { maximumFractionDigits: 0 });
  }
  function offerPrices(o) {
    const sell = Number(o?.price || 0);
    let drv = Number(o?.drive_price || 0);
    let reg = Number(o?.regular_price || 0);
    let com = Number(o?.commission || 0);
    if (o?.type === "drive") {
      if (drv <= 0) drv = sell;
      if (reg <= 0) reg = drv + com;
      com = Math.max(0, reg - drv);
    } else if (reg <= 0) {
      reg = sell;
    }
    return { reg, drv, com, sell: o?.type === "drive" ? (drv || sell) : (reg || sell) };
  }
  function esc(s) {
    return String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }
  function opOf(id) {
    return state.operators.find((o) => o.id === id)
      || (state.bills || []).find((o) => o.id === id)
      || (state.methods || []).find((o) => o.id === id)
      || { name_bn: id, short: (id || "").toString().toUpperCase(), name: id, color: "#0f766e" };
  }
  function orderHead(o) {
    const h = offerHead(o, true);
    if (h) return h;
    return o.title || labelKind(o.kind || o.type) || "অর্ডার";
  }
  function offerHead(o, full) {
    const op = opOf(o.operator);
    const name = full ? (op.name_bn || op.short) : (op.short || op.name_bn);
    return [name, o.volume, o.validity].filter((x) => String(x || "").trim()).join(" • ");
  }
  function idem() {
    return Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
  }

  /* ---------------- Web Push notifications ---------------- */
  const PUSH_ASK_KEY = "oh_push_asked_v1";
  // Keep the install prompt alive until the user explicitly uses it. Chromium only
  // exposes beforeinstallprompt during the current page lifetime.
  let deferredInstallPrompt = null;
  window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    showInstallButton();
  });
  window.addEventListener("appinstalled", () => {
    deferredInstallPrompt = null;
    const b = $("#oh-install");
    if (b) b.remove();
  });
  async function installPwa() {
    if (!deferredInstallPrompt) return;
    deferredInstallPrompt.prompt();
    try { await deferredInstallPrompt.userChoice; } catch (e) {}
    deferredInstallPrompt = null;
    const b = $("#oh-install");
    if (b) b.remove();
  }
  function showInstallButton() {
    if (!deferredInstallPrompt || $("#oh-install")) return;
    const b = document.createElement("button");
    b.id = "oh-install";
    b.className = "pwa-install";
    b.type = "button";
    b.textContent = "অ্যাপ ইনস্টল করুন";
    b.addEventListener("click", installPwa);
    document.body.appendChild(b);
  }
  function urlB64ToUint8Array(base64String) {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
    const raw = atob(base64);
    const arr = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
    return arr;
  }
  function pushSupported() {
    return ("serviceWorker" in navigator) && ("PushManager" in window) && ("Notification" in window);
  }
  async function isBraveBrowser() {
    try { return !!(navigator.brave && (await navigator.brave.isBrave())); } catch (e) { return false; }
  }
  // Returns { ok:true } on success, otherwise { ok:false, reason } where reason is one of
  // unsupported | insecure | permission | nokey | subscribe.
  async function pushEnsureSubscribed() {
    try {
      if (!pushSupported()) return { ok: false, reason: "unsupported" };
      if (!window.isSecureContext) return { ok: false, reason: "insecure" };
      if (Notification.permission !== "granted") return { ok: false, reason: "permission" };
      let reg;
      try { reg = await navigator.serviceWorker.ready; } catch (e) { reg = null; }
      if (!reg) reg = await navigator.serviceWorker.register("sw.js?v=30").catch(() => null);
      if (!reg || !reg.pushManager) return { ok: false, reason: "unsupported" };
      const keyRes = await api("push_key", {}, "GET");
      if (!keyRes.ok || !keyRes.key) return { ok: false, reason: "nokey" };
      let sub = await reg.pushManager.getSubscription();
      if (!sub) {
        try {
          sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlB64ToUint8Array(keyRes.key),
          });
        } catch (e) {
          // Chromium forks (Brave / plain Chromium / Mi Browser) ship without Google's
          // FCM push endpoint enabled, so subscribe() rejects here even after permission
          // was granted. Surface it instead of failing silently.
          return { ok: false, reason: "subscribe" };
        }
      }
      if (!sub) return { ok: false, reason: "subscribe" };
      if (!state.token) return { ok: true };
      const j = sub.toJSON();
      const r = await api("push_subscribe", { endpoint: j.endpoint, keys: j.keys });
      if (!r || !r.ok) return { ok: false, reason: "subscribe" };
      return { ok: true };
    } catch (e) {
      return { ok: false, reason: "subscribe" };
    }
  }
  async function pushFailMessage(reason) {
    if (reason === "insecure") return "নোটিফিকেশনের জন্য HTTPS (সিকিউর) সংযোগ দরকার।";
    if (reason === "unsupported") return "এই ব্রাউজারে পুশ নোটিফিকেশন সাপোর্ট করে না। Chrome ব্যবহার করে দেখুন।";
    if (reason === "permission") return "নোটিফিকেশন অনুমতি দেওয়া হয়নি। ব্রাউজার সেটিংস থেকে অনুমতি দিন।";
    if (reason === "nokey") return "সার্ভারে পুশ কনফিগার করা হয়নি। পরে চেষ্টা করুন।";
    if (reason === "subscribe") {
      if (await isBraveBrowser()) {
        return "Brave-তে পুশ চালু করতে: Settings → Privacy and security → 'Use Google services for push messaging' অন করে ব্রাউজার রিস্টার্ট করুন, তারপর আবার চেষ্টা করুন।";
      }
      return "ব্রাউজারে Google পুশ সার্ভিস নিষ্ক্রিয় থাকায় নোটিফিকেশন চালু করা যায়নি। ব্রাউজার সেটিংস থেকে পুশ/Google সার্ভিস চালু করুন অথবা Chrome ব্যবহার করুন।";
    }
    return "নোটিফিকেশন চালু করা যায়নি।";
  }
  // Central "turn on notifications" flow reused by the intro sheet and the account toggle.
  function showPermissionHelp() {
    overlay(`<div class="push-ask"><div class="push-ico">${icons.bell}</div><h3>নোটিফিকেশন ব্লক করা আছে</h3><p class="hint">ব্রাউজার একবার ব্লক করলে ওয়েবসাইট নিজে থেকে আবার Allow ডায়ালগ খুলতে পারে না। তবে এখান থেকেই সহজে চালু করতে পারবেন।</p><div class="permission-steps"><b>Brave / Android</b><br>১. উপরের অ্যাড্রেস বারের বাম পাশে ⓘ বা সাইট আইকনে চাপুন<br>২. <b>Permissions / Site settings</b> খুলুন<br>৩. <b>Notifications → Allow</b> নির্বাচন করুন<br>৪. এই পেজে ফিরে এসে নিচের বাটনে চাপুন</div><button class="btn block" type="button" data-reload-permission>আমি Allow করেছি — আবার চেষ্টা করুন</button><button class="btn ghost block" style="margin-top:8px" type="button" data-push="no">বন্ধ</button></div>`);
  }
  async function pushEnable() {
    if (!pushSupported()) { toast(await pushFailMessage("unsupported"), "bad"); return false; }
    if (!window.isSecureContext) { toast(await pushFailMessage("insecure"), "bad"); return false; }
    let perm = Notification.permission;
    if (perm === "default") {
      try {
        // Some engines still use the legacy callback form; support both.
        perm = await new Promise((resolve) => {
          const p = Notification.requestPermission(resolve);
          if (p && typeof p.then === "function") p.then(resolve);
        });
      } catch (e) { perm = Notification.permission; }
    }
    localStorage.setItem(PUSH_ASK_KEY, "1");
    if (perm === "denied") { showPermissionHelp(); return false; }
    if (perm !== "granted") { toast(await pushFailMessage("permission"), ""); return false; }
    const res = await pushEnsureSubscribed();
    if (res.ok) { toast("নোটিফিকেশন চালু হয়েছে", "ok"); return true; }
    toast(await pushFailMessage(res.reason), "bad");
    return false;
  }
  function pushAskSheet() {
    return `
      <div class="handle"></div>
      <div class="push-ask">
        <div class="push-ico">${icons.bell}</div>
        <h3>নোটিফিকেশন চালু করুন</h3>
        <p class="hint">অফার কনফার্ম, ব্যালেন্স আপডেট বা যেকোনো গুরুত্বপূর্ণ আপডেট সাথে সাথে জানতে পারবেন — অ্যাপ বন্ধ থাকলেও।</p>
        <button class="btn block" type="button" data-push="yes">নোটিফিকেশন চালু করুন</button>
        <button class="btn ghost block" style="margin-top:8px" type="button" data-push="no">এখন না</button>
      </div>`;
  }
  function pushMaybeAsk() {
    if (!pushSupported()) return;
    if (Notification.permission === "granted") { pushEnsureSubscribed(); return; }
    if (Notification.permission === "denied") { localStorage.setItem(PUSH_ASK_KEY, "1"); showPermissionHelp(); return; }
    if (localStorage.getItem(PUSH_ASK_KEY)) return;
    overlay(pushAskSheet());
  }

  async function api(action, data = {}, method = "POST") {
    const opt = {
      method,
      cache: "no-store",
      headers: { "Content-Type": "application/json", "X-Token": state.token, Authorization: "Bearer " + state.token },
    };
    if (method !== "GET") opt.body = JSON.stringify({ ...(data || {}), action });
    let res;
    try {
      res = await fetch("api.php?action=" + encodeURIComponent(action), opt);
    } catch (e) {
      return { ok: false, error: "সার্ভার উত্তর পাওয়া যায়নি" };
    }
    let j = {};
    try { j = JSON.parse(await res.text()); } catch (e) { j = { ok: false, error: "সার্ভার উত্তর পাওয়া যায়নি" }; }
    if (res.status === 401 && state.token && action !== "login" && action !== "push_subscribe") {
      state.token = "";
      state.isAdmin = false;
      localStorage.removeItem("oh_token");
      localStorage.removeItem("oh_at");
      state.user = null;
      closeOverlay();
      render();
      toast("সেশন শেষ হয়েছে, আবার লগইন করুন", "bad");
    }
    return j;
  }

  function toast(msg, kind = "") {
    $$(".toast").forEach((t) => t.remove());
    const el = document.createElement("div");
    el.className = "toast " + kind;
    el.textContent = msg;
    document.body.appendChild(el);
    const ms = Math.min(9000, Math.max(2800, String(msg).length * 80));
    setTimeout(() => el.remove(), ms);
  }

  function copy(text) {
    const t = String(text || "");
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(t).then(() => toast("কপি হয়েছে", "ok")).catch(fb);
    } else fb();
    function fb() {
      const ta = document.createElement("textarea");
      ta.value = t;
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand("copy"); toast("কপি হয়েছে", "ok"); } catch (e) { toast("কপি হয়নি", "bad"); }
      ta.remove();
    }
  }

  function isAdmin() {
    return !!(state.user && state.user.role === "admin");
  }
  function fmtClock(d = new Date()) {
    let h = d.getHours();
    const ap = h >= 12 ? "PM" : "AM";
    h = h % 12 || 12;
    const m = String(d.getMinutes()).padStart(2, "0");
    return h + ":" + m + ap;
  }
  function tickerBar() {
    const msg = String(state.settings?.ticker || state.settings?.notice || "নামাজের সময় · আপডেট এখানে দেখাবে").trim();
    return `<div class="ticker" aria-label="সময় ও নোটিশ">
      <time class="now" id="liveclk">${esc(fmtClock())}</time>
      <div class="mq"><div class="mq-track"><span>${esc(msg)}</span><span>${esc(msg)}</span></div></div>
    </div>`;
  }
  function mountNx(show) {
    let f = document.getElementById("nxembed");
    if (!show) {
      document.body.classList.remove("nx-on");
      if (f) f.classList.remove("on");
      return;
    }
    document.body.classList.add("nx-on");
    if (!f) {
      f = document.createElement("iframe");
      f.id = "nxembed";
      f.title = "এডমিন প্যানেল";
      document.body.appendChild(f);
    }
    if (f.getAttribute("src") !== "nx.php?v=25") f.src = "nx.php?v=25";
    f.classList.add("on");
  }

  function goto(page) {
    if (page === "admin" && !isAdmin()) page = "account";
    if (page === "account" && isAdmin()) page = "admin";
    state.page = page;
    state.overlay = null;
    history.replaceState(null, "", "#" + page);
    render();
    window.scrollTo(0, 0);
    if (page === "offers" && !state.offers.length) loadOffers();
    if (page === "history") loadOrders();
    if (page === "wallet" || page === "withdraw") { loadMethods(); loadPayments(); }
    if (page === "bill" && !state.bills.length) {
      api("boot", {}, "GET").then((b) => {
        if (b.ok && Array.isArray(b.bills) && b.bills.length) {
          state.bills = b.bills;
          if (state.page === "bill") render();
        }
      });
    }
    if (page === "notes") loadNotes();
  }

  function overlay(html) {
    state.overlay = html;
    drawOverlay();
  }
  function closeOverlay() {
    state.overlay = null;
    const o = $(".overlay");
    if (o) o.remove();
  }
  function drawOverlay() {
    $$(".overlay").forEach((e) => e.remove());
    if (!state.overlay) return;
    const d = document.createElement("div");
    d.className = "overlay";
    d.innerHTML = `<div class="sheet">${state.overlay}</div>`;
    d.addEventListener("click", (e) => { if (e.target === d) closeOverlay(); });
    document.body.appendChild(d);
    bind(d);
  }

  function balView(u) {
    if (!u) return { avail: 0, pend: 0 };
    return { avail: Number(u.available ?? (u.balance - u.pending)) || 0, pend: Number(u.pending) || 0 };
  }

  function topbar() {
    const u = state.user;
    const b = balView(u);
    const name = state.settings?.site_name || "OfferHub";
    const tag = state.settings?.tagline || "";
    return `
      <header class="topbar">
        <div class="brand">
          <div class="logo">${icons.logo}</div>
          <div>
            <h1>${esc(name)}</h1>
            <small>${esc(tag)}</small>
          </div>
        </div>
        ${u ? `
          <button class="bal-chip" data-go="wallet" title="ওয়ালেট">
            <b>৳${money(b.avail)}</b>
            <span class="${b.pend ? "" : "zero"}">${b.pend ? "(-" + money(b.pend) + ")" : "(০)"}</span>
          </button>
          <button class="icon-btn" data-go="notes">
            ${icons.bell}
            <i class="badge ${state.unread ? "" : "hide"}">${state.unread > 9 ? "9+" : state.unread}</i>
          </button>` : ""}
      </header>`;
  }

  function bnav() {
    const p = state.page;
    const last = isAdmin()
      ? ["admin", "এডমিন প্যানেল", icons.user]
      : ["account", "অ্যাকাউন্ট", icons.user];
    const items = [
      ["home", "হোম", icons.home],
      ["offers", "অফার", icons.offers],
      ["wallet", "ওয়ালেট", icons.wallet],
      ["history", "হিস্ট্রি", icons.hist],
      last,
    ];
    return `<nav class="bnav">${items.map(([k, l, ic]) => `<button data-go="${k}" class="${p === k ? "on" : ""}">${ic}<span>${l}</span></button>`).join("")}</nav>`;
  }

  function desk() {
    const name = state.settings?.site_name || "OfferHub";
    const items = [
      ["home", "হোম", icons.home],
      ["offers", "অফার", icons.offers],
      ["recharge", "রিচার্জ", icons.wallet],
      ["wallet", "ওয়ালেট", icons.wallet],
      ["history", "হিস্ট্রি", icons.hist],
      ["notes", "নোটিফিকেশন", icons.bell],
      isAdmin() ? ["admin", "এডমিন প্যানেল", icons.user] : ["account", "অ্যাকাউন্ট", icons.user],
    ];
    return `<aside class="desk">
      <div class="brand"><div class="logo">${icons.logo}</div><div><h1>${esc(name)}</h1><small>কনসোল</small></div></div>
      <div class="navb">${items.map(([k, l, ic]) => `<button data-go="${k}" class="${state.page === k ? "on" : ""}">${ic}<span>${l}</span></button>`).join("")}</div>
    </aside>`;
  }

  function offerCard(o) {
    const op = opOf(o.operator);
    const p = offerPrices(o);
    const prices = (o.type === "drive" && p.reg > 0 && p.drv > 0)
      ? `<span>রেগুলার: <b>৳${money(p.reg)}</b></span><span>অফার: <b>৳${money(p.drv)}</b></span><span class="comm">কমিশন ৳${money(p.com)}</span>`
      : `<span>রেগুলার: <b>৳${money(p.sell)}</b></span>`;
    return `<article class="card offer" data-hit="${esc(o.id)}">
      <div class="meta">
        <div class="off-name">
          <span class="op-tag" style="background:${op.color}">${esc(op.short || op.name_bn)}</span>
          <h3>${esc(offerHead(o, false))}</h3>
        </div>
        <div class="off-prices">${prices}</div>
      </div>
      <button class="btn sm" type="button">হিট</button>
    </article>`;
  }

  function pageHome() {
    const notice = state.settings?.notice || "";
    const ops = state.operators;
    const featured = state.offers.filter((o) => o.type === "regular").slice(0, 6);
    const drives = state.offers.filter((o) => o.type === "drive").slice(0, 4);
    return `<main class="page">
      ${notice ? `<div class="notice">${esc(notice)}</div>` : ""}
      <div class="ops">
        <button class="op ${state.op === "all" ? "on" : ""}" data-op="all"><div class="av" style="background:#0f766e">সব</div><span>সকল</span></button>
        ${ops.map((o) => `<button class="op ${state.op === o.id ? "on" : ""}" data-op="${o.id}"><div class="av" style="background:${o.color}">${esc(o.short)}</div><span>${esc(o.name_bn)}</span></button>`).join("")}
      </div>
      <div class="quick">
        <button class="qbtn" data-go="recharge"><div class="qi" style="background:#0ea5e9">${icons.wallet}</div><span>রিচার্জ</span></button>
        <button class="qbtn" data-type="drive"><div class="qi" style="background:#d97706">${icons.offers}</div><span>ড্রাইভ</span></button>
        <button class="qbtn" data-go="wallet"><div class="qi" style="background:#e11d48">${icons.wallet}</div><span>ডিপোজিট</span></button>
        <button class="qbtn" data-go="offers"><div class="qi" style="background:#0f766e">${icons.offers}</div><span>অফার</span></button>
        <button class="qbtn" data-go="bill"><div class="qi" style="background:#7c3aed">${icons.bill}</div><span>বিল পে</span></button>
        <button class="qbtn" data-go="withdraw"><div class="qi" style="background:#0369a1">${icons.withdraw}</div><span>উইথড্র</span></button>
        <button class="qbtn" data-go="transfer"><div class="qi" style="background:#0f766e">${icons.send}</div><span>ট্রান্সফার</span></button>
        <button class="qbtn" data-support="1"><div class="qi" style="background:#16a34a">${icons.support}</div><span>সাপোর্ট</span></button>
      </div>
      <div class="sec-h"><h2>জনপ্রিয় অফার</h2><button class="link" data-go="offers">সব দেখুন</button></div>
      <div class="grid">${featured.length ? featured.map(offerCard).join("") : `<div class="skel"></div><div class="skel"></div>`}</div>
      <div class="sec-h"><h2>ড্রাইভ অফার</h2><button class="link" data-type="drive">সব</button></div>
      <div class="grid">${drives.map(offerCard).join("") || `<div class="empty"><b>ড্রাইভ অফার নেই</b></div>`}</div>
    </main>`;
  }

  function filteredOffers() {
    return state.offers.filter((o) => {
      if (state.op !== "all" && o.operator !== state.op) return false;
      if (state.type !== "all" && o.type !== state.type) return false;
      if (state.cat !== "all" && o.category !== state.cat) return false;
      if (state.q) {
        const h = (offerHead(o, true) + " " + offerHead(o, false)).toLowerCase();
        if (!h.includes(state.q.toLowerCase())) return false;
      }
      return true;
    });
  }

  function pageOffers() {
    const list = filteredOffers();
    return `<main class="page">
      <div class="search">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3-3"/></svg>
        <input id="oq" placeholder="অফার খুঁজুন" value="${esc(state.q)}" />
      </div>
      <div class="ops">
        <button class="op ${state.op === "all" ? "on" : ""}" data-op="all"><div class="av" style="background:#0f766e">সব</div><span>সকল</span></button>
        ${state.operators.map((o) => `<button class="op ${state.op === o.id ? "on" : ""}" data-op="${o.id}"><div class="av" style="background:${o.color}">${esc(o.short)}</div><span>${esc(o.name_bn)}</span></button>`).join("")}
      </div>
      <div class="chips">
        <div class="chip-row">
          <button class="chip ${state.type === "all" ? "on" : ""}" data-type="all">সব ধরন</button>
          <button class="chip ${state.type === "regular" ? "on" : ""}" data-type="regular">রেগুলার</button>
          <button class="chip ${state.type === "drive" ? "on" : ""}" data-type="drive">ড্রাইভ</button>
        </div>
        <label class="cat-wrap">
          <select class="cat-filter ${state.cat !== "all" ? "on" : ""}" id="ocat" aria-label="ক্যাটাগরি ফিল্টার">
            <option value="all" ${state.cat === "all" ? "selected" : ""}>সব ক্যাটাগরি</option>
            <option value="internet" ${state.cat === "internet" ? "selected" : ""}>ইন্টারনেট</option>
            <option value="minutes" ${state.cat === "minutes" ? "selected" : ""}>মিনিট</option>
            <option value="combo" ${state.cat === "combo" ? "selected" : ""}>কম্বো</option>
          </select>
        </label>
      </div>
      <div class="grid two">${list.length ? list.map(offerCard).join("") : `<div class="empty card"><b>কোনো অফার নেই</b><p>ফিল্টার পরিবর্তন করুন</p></div>`}</div>
    </main>`;
  }

  function pageRecharge() {
    const s = state.settings || {};
    return `<main class="page">
      <div class="card">
        <h2 style="margin-bottom:10px">রিচার্জ</h2>
        <p class="hint" style="color:var(--muted);font-size:12.5px;margin-bottom:12px">নম্বর দিলে অপারেটর অটো ধরা হবে। পেন্ডিং থাকলে একই নম্বরে আবার হিট হবে না।</p>
        <div class="field"><label>মোবাইল নম্বর</label><input id="rnum" inputmode="numeric" maxlength="11" placeholder="01XXXXXXXXX" /></div>
        <div id="rop" class="time-pill" style="margin-top:-6px;margin-bottom:10px"></div>
        <div class="field"><label>পরিমাণ (৳${money(s.min_recharge || 10)} – ৳${money(s.max_recharge || 5000)})</label><input id="ramt" inputmode="numeric" placeholder="পরিমাণ" /></div>
        <div class="amt-chips" id="ramts">
          ${[20, 50, 100, 200, 500, 1000].map((n) => `<button data-amt="${n}">৳${n}</button>`).join("")}
        </div>
        <button class="btn block" id="rgo">রিচার্জ হিট করুন</button>
      </div>
    </main>`;
  }

  function pageBill() {
    const s = state.settings || {};
    const list = state.bills || [];
    const pid = state.billId || "";
    return `<main class="page">
      <div class="card">
        <h2 style="margin-bottom:10px">বিল পে</h2>
        <p class="hint" style="color:var(--muted);font-size:12.5px;margin-bottom:12px">ডেসকো, ডিপিডিসি, ওয়াসা, তিতাস গ্যাস — যে কোম্পানির বিল দেবেন সেটা বেছে নিন। পেন্ডিং কনফার্ম হলে টাকা কাটবে।</p>
        <input type="hidden" id="bprov" value="${esc(pid)}" />
        <p style="font-size:12px;font-weight:800;color:#334155;margin:0 0 8px">কোম্পানি বাছাই করুন</p>
        <div class="ops wrap" style="margin-bottom:12px">
          ${(list.length ? list : BILL_CATALOG).map((b) => `<button type="button" class="op ${pid === b.id ? "on" : ""}" data-bprov="${esc(b.id)}">
            <div class="av" style="background:${esc(b.color || "#0f766e")}">${esc((b.short || b.name_bn || "??").slice(0, 4))}</div>
            <span>${esc(b.name_bn || b.name)}</span>
          </button>`).join("")}
        </div>
        <div class="field"><label>কাস্টমার / অ্যাকাউন্ট নম্বর</label><input id="bacc" placeholder="অ্যাকাউন্ট নম্বর" /></div>
        <div class="field"><label>পরিমাণ (৳${money(s.min_bill || 50)} – ৳${money(s.max_bill || 20000)})</label><input id="bamt" inputmode="numeric" placeholder="পরিমাণ" /></div>
        <div class="amt-chips">
          ${[100, 200, 500, 1000, 2000].map((n) => `<button data-amt="${n}" data-amt-for="bamt">৳${n}</button>`).join("")}
        </div>
        <button class="btn block" type="button" id="bgo">বিল হিট করুন</button>
      </div>
    </main>`;
  }

  function pageWithdraw() {
    const s = state.settings || {};
    const ms = state.methods || [];
    const mid = state.wdId || "";
    const b = balView(state.user);
    return `<main class="page">
      <div class="card">
        <h2 style="margin-bottom:10px">উইথড্র</h2>
        <p class="hint" style="color:var(--muted);font-size:12.5px;margin-bottom:12px">ব্যবহারযোগ্য ৳${money(b.avail)} · পেন্ডিং কনফার্ম হলে টাকা কাটবে, ক্যান্সেল হলে ফেরত।</p>
        <input type="hidden" id="wmethod" value="${esc(mid)}" />
        <p style="font-size:12px;font-weight:800;color:#334155;margin:0 0 8px">মাধ্যম বাছাই করুন</p>
        <div class="ops wrap" style="margin-bottom:12px">
          ${ms.map((m) => `<button type="button" class="op ${mid === m.id ? "on" : ""}" data-wmethod="${esc(m.id)}">
            <div class="av" style="background:${esc(m.color || "#0f766e")}">${esc((m.name_bn || m.name || "??").slice(0, 4))}</div>
            <span>${esc(m.name_bn || m.name)}</span>
          </button>`).join("") || "<div class='empty'>মাধ্যম নেই</div>"}
        </div>
        <div class="field"><label>যে নম্বরে পাবেন</label><input id="wnum" inputmode="numeric" placeholder="01XXXXXXXXX বা অ্যাকাউন্ট" /></div>
        <div class="field"><label>পরিমাণ (৳${money(s.min_withdraw || 100)} – ৳${money(s.max_withdraw || 50000)})</label><input id="wamt" inputmode="numeric" placeholder="পরিমাণ" /></div>
        <div class="amt-chips">
          ${[100, 200, 500, 1000, 2000].map((n) => `<button data-amt="${n}" data-amt-for="wamt">৳${n}</button>`).join("")}
        </div>
        <button class="btn block" type="button" id="wgo">উইথড্র রিকোয়েস্ট</button>
      </div>
    </main>`;
  }

  function pageTransfer() {
    const s = state.settings || {};
    const b = balView(state.user);
    return `<main class="page">
      <div class="card">
        <h2 style="margin-bottom:10px">ট্রান্সফার</h2>
        <p class="hint" style="color:var(--muted);font-size:12.5px;margin-bottom:12px">রেজিস্টার করা ইউজারের নম্বরে তাৎক্ষণিক পাঠান। ব্যবহারযোগ্য ৳${money(b.avail)} · মিন ৳${money(s.min_transfer || 10)}।</p>
        <div class="field"><label>প্রাপকের নম্বর</label><input id="tnum" inputmode="numeric" maxlength="11" placeholder="01XXXXXXXXX" /></div>
        <div class="field"><label>পরিমাণ</label><input id="tamt" inputmode="numeric" placeholder="পরিমাণ" /></div>
        <div class="amt-chips">
          ${[50, 100, 200, 500, 1000].map((n) => `<button data-amt="${n}" data-amt-for="tamt">৳${n}</button>`).join("")}
        </div>
        <button class="btn block" id="tgo">ট্রান্সফার করুন</button>
      </div>
    </main>`;
  }

  function pageWallet() {
    const u = state.user;
    const b = balView(u);
    return `<main class="page">
      <div class="hero-bal">
        <em>ব্যবহারযোগ্য ব্যালেন্স</em>
        <div class="big">৳${money(b.avail)}</div>
        <div class="pend">${b.pend ? "৳" + money(b.avail) + " (−" + money(b.pend) + ") · পেন্ডিং কনফার্ম হলে কাটবে, ক্যান্সেল হলে ফেরত" : "কোনো পেন্ডিং নেই (০) · মোট ৳" + money(u.balance || 0)}</div>
        <div class="hero-actions">
          <button class="btn" id="addm">টাকা যোগ</button>
          <button class="btn" data-go="history">হিস্ট্রি</button>
        </div>
      </div>
      <div class="sec-h"><h2>ডিপোজিট হিস্ট্রি</h2></div>
      <div class="list">
        ${(state.payments || []).slice(0, 20).map((p) => `
          <div class="item">
            <div>
              <h4>${esc(p.method_name)} · ৳${money(p.amount)}</h4>
              <p>Trx ${esc(p.trx)} · ${esc(p.created_at)}</p>
            </div>
            <span class="st ${esc(p.status)}">${labelStatus(p.status)}</span>
          </div>`).join("") || `<div class="empty card"><b>এখনো ডিপোজিট নেই</b></div>`}
      </div>
    </main>`;
  }

  function labelStatus(s) {
    return ({ pending: "পেন্ডিং", confirmed: "কনফার্ম", cancelled: "ক্যান্সেল", approved: "অ্যাপ্রুভড", rejected: "রিজেক্ট" }[s] || s);
  }
  function labelKind(k) {
    return ({ offer: "অফার", recharge: "রিচার্জ", bill: "বিল", withdraw: "উইথড্র", transfer: "ট্রান্সফার" }[k] || "");
  }

  function pageHistory() {
    const list = state.orders || [];
    return `<main class="page">
      <div class="sec-h"><h2>অর্ডার হিস্ট্রি</h2></div>
      <div class="list">
        ${list.length ? list.map((o) => {
          const op = opOf(o.operator);
          return `<div class="item">
            <div>
              <h4>${esc(orderHead(o))}</h4>
              <p>${esc(labelKind(o.kind || o.type))} · ${esc(o.number)} · ৳${money(o.amount)}${o.commission ? " · কমিশন ৳" + money(o.commission) : ""}</p>
              <p>${esc(o.code)} · ${esc(o.created_at)}</p>
            </div>
            <span class="st ${esc(o.status)}">${labelStatus(o.status)}</span>
          </div>`;
        }).join("") : `<div class="empty card"><b>কোনো অর্ডার নেই</b><p>অফার হিট করলে এখানে দেখাবে</p></div>`}
      </div>
    </main>`;
  }

  function pageNotes() {
    return `<main class="page">
      <div class="sec-h"><h2>নোটিফিকেশন</h2><button class="link" id="readall">সব পড়া হয়েছে</button></div>
      <div class="list">
        ${(state.notes || []).map((n) => `
          <div class="n-item ${n.read ? "" : "unread"}" data-nid="${esc(n.id)}">
            <b>${esc(n.title)}</b>
            <p>${esc(n.body)}</p>
            <time>${esc(n.at)}</time>
          </div>`).join("") || `<div class="empty card"><b>কোনো নোটিফিকেশন নেই</b></div>`}
      </div>
    </main>`;
  }

  function pushCard() {
    const supported = ("Notification" in window) && ("serviceWorker" in navigator) && ("PushManager" in window);
    let status, action = "";
    if (!supported) {
      status = "<span style='color:var(--bad)'>এই ব্রাউজারে সাপোর্ট নেই</span>";
    } else if (Notification.permission === "granted") {
      status = "<span style='color:#047857'>চালু আছে</span>";
      action = `<button class="btn ghost block" style="margin-top:10px" data-push="yes" type="button">আবার সিঙ্ক করুন</button>`;
    } else if (Notification.permission === "denied") {
      status = "<span style='color:var(--bad)'>ব্লক করা আছে</span>";
      action = `<p class="hint" style="margin-top:8px">ব্রাউজারের অ্যাড্রেস বারের পাশে থাকা 🔒/সাইট সেটিংস থেকে নোটিফিকেশন 'Allow' করুন।</p>`;
    } else {
      status = "<span style='color:var(--muted)'>বন্ধ</span>";
      action = `<button class="btn block" style="margin-top:10px" data-push="yes" type="button">নোটিফিকেশন চালু করুন</button>`;
    }
    return `<div class="card">
      <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
        <div style="display:flex;align-items:center;gap:10px">
          <span style="display:inline-flex;width:22px;height:22px;color:var(--brand)">${icons.bell}</span>
          <b>পুশ নোটিফিকেশন</b>
        </div>
        <span style="font-size:13px">${status}</span>
      </div>
      ${action}
    </div>`;
  }

  function pageAccount() {
    const u = state.user;
    const b = balView(u);
    return `<main class="page">
      <div class="card profile-head">
        <div class="avatar">${esc((u.name || "U").slice(0, 1))}</div>
        <div style="min-width:0">
          <h2 style="font-size:16px">${esc(u.name)}</h2>
          <p style="color:var(--muted);font-size:13px">${esc(u.phone)}</p>
          <p style="font-size:12px;margin-top:4px">৳${money(b.avail)} <span style="color:var(--bad)">(-${money(b.pend)})</span></p>
        </div>
      </div>
      <div class="card">
        <div class="field"><label>নাম</label><input id="pname" value="${esc(u.name)}" /></div>
        <button class="btn block" id="psave">নাম সেভ</button>
      </div>
      ${pushCard()}
      <div class="card">
        <div class="field"><label>বর্তমান পাসওয়ার্ড</label><input id="pold" type="password" /></div>
        <div class="field"><label>নতুন পাসওয়ার্ড</label><input id="pnew" type="password" /></div>
        <button class="btn ghost block" id="ppass">পাসওয়ার্ড বদলান</button>
      </div>
      <button class="btn bad block" id="plogout">লগআউট</button>
    </main>`;
  }

  function pageAuth() {
    const name = state.settings?.site_name || "OfferHub";
    return `<div class="auth">
      <div class="mark">${icons.logo}</div>
      <h1>${esc(name)}</h1>
      <p class="lead">অফার, ড্রাইভ ও রিচার্জ — এক জায়গায়।</p>
      <div class="tabs">
        <button class="on" data-auth="login">লগইন</button>
        <button data-auth="reg">রেজিস্টার</button>
      </div>
      <form id="authform">
        <div id="authbox">${authForm("login")}</div>
      </form>
    </div>`;
  }
  function authForm(kind) {
    const phoneInput = kind === "reg"
      ? `<input id="aphone" name="phone" inputmode="numeric" maxlength="11" placeholder="01XXXXXXXXX" autocomplete="username" />`
      : `<input id="aphone" name="phone" placeholder="01XXXXXXXXX" autocomplete="username" />`;
    return `
      ${kind === "reg" ? `<div class="field"><label>নাম</label><input id="aname" name="name" placeholder="আপনার নাম" /></div>` : ""}
      <div class="field"><label>মোবাইল নম্বর</label>${phoneInput}</div>
      <div class="field"><label>পাসওয়ার্ড</label><input id="apass" name="password" type="password" placeholder="কমপক্ষে ৬ অক্ষর" autocomplete="current-password" /></div>
      <button class="btn block" type="button" id="ago" data-kind="${kind}">${kind === "login" ? "লগইন" : "একাউন্ট খুলুন"}</button>
    `;
  }

  function hitSheet(offer) {
    const p = offerPrices(offer);
    const priceRows = (offer.type === "drive" && p.reg > 0 && p.drv > 0)
      ? `<div style="display:flex;justify-content:space-between;gap:8px"><span>রেগুলার</span><b>৳${money(p.reg)}</b></div>
        <div style="display:flex;justify-content:space-between;gap:8px;margin-top:6px"><span>ড্রাইভ</span><b>৳${money(p.drv)}</b></div>
        <div style="display:flex;justify-content:space-between;gap:8px;margin-top:6px"><span>কমিশন</span><b style="color:#047857">৳${money(p.com)}</b></div>`
      : `<div style="display:flex;justify-content:space-between;gap:8px"><span>মূল্য</span><b>৳${money(p.sell)}</b></div>`;
    return `
      <div class="handle"></div>
      <h3>${esc(offerHead(offer, true))}</h3>
      ${offer.note ? `<p class="hint">${esc(offer.note)}</p>` : ""}
      <div class="card" style="margin-bottom:12px">
        ${priceRows}
      </div>
      <div class="field"><label>যে নম্বরে হিট হবে</label><input id="onum" inputmode="numeric" maxlength="11" placeholder="01XXXXXXXXX" /></div>
      <p class="hint" id="ohint"></p>
      <button class="btn block" id="oconfirm" data-oid="${esc(offer.id)}">কনফার্ম হিট · ৳${money(p.sell)}</button>
      <button class="btn ghost block" style="margin-top:8px" id="oclose">বন্ধ</button>
    `;
  }

  function paySheet() {
    const ms = state.methods || [];
    return `
      <div class="handle"></div>
      <h3>টাকা যোগ করুন</h3>
      <p class="hint">নিজস্ব গেটওয়ে · বিকাশ, নগদ, রকেট, উপায়, ইউক্যাশ, ব্যাংক</p>
      <div class="pay-list" id="plist">
        ${ms.map((m) => `<button class="pay-item" type="button" data-pick="${esc(m.id)}">
          <div class="dot" style="background:${esc(m.color)}">${esc((m.name_bn || m.name).slice(0, 2))}</div>
          <div><b>${esc(m.name_bn || m.name)}</b><small>${esc(m.type)} · মিন ৳${money(m.min)}</small></div>
          <span class="time-pill">বাছাই</span>
        </button>`).join("") || "<div class='empty'>মাধ্যম নেই</div>"}
      </div>
    `;
  }

  function payForm(m) {
    return `
      <div class="handle"></div>
      <h3>${esc(m.name_bn || m.name)}</h3>
      <p class="hint">${esc(m.instructions)}</p>
      ${m.bank_name ? `<p class="hint">${esc(m.bank_name)} · ${esc(m.account_name || "")}</p>` : ""}
      <div class="copybox"><code id="mnum">${esc(m.number)}</code><button class="btn sm" data-copy="${esc(m.number)}">কপি</button></div>
      <div class="field"><label>পরিমাণ</label><input id="damt" inputmode="numeric" placeholder="সর্বনিম্ন ৳${money(m.min)}" /></div>
      <div class="field"><label>সেন্ডার নম্বর</label><input id="dsend" inputmode="numeric" maxlength="11" placeholder="যে নম্বর থেকে পাঠাবেন" /></div>
      <div class="field"><label>TrxID</label><input id="dtrx" placeholder="ট্রানজেকশন আইডি" /></div>
      <button class="btn block" type="button" id="dgo" data-pay="${esc(m.id)}">জমা দিন</button>
      <button class="btn ghost block" style="margin-top:8px" id="oclose">বন্ধ</button>
    `;
  }

  function render() {
    if (state.splash) {
      app.innerHTML = `<div class="splash">
        <div class="mark">${icons.logo}</div>
        <h1>${esc(state.settings?.site_name || "OfferHub")}</h1>
        <p>${esc(state.settings?.tagline || "স্মার্ট অফার ও রিচার্জ")}</p>
        <div class="dots"><i></i><i></i><i></i></div>
      </div>`;
      return;
    }
    if (!state.user) {
      app.innerHTML = pageAuth();
      bind(app);
      return;
    }
    let body = "";
    switch (state.page) {
      case "offers": body = pageOffers(); break;
      case "recharge": body = pageRecharge(); break;
      case "bill": body = pageBill(); break;
      case "withdraw": body = pageWithdraw(); break;
      case "transfer": body = pageTransfer(); break;
      case "wallet": body = pageWallet(); break;
      case "history": body = pageHistory(); break;
      case "notes": body = pageNotes(); break;
      case "admin": body = ""; break;
      case "account": body = pageAccount(); break;
      default: body = pageHome();
    }
    app.innerHTML = `<div class="shell">${desk()}<div class="col"><div class="head-stack">${topbar()}${tickerBar()}</div>${body}</div></div>${bnav()}`;
    mountNx(state.page === "admin" && isAdmin());
    bind(app);
    drawOverlay();
  }

  function bind(root) {
    $$("[data-go]", root).forEach((b) => b.addEventListener("click", () => goto(b.dataset.go)));
    $$("[data-op]", root).forEach((b) => b.addEventListener("click", () => {
      state.op = b.dataset.op;
      if (state.page !== "offers") { state.page = "offers"; history.replaceState(null, "", "#offers"); }
      render();
    }));
    $$("[data-type]", root).forEach((b) => b.addEventListener("click", () => {
      state.type = b.dataset.type === "all" ? "all" : b.dataset.type;
      state.page = "offers";
      history.replaceState(null, "", "#offers");
      render();
    }));
    const ocat = $("#ocat", root);
    if (ocat) ocat.addEventListener("change", () => {
      state.cat = ocat.value || "all";
      state.page = "offers";
      history.replaceState(null, "", "#offers");
      render();
    });
    $$("[data-hit]", root).forEach((b) => b.addEventListener("click", () => {
      const o = state.offers.find((x) => x.id === b.dataset.hit);
      if (o) overlay(hitSheet(o));
    }));
    $$("[data-copy]", root).forEach((b) => b.addEventListener("click", () => copy(b.dataset.copy)));
    $$("[data-auth]", root).forEach((b) => b.addEventListener("click", () => {
      $$("[data-auth]", root).forEach((x) => x.classList.remove("on"));
      b.classList.add("on");
      const box = $("#authbox");
      if (box) { box.innerHTML = authForm(b.dataset.auth); bind(box); }
    }));
    const ago = $("#ago", root);
    if (ago) ago.addEventListener("click", onAuth);
    const aform = $("#authform", root);
    if (aform) aform.addEventListener("submit", (e) => { e.preventDefault(); onAuth({ currentTarget: ago || { dataset: { kind: "login" } } }); });
    const oq = $("#oq", root);
    if (oq) oq.addEventListener("input", () => { state.q = oq.value; });
    if (oq) oq.addEventListener("change", () => render());
    const oclose = $("#oclose", root);
    if (oclose) oclose.addEventListener("click", closeOverlay);
    const oconfirm = $("#oconfirm", root);
    if (oconfirm) oconfirm.addEventListener("click", () => doHit(oconfirm.dataset.oid));
    const addm = $("#addm", root);
    if (addm) addm.addEventListener("click", async () => {
      if (!state.methods.length) await loadMethods();
      overlay(paySheet());
    });
    $$("[data-pick]", root).forEach((b) => b.addEventListener("click", () => {
      const m = state.methods.find((x) => x.id === b.dataset.pick);
      if (m) overlay(payForm(m));
    }));
    const dgo = $("#dgo", root);
    if (dgo) dgo.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      doDeposit(dgo.dataset.pay);
    });
    const rgo = $("#rgo", root);
    if (rgo) rgo.addEventListener("click", doRecharge);
    $$("[data-support]", root).forEach((b) => b.addEventListener("click", () => {
      const n = String(state.settings?.whatsapp || state.settings?.support_phone || "").replace(/\D/g, "").replace(/^88/, "");
      if (!n) return toast("সাপোর্ট নম্বর সেট নেই", "bad");
      window.open("https://wa.me/88" + n, "_blank");
    }));
    $$("[data-bprov]", root).forEach((b) => b.addEventListener("click", () => {
      const id = b.getAttribute("data-bprov") || "";
      state.billId = id;
      const hid = $("#bprov", root);
      if (hid) hid.value = id;
      $$("[data-bprov]", root).forEach((x) => {
        x.classList.toggle("on", x === b);
        const t = x.querySelector(".time-pill");
        if (t) t.textContent = x === b ? "বাছাই" : "";
      });
    }));
    $$("[data-wmethod]", root).forEach((b) => b.addEventListener("click", () => {
      const id = b.getAttribute("data-wmethod") || "";
      state.wdId = id;
      const hid = $("#wmethod", root);
      if (hid) hid.value = id;
      $$("[data-wmethod]", root).forEach((x) => {
        x.classList.toggle("on", x === b);
        const t = x.querySelector(".time-pill");
        if (t) t.textContent = x === b ? "বাছাই" : "";
      });
    }));
    const bgo = $("#bgo", root);
    if (bgo) bgo.addEventListener("click", doBill);
    const wgo = $("#wgo", root);
    if (wgo) wgo.addEventListener("click", doWithdraw);
    const tgo = $("#tgo", root);
    if (tgo) tgo.addEventListener("click", doTransfer);
    $$("[data-amt]", root).forEach((b) => b.addEventListener("click", () => {
      const id = b.dataset.amtFor || "ramt";
      const inp = document.getElementById(id);
      if (inp) inp.value = b.dataset.amt;
      const wrap = b.parentElement;
      if (wrap) [...wrap.querySelectorAll("[data-amt]")].forEach((x) => x.classList.toggle("on", x === b));
    }));
    const rnum = $("#rnum", root);
    if (rnum) rnum.addEventListener("input", () => {
      const op = detect(rnum.value);
      const el = $("#rop");
      if (el) el.textContent = op ? "অপারেটর: " + op.name_bn : (rnum.value.length >= 3 ? "অপারেটর শনাক্ত হয়নি" : "");
    });
    const psave = $("#psave", root);
    if (psave) psave.addEventListener("click", async () => {
      const r = await api("profile", { name: $("#pname").value });
      if (r.ok) { state.user = r.user; toast("সেভ হয়েছে", "ok"); render(); } else toast(r.error, "bad");
    });
    const ppass = $("#ppass", root);
    if (ppass) ppass.addEventListener("click", async () => {
      const r = await api("password", { old: $("#pold").value, new: $("#pnew").value });
      if (r.ok) toast("পাসওয়ার্ড বদলানো হয়েছে", "ok"); else toast(r.error, "bad");
    });
    const plogout = $("#plogout", root);
    if (plogout) plogout.addEventListener("click", async () => {
      await api("logout");
      state.token = ""; state.user = null; state.isAdmin = false;
      localStorage.removeItem("oh_token");
      localStorage.removeItem("oh_at");
      localStorage.removeItem("oh_is_admin");
      document.body.classList.remove("nx-on");
      const nx = document.getElementById("nxembed");
      if (nx) nx.remove();
      render();
    });
    const readall = $("#readall", root);
    if (readall) readall.addEventListener("click", async () => {
      await api("notifications_read", {});
      loadNotes();
    });
    $$("[data-nid]", root).forEach((el) => el.addEventListener("click", async () => {
      await api("notifications_read", { id: el.dataset.nid });
      loadNotes();
    }));
    $$("[data-reload-permission]", root).forEach((b) => b.addEventListener("click", async () => {
      closeOverlay();
      await pushEnable();
      if (state.page === "account") render();
    }));
    $$("[data-push]", root).forEach((b) => b.addEventListener("click", async () => {
      if (b.dataset.push !== "yes") {
        localStorage.setItem(PUSH_ASK_KEY, "1");
        closeOverlay();
        return;
      }
      // Close the intro sheet (if any) but keep the account toggle in place.
      if ($(".overlay")) closeOverlay();
      b.disabled = true;
      const prev = b.textContent;
      b.textContent = "চালু হচ্ছে…";
      await pushEnable();
      b.disabled = false;
      b.textContent = prev;
      if (state.page === "account") render();
    }));
  }

  function detect(num) {
    const n = String(num).replace(/\D/g, "").replace(/^88/, "");
    const p = n.slice(0, 3);
    return state.operators.find((o) => (o.prefixes || []).includes(p));
  }

  async function onAuth(e) {
    if (e && typeof e.preventDefault === "function") e.preventDefault();
    if (state.busy) return;
    const kind = e.currentTarget?.dataset?.kind || $("#ago")?.dataset?.kind || "login";
    const box = document.getElementById("authform") || document;
    const phone = (box.querySelector("#aphone")?.value || "").trim();
    const password = box.querySelector("#apass")?.value || "";
    const name = (box.querySelector("#aname")?.value || "").trim();
    if (!phone || !password) return toast("নম্বর ও পাসওয়ার্ড দিন", "bad");
    state.busy = true;
    const r = await api(kind === "reg" ? "register" : "login", { phone, password, name });
    state.busy = false;
    if (!r.ok) return toast(r.error || "ব্যর্থ", "bad");
    if (r.kind === "admin") {
      localStorage.setItem("oh_at", r.token);
      if (r.user) {
        state.token = r.token;
        state.user = r.user;
        state.isAdmin = true;
        localStorage.setItem("oh_token", r.token);
        toast("স্বাগতম", "ok");
        await afterLogin();
        goto("home");
        return;
      }
      enterAdmin(r.token);
      return;
    }
    state.token = r.token;
    state.user = r.user;
    state.isAdmin = false;
    localStorage.setItem("oh_token", r.token);
    localStorage.removeItem("oh_at");
    localStorage.removeItem("oh_is_admin");
    toast("স্বাগতম", "ok");
    await afterLogin();
    goto("home");
  }

  function enterAdmin(token) {
    localStorage.setItem("oh_at", token);
    localStorage.removeItem("oh_token");
    const appBox = document.getElementById("app");
    if (appBox) appBox.style.display = "none";
    let root = document.getElementById("root");
    if (!root) {
      root = document.createElement("div");
      root.id = "root";
      document.body.appendChild(root);
    }
    root.style.display = "block";
    root.innerHTML = '<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0b1220;color:#94a3b8;font-family:system-ui">কনসোল খুলছে…</div>';
    if (!document.getElementById("admin-css")) {
      const css = document.createElement("link");
      css.id = "admin-css";
      css.rel = "stylesheet";
      css.href = "assets/admin.css?v=20";
      document.head.appendChild(css);
    }
    const go = () => {
      if (window.AdminApp && typeof window.AdminApp.start === "function") {
        window.AdminApp.start(token);
      }
    };
    if (window.AdminApp) {
      go();
      return;
    }
    const s = document.createElement("script");
    s.src = "assets/admin.js?v=20";
    s.onload = go;
    s.onerror = () => {
      root.innerHTML = '<div style="padding:24px;color:#fda4af;background:#0b1220;min-height:100vh">কনসোল লোড হয়নি। পেজ রিফ্রেশ করুন।</div>';
    };
    document.head.appendChild(s);
  }

  async function doHit(id) {
    if (state.busy) return;
    const offer = state.offers.find((x) => x.id === id);
    const number = $("#onum")?.value || "";
    if (!offer) return;
    state.busy = true;
    const btn = $("#oconfirm");
    if (btn) btn.disabled = true;
    const r = await api("order", { offer_id: id, number, idem: idem() });
    state.busy = false;
    if (btn) btn.disabled = false;
    if (!r.ok) return toast(r.error || "ব্যর্থ", "bad");
    state.user = r.user;
    closeOverlay();
    toast("অফার হিট হয়েছে · পেন্ডিং", "ok");
    render();
    loadOrders();
  }

  async function doBill() {
    if (state.busy) return;
    const pid = state.billId || $("#bprov")?.value || document.querySelector("[data-bprov].on")?.getAttribute("data-bprov") || "";
    if (!pid) return toast("কোম্পানি বাছাই করুন", "bad");
    state.billId = pid;
    state.busy = true;
    const r = await api("bill", { provider: pid, account: $("#bacc")?.value, amount: $("#bamt")?.value, idem: idem() });
    state.busy = false;
    if (!r.ok) return toast(r.error || "ব্যর্থ", "bad");
    state.user = r.user;
    toast("বিল পেন্ডিং", "ok");
    goto("history");
  }
  async function doWithdraw() {
    if (state.busy) return;
    const mid = state.wdId || $("#wmethod")?.value || document.querySelector("[data-wmethod].on")?.getAttribute("data-wmethod") || "";
    if (!mid) return toast("মাধ্যম বাছাই করুন", "bad");
    state.wdId = mid;
    state.busy = true;
    const r = await api("withdraw", { method: mid, number: $("#wnum")?.value, amount: $("#wamt")?.value, idem: idem() });
    state.busy = false;
    if (!r.ok) return toast(r.error || "ব্যর্থ", "bad");
    state.user = r.user;
    toast("উইথড্র পেন্ডিং", "ok");
    goto("history");
  }
  async function doTransfer() {
    if (state.busy) return;
    state.busy = true;
    const r = await api("transfer", { phone: $("#tnum")?.value, amount: $("#tamt")?.value, idem: idem() });
    state.busy = false;
    if (!r.ok) return toast(r.error || "ব্যর্থ", "bad");
    state.user = r.user;
    toast("ট্রান্সফার সফল", "ok");
    goto("history");
  }
  async function doRecharge() {
    if (state.busy) return;
    state.busy = true;
    const r = await api("recharge", { number: $("#rnum")?.value, amount: $("#ramt")?.value, idem: idem() });
    state.busy = false;
    if (!r.ok) return toast(r.error || "ব্যর্থ", "bad");
    state.user = r.user;
    toast("রিচার্জ পেন্ডিং", "ok");
    goto("history");
  }

  async function doDeposit(mid) {
    if (state.busy) return;
    const box = document.querySelector(".overlay") || document;
    const amount = Number($("#damt", box)?.value || 0);
    const sender = $("#dsend", box)?.value || "";
    const trx = $("#dtrx", box)?.value || "";
    if (!mid) return toast("পেমেন্ট মাধ্যম বাছাই করুন", "bad");
    if (!(amount > 0)) return toast("সঠিক পরিমাণ লিখুন", "bad");
    state.busy = true;
    const r = await api("deposit", { method: mid, amount, sender, trx });
    state.busy = false;
    if (!r.ok) return toast(r.error || "ব্যর্থ", "bad");
    state.user = r.user;
    closeOverlay();
    toast("পেমেন্ট জমা হয়েছে", "ok");
    loadPayments();
    goto("wallet");
  }

  async function loadOffers() {
    let r = await api("offers", {}, "GET");
    if (!r.ok || !Array.isArray(r.offers)) r = await api("offers", {});
    if (r.ok && Array.isArray(r.offers) && (r.offers.length || !state.offers.length)) {
      state.offers = r.offers;
      render();
    }
  }
  async function loadOrders() {
    const r = await api("orders", {});
    if (r.ok) { state.orders = r.orders || []; if (state.page === "history") render(); }
  }
  async function loadMethods() {
    const r = await api("methods", {});
    if (r.ok) state.methods = r.methods || [];
  }
  async function loadPayments() {
    const r = await api("payments", {});
    if (r.ok) { state.payments = r.payments || []; if (state.page === "wallet") render(); }
  }
  async function loadNotes() {
    const r = await api("notifications", {});
    if (r.ok) {
      state.notes = r.items || [];
      state.unread = r.unread || 0;
      if (state.page === "notes") render();
      else {
        const b = $(".badge");
        if (b) { b.textContent = state.unread > 9 ? "9+" : state.unread; b.classList.toggle("hide", !state.unread); }
      }
    }
  }

  async function afterLogin() {
    await Promise.all([loadOffers(), loadNotes(), loadMethods()]);
    pushEnsureSubscribed();
  }

  async function boot() {
    const at = localStorage.getItem("oh_at") || "";
    if (at && !state.token) {
      state.token = at;
      localStorage.setItem("oh_token", at);
    }
    const b = await api("boot", {}, "GET");
    if (b.ok) {
      state.settings = b.settings;
      state.operators = b.operators || [];
      if (Array.isArray(b.bills) && b.bills.length) state.bills = b.bills;
      else if (!state.bills.length) state.bills = BILL_CATALOG;
      if (Array.isArray(b.offers) && b.offers.length) state.offers = b.offers;
    }
    if (state.token) {
      const me = await api("me", {}, "GET");
      if (me.ok) {
        state.user = me.user;
        state.isAdmin = me.user.role === "admin";
        if (state.isAdmin) localStorage.setItem("oh_at", state.token);
        await afterLogin();
      } else {
        state.token = "";
        state.isAdmin = false;
        localStorage.removeItem("oh_token");
        localStorage.removeItem("oh_at");
      }
    }
    if (!state.offers.length) await loadOffers();
    if (!state.offers.length && Array.isArray(window.__OH_OFFERS) && window.__OH_OFFERS.length) {
      state.offers = window.__OH_OFFERS;
    }
    let hash = (location.hash || "#home").slice(1) || "home";
    if (hash === "admin" && !isAdmin()) hash = "account";
    if (hash === "account" && isAdmin()) hash = "admin";
    state.page = hash;
    state.splash = false;
    render();
    setTimeout(pushMaybeAsk, 1000);
  }

  setInterval(() => {
    const el = document.getElementById("liveclk");
    if (el) el.textContent = fmtClock();
  }, 1000);

  setInterval(async () => {
    if (!state.token || !state.user) return;
    if (state.page === "admin") return;
    const r = await api("poll", {}, "GET");
    if (!r.ok) return;
    const prevA = state.user.available, prevP = state.user.pending;
    state.user = r.user;
    if (r.unread > state.unread && r.latest && r.latest.id !== state.lastNote) {
      state.lastNote = r.latest.id;
      toast(r.latest.title, "ok");
    }
    state.unread = r.unread || 0;
    const chip = $(".bal-chip");
    if (chip) {
      const b = balView(state.user);
      chip.innerHTML = `<b>৳${money(b.avail)}</b><span class="${b.pend ? "" : "zero"}">${b.pend ? "(-" + money(b.pend) + ")" : "(০)"}</span>`;
    }
    const badge = $(".badge");
    if (badge) { badge.textContent = state.unread > 9 ? "9+" : state.unread; badge.classList.toggle("hide", !state.unread); }
    if (prevA !== r.user.available || prevP !== r.user.pending) {
      if (["wallet", "account", "home"].includes(state.page)) render();
    }
  }, 5000);

  if ("serviceWorker" in navigator) {
    // Register (or refresh) the service worker WITHOUT unregistering first.
    // Unregistering drops the existing Web Push subscription, which previously
    // wiped notifications on every reload; sw.js already self-updates via
    // skipWaiting()/clients.claim() and the versioned ?v= query.
    navigator.serviceWorker.register("/sw.js?v=31", { scope: "/" }).then((reg) => {
      try { reg.update(); } catch (e) {}
    }).catch(() => {});
  }

  boot();
})();
