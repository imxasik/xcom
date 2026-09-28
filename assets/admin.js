(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const rootEl = () => document.getElementById("root");
  const S = {
    token: localStorage.getItem("oh_at") || "",
    admin: null,
    page: "dash",
    settings: {},
    operators: [],
    methods: [],
    bills: [],
    stats: {},
    orders: [],
    offers: [],
    users: [],
    payments: [],
    logs: [],
    dashOrders: [],
    dashPays: [],
    filter: "pending",
    q: "",
    overlay: null,
    menu: false,
    offOp: "all",
    offType: "all",
    offCat: "all",
  };

  const navGroups = [
    { title: "কাজ", items: [["dash", "ওভারভিউ"], ["orders", "অর্ডার"], ["offers", "অফার"], ["pay", "পেমেন্ট"]] },
    { title: "হিসাব", items: [["users", "ইউজার"], ["methods", "গেটওয়ে"], ["ops", "অপারেটর"], ["bills", "বিল"]] },
    { title: "সিস্টেম", items: [["note", "নোটিশ"], ["logs", "লগ"], ["set", "সেটিংস"]] },
  ];
  const pages = navGroups.flatMap((g) => g.items);

  function esc(s) { return String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])); }
  function money(n) { return (Number(n) || 0).toLocaleString("en-BD", { maximumFractionDigits: 0 }); }
  function st(s) {
    const m = { pending: "পেন্ডিং", confirmed: "কনফার্ম", cancelled: "ক্যান্সেল", approved: "অ্যাপ্রুভ", rejected: "রিজেক্ট", active: "অন", blocked: "ব্লক", inactive: "অফ" };
    return `<span class="st ${esc(s)}">${m[s] || s}</span>`;
  }
  function kindTag(k) {
    const m = { recharge: "রিচার্জ", bill: "বিল", withdraw: "উইথড্র", transfer: "ট্রান্সফার" };
    return m[k] ? `<span class="st confirmed">${m[k]}</span>` : "";
  }

  async function api(action, data = {}) {
    const res = await fetch("api.php?action=" + encodeURIComponent(action), {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-Token": S.token, Authorization: "Bearer " + S.token },
      body: JSON.stringify({ ...(data || {}), action }),
    });
    let j = {};
    try { j = await res.json(); } catch (e) { j = { ok: false, error: "সার্ভার ত্রুটি" }; }
    if (res.status === 401) { S.token = ""; localStorage.removeItem("oh_at"); S.admin = null; render(); }
    return j;
  }
  function toast(msg, kind = "") {
    $$(".toast").forEach((t) => t.remove());
    const el = document.createElement("div");
    el.className = "toast " + kind;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2500);
  }
  function copy(t) {
    const v = String(t || "");
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(v).then(() => toast("কপি হয়েছে")).catch(fb);
    else fb();
    function fb() {
      const ta = document.createElement("textarea"); ta.value = v; document.body.appendChild(ta); ta.select();
      try { document.execCommand("copy"); toast("কপি হয়েছে"); } catch (e) { toast("কপি ব্যর্থ", "bad"); }
      ta.remove();
    }
  }
  function goto(p) { S.page = p; S.overlay = null; loadPage(); }

  function gate() {
    return `<div class="gate"><form class="gate-card" id="gform" autocomplete="off">
      <h1>System Sync</h1>
      <p>Authorized operators only. Unauthorized access is logged.</p>
      <div class="field"><label>ID</label><input id="gu" autocomplete="username" /></div>
      <div class="field"><label>Key</label><input id="gp" type="password" autocomplete="current-password" /></div>
      <button class="btn block" type="submit">Continue</button>
    </form></div>`;
  }

  function navBlock(closeId) {
    return `<div class="side-inner">
      <div class="nav-brand">
        <strong>Console</strong>
        ${closeId ? `<button type="button" class="nav-close" id="${closeId}" aria-label="বন্ধ">×</button>` : ""}
      </div>
      <div class="nav-scroll">
        ${navGroups.map((g) => `<div class="nav-group">
          <div class="nav-label">${g.title}</div>
          ${g.items.map(([k, l]) => `<button type="button" class="nav-item ${S.page === k ? "on" : ""}" data-go="${k}">${l}</button>`).join("")}
        </div>`).join("")}
      </div>
      <div class="nav-foot">
        <button type="button" class="nav-out" id="${closeId ? "out2" : "out"}">সাইন আউট</button>
      </div>
    </div>`;
  }

  function shell(inner) {
    const title = pages.find((p) => p[0] === S.page)?.[1] || "Console";
    return `<div class="layout">
      <aside class="side">${navBlock("")}</aside>
      <div class="col">
        <div class="top">
          <button type="button" class="hamb" id="hamb" aria-label="মেনু">☰</button>
          <b>${title}</b>
          <span class="who">${esc(S.admin?.username || "")}</span>
        </div>
        <div class="main">${inner}</div>
      </div>
    </div>
    <div class="drawer" id="drawer">
      <div class="dim" id="ddim"></div>
      <div class="panel" id="dpanel">${navBlock("dclose")}</div>
    </div>`;
  }

  function viewDash() {
    const s = S.stats || {};
    const orders = (S.dashOrders || []).slice(0, 12);
    const pays = (S.dashPays || []).slice(0, 12);
    return `
      <div class="stats">
        <div class="stat"><em>পেন্ডিং অর্ডার</em><strong>${s.orders_pending || 0}</strong></div>
        <div class="stat"><em>পেন্ডিং পেমেন্ট</em><strong>${s.payments_pending || 0}</strong></div>
        <div class="stat"><em>আজকের সেলস</em><strong>৳${money(s.sales_today)}</strong></div>
        <div class="stat"><em>ইউজার</em><strong>${s.users || 0}</strong></div>
      </div>
      <div class="card"><p style="color:#94a3b8">আজকের অর্ডার ${s.orders_today || 0} · মোট সেলস ৳${money(s.sales_total)} · আজকের ডিপোজিট ৳${money(s.deposit_today)}</p></div>
      <div class="nav-label" style="padding:4px 2px 0">অর্ডার কিউ</div>
      <div class="list">${orders.length ? orders.map((o) => `
        <div class="item">
          <div class="row">
            <h4 class="grow">${esc(o.title)} · ${esc(o.number)}</h4>
            ${kindTag(o.kind)}${st(o.status)}
          </div>
          <p>${esc(o.code)} · ৳${money(o.amount)} · ${esc(o.created_at)}</p>
          <div class="acts">
            <button class="btn sm ghost" data-copy-id="${esc(o.id)}">হাউজ কপি</button>
            ${o.status === "pending" ? `
              <button class="btn sm ok" data-ord="${esc(o.id)}" data-do="confirm">কনফার্ম</button>
              <button class="btn sm bad" data-ord="${esc(o.id)}" data-do="cancel">ক্যান্সেল</button>` : ""}
          </div>
        </div>`).join("") : `<div class="card">পেন্ডিং অর্ডার নেই</div>`}</div>
      <div class="nav-label" style="padding:8px 2px 0">পেমেন্ট কিউ</div>
      <div class="list">${pays.length ? pays.map((p) => `
        <div class="item">
          <div class="row"><h4 class="grow">${esc(p.method_name)} · ৳${money(p.amount)}</h4>${st(p.status)}</div>
          <p>${esc(p.user_phone)} · Trx ${esc(p.trx)}</p>
          ${p.status === "pending" ? `<div class="acts">
            <button class="btn sm ok" data-pay="${esc(p.id)}" data-do="approve">অ্যাপ্রুভ</button>
            <button class="btn sm bad" data-pay="${esc(p.id)}" data-do="reject">রিজেক্ট</button>
          </div>` : ""}
        </div>`).join("") : `<div class="card">পেন্ডিং পেমেন্ট নেই</div>`}</div>`;
  }

  function viewOrders() {
    const list = S.orders || [];
    return `
      <div class="chips">
        ${["pending", "confirmed", "cancelled", ""].map((x) => `<button class="chip ${S.filter === x ? "on" : ""}" data-flt="${x}">${x === "" ? "সব" : x === "pending" ? "পেন্ডিং" : x === "confirmed" ? "কনফার্ম" : "ক্যান্সেল"}</button>`).join("")}
      </div>
      <div class="search"><input id="oq" placeholder="নম্বর / কোড / ইউজার" value="${esc(S.q)}" /></div>
      <div class="list">${list.map((o) => `
        <div class="item">
          <div class="row">
            <h4 class="grow">${esc(o.title)} · ${esc(o.number)}</h4>
            ${st(o.status)}
          </div>
          <p>${esc(o.code)} · ৳${money(o.amount)}${o.commission ? " · কমিশন ৳" + money(o.commission) : ""} · ${esc(o.user_phone)} · ${esc(o.created_at)}</p>
          <div class="acts">
            <button class="btn sm ghost" data-copy-id="${esc(o.id)}">হাউজ কপি</button>
            ${o.status === "pending" ? `
              <button class="btn sm ok" data-ord="${esc(o.id)}" data-do="confirm">কনফার্ম</button>
              <button class="btn sm bad" data-ord="${esc(o.id)}" data-do="cancel">ক্যান্সেল</button>` : ""}
          </div>
        </div>`).join("") || `<div class="card">কোনো অর্ডার নেই</div>`}</div>`;
  }

  function viewOffers() {
    const groups = [];
    const map = new Map();
    const filtered = (S.offers || []).filter((o) => {
      if (S.offOp !== "all" && o.operator !== S.offOp) return false;
      if (S.offType !== "all" && o.type !== S.offType) return false;
      if (S.offCat !== "all" && (o.category || "internet") !== S.offCat) return false;
      return true;
    });
    for (const o of filtered) {
      const k = o.operator || "_";
      if (!map.has(k)) {
        const g = [];
        map.set(k, g);
        groups.push([k, g]);
      }
      map.get(k).push(o);
    }
    groups.forEach(([, list]) => list.sort((a, b) => {
      const ta = a.type === "drive" ? 1 : 0;
      const tb = b.type === "drive" ? 1 : 0;
      return ta - tb || (a.sort || 0) - (b.sort || 0);
    }));
    const ops = S.operators || [];
    return `
      <div class="row"><button class="btn" id="newoffer">নতুন অফার</button></div>
      <div class="ops">
        <button type="button" class="op ${S.offOp === "all" ? "on" : ""}" data-offop="all"><div class="av" style="background:#0f766e">সব</div><span>সকল</span></button>
        ${ops.map((o) => `<button type="button" class="op ${S.offOp === o.id ? "on" : ""}" data-offop="${esc(o.id)}"><div class="av" style="background:${esc(o.color)}">${esc(o.short)}</div><span>${esc(o.name_bn)}</span></button>`).join("")}
      </div>
      <div class="chips">
        <div class="chip-row">
          <button type="button" class="chip ${S.offType === "all" ? "on" : ""}" data-offtype="all">সব ধরন</button>
          <button type="button" class="chip ${S.offType === "regular" ? "on" : ""}" data-offtype="regular">রেগুলার</button>
          <button type="button" class="chip ${S.offType === "drive" ? "on" : ""}" data-offtype="drive">ড্রাইভ</button>
        </div>
        <label class="cat-wrap">
          <select class="cat-filter ${S.offCat !== "all" ? "on" : ""}" id="offcat" aria-label="ক্যাটাগরি ফিল্টার">
            <option value="all" ${S.offCat === "all" ? "selected" : ""}>সব ক্যাটাগরি</option>
            <option value="internet" ${S.offCat === "internet" ? "selected" : ""}>ইন্টারনেট</option>
            <option value="minutes" ${S.offCat === "minutes" ? "selected" : ""}>মিনিট</option>
            <option value="combo" ${S.offCat === "combo" ? "selected" : ""}>কম্বো</option>
            <option value="recharge" ${S.offCat === "recharge" ? "selected" : ""}>রিচার্জ</option>
          </select>
        </label>
      </div>
      ${groups.length ? groups.map(([k, list]) => {
        const op = S.operators.find((x) => x.id === k) || { short: k, name_bn: k };
        return `<div class="nav-label">${esc(op.short)} · ${esc(op.name_bn || "")}</div>
      <div class="list">${list.map((o) => {
        const isD = o.type === "drive";
        const pDrv = isD ? Number(o.drive_price || o.price || 0) : 0;
        const pReg = Number(o.regular_price || (isD ? pDrv + Number(o.commission || 0) : o.price) || 0);
        const pCom = isD ? Math.max(0, pReg - pDrv) : 0;
        const line = isD
          ? `রেগুলার: ৳${money(pReg)} অফার: ৳${money(pDrv)} কমিশন: ৳${money(pCom)}`
          : `রেগুলার: ৳${money(pReg)}`;
        return `<div class="item">
          <div class="row"><h4 class="grow">${esc([op.short, o.volume, o.validity].filter(Boolean).join(" • "))}</h4>${st(o.status)}</div>
          <p>${line}</p>
          <div class="acts">
            <button class="btn sm ghost" data-edito="${esc(o.id)}">এডিট</button>
            <button class="btn sm ghost" data-clone="${esc(o.id)}">ক্লোন</button>
            <button class="btn sm bad" data-delo="${esc(o.id)}">ডিলিট</button>
          </div>
        </div>`;
      }).join("")}</div>`;
      }).join("") : `<div class="card">এই ফিল্টারে কোনো অফার নেই</div>`}`;
  }

  function offerForm(o = {}) {
    const ops = S.operators.map((x) => `<option value="${esc(x.id)}" ${o.operator === x.id ? "selected" : ""}>${esc(x.name_bn)}</option>`).join("");
    const isD = o.type === "drive";
    const drv = isD ? Number(o.drive_price || o.price || 0) : 0;
    let reg = Number(o.regular_price || 0);
    if (reg <= 0) reg = isD ? drv + Number(o.commission || 0) : Number(o.price || 0);
    return `<h3 style="margin-bottom:10px">${o.id ? "অফার এডিট" : "নতুন অফার"}</h3>
      <input type="hidden" id="oid" name="id" value="${esc(o.id || "")}" />
      <div class="grid2">
        <div class="field"><label>অপারেটর</label><select id="o_op" name="operator">${ops}</select></div>
        <div class="field"><label>ধরন</label><select id="o_type" name="type"><option value="regular" ${!isD ? "selected" : ""}>রেগুলার</option><option value="drive" ${isD ? "selected" : ""}>ড্রাইভ</option></select></div>
        <div class="field"><label>ক্যাটাগরি</label><select id="o_cat">${[["internet","ইন্টারনেট"],["minutes","মিনিট"],["combo","কম্বো"],["recharge","রিচার্জ"]].map(([c,l]) => `<option value="${c}" ${o.category === c ? "selected" : ""}>${l}</option>`).join("")}</select></div>
        <div class="field"><label>স্ট্যাটাস</label><select id="o_st"><option value="active" ${o.status !== "inactive" ? "selected" : ""}>অন</option><option value="inactive" ${o.status === "inactive" ? "selected" : ""}>অফ</option></select></div>
        <div class="field"><label>ভলিউম</label><input id="o_vol" name="volume" value="${esc(o.volume || "")}" placeholder="5 GB" /></div>
        <div class="field"><label>ভ্যালিডিটি</label><input id="o_val" name="validity" value="${esc(o.validity || "")}" placeholder="৭ দিন" /></div>
        <div class="field"><label>রেগুলার মূল্য</label><input id="o_reg" name="regular_price" inputmode="decimal" value="${reg > 0 ? esc(reg) : ""}" /></div>
        <div class="field" id="o_drvwrap" style="${isD ? "" : "display:none"}"><label>ড্রাইভ / অফার মূল্য</label><input id="o_drv" name="drive_price" inputmode="decimal" value="${isD && drv > 0 ? esc(drv) : ""}" /></div>
        <p id="o_comv" style="grid-column:1/-1;color:#047857;font-size:12.5px;font-weight:700;margin:-2px 0 8px;${isD ? "" : "display:none"}">কমিশন ৳0</p>
        <div class="field"><label>নোট</label><input id="o_note" value="${esc(o.note || "")}" /></div>
        <div class="field"><label>সর্ট</label><input id="o_sort" inputmode="numeric" value="${esc(o.sort ?? 0)}" /></div>
      </div>
      <button class="btn block" id="osave">সেভ</button>
      <button class="btn ghost block" style="margin-top:8px" id="xclose">বন্ধ</button>`;
  }

  function viewUsers() {
    return `<div class="list">${(S.users || []).map((u) => `
      <div class="item">
        <div class="row"><h4 class="grow">${esc(u.name)} · ${esc(u.phone)}</h4>${st(u.status)}${u.role === "admin" ? ' <span class="st confirmed">স্টাফ</span>' : ""}</div>
        <p>৳${money(u.available)} (−${money(u.pending)}) · মোট ৳${money(u.balance)}</p>
        <div class="acts">
          <button class="btn sm ok" data-bal="${esc(u.id)}" data-mode="add">ব্যালেন্স +</button>
          <button class="btn sm ghost" data-role="${esc(u.id)}" data-next="${u.role === "admin" ? "user" : "admin"}">${u.role === "admin" ? "স্টাফ সরান" : "স্টাফ করুন"}</button>
          <button class="btn sm ghost" data-tog="${esc(u.id)}" data-st="${u.status === "blocked" ? "active" : "blocked"}">${u.status === "blocked" ? "আনব্লক" : "ব্লক"}</button>
        </div>
      </div>`).join("")}</div>`;
  }

  function viewPay() {
    return `<div class="list">${(S.payments || []).map((p) => `
      <div class="item">
        <div class="row"><h4 class="grow">${esc(p.method_name)} · ৳${money(p.amount)}</h4>${st(p.status)}</div>
        <p>${esc(p.user_phone)} · Trx ${esc(p.trx)} · ${esc(p.sender)} · ${esc(p.created_at)}</p>
        ${p.status === "pending" ? `<div class="acts">
          <button class="btn sm ok" data-pay="${esc(p.id)}" data-do="approve">অ্যাপ্রুভ</button>
          <button class="btn sm bad" data-pay="${esc(p.id)}" data-do="reject">রিজেক্ট</button>
        </div>` : ""}
      </div>`).join("") || `<div class="card">কোনো পেমেন্ট নেই</div>`}</div>`;
  }

  function viewMethods() {
    return `<div class="list">${(S.methods || []).map((m) => `
      <div class="item">
        <div class="row"><h4 class="grow">${esc(m.name_bn || m.name)}</h4>${st(m.status)}${m.auto ? ' <span class="st confirmed">অটো</span>' : ""}</div>
        <p>${esc(m.number)} · ${esc(m.type)} · মিন ৳${money(m.min)}</p>
        <div class="acts"><button class="btn sm ghost" data-editm="${esc(m.id)}">এডিট</button></div>
      </div>`).join("")}</div>`;
  }

  function methodForm(m) {
    return `<h3 style="margin-bottom:10px">${esc(m.name_bn)}</h3>
      <input type="hidden" id="mid" value="${esc(m.id)}" />
      <div class="field"><label>নম্বর / অ্যাকাউন্ট</label><input id="m_num" value="${esc(m.number || "")}" /></div>
      <div class="field"><label>টাইপ</label><input id="m_type" value="${esc(m.type || "")}" /></div>
      <div class="field"><label>ব্যাংক নাম</label><input id="m_bank" value="${esc(m.bank_name || "")}" /></div>
      <div class="field"><label>অ্যাকাউন্ট নাম</label><input id="m_acc" value="${esc(m.account_name || "")}" /></div>
      <div class="field"><label>ইনস্ট্রাকশন</label><textarea id="m_ins">${esc(m.instructions || "")}</textarea></div>
      <div class="grid2">
        <div class="field"><label>মিন</label><input id="m_min" value="${esc(m.min ?? 50)}" /></div>
        <div class="field"><label>স্ট্যাটাস</label><select id="m_st"><option value="active" ${m.status !== "inactive" ? "selected" : ""}>অন</option><option value="inactive">অফ</option></select></div>
      </div>
      <div class="field"><label>অটো অ্যাপ্রুভ (টেস্ট)</label><select id="m_auto"><option value="0" ${!m.auto ? "selected" : ""}>বন্ধ</option><option value="1" ${m.auto ? "selected" : ""}>চালু</option></select></div>
      <button class="btn block" id="msave">সেভ</button>
      <button class="btn ghost block" style="margin-top:8px" id="xclose">বন্ধ</button>`;
  }

  function viewOps() {
    return `<div class="list">${(S.operators || []).map((o) => `
      <div class="item">
        <div class="row"><h4 class="grow">${esc(o.name_bn)} (${esc(o.short)})</h4>${st(o.status)}</div>
        <p>প্রিফিক্স: ${(o.prefixes || []).join(", ")}</p>
        <div class="acts"><button class="btn sm ghost" data-edito2="${esc(o.id)}">এডিট</button></div>
      </div>`).join("")}</div>`;
  }

  function viewBills() {
    return `<div class="list">${(S.bills || []).map((b) => `
      <div class="item">
        <div class="row"><h4 class="grow">${esc(b.name_bn)} (${esc(b.short || b.id)})</h4>${st(b.status)}</div>
        <p>${esc(b.type)} · ${esc(b.hint || "")}</p>
        <div class="acts"><button class="btn sm ghost" data-editb="${esc(b.id)}">এডিট</button></div>
      </div>`).join("")}</div>`;
  }

  function viewNote() {
    return `<div class="card">
      <div class="field"><label>টাইটেল</label><input id="nt" /></div>
      <div class="field"><label>মেসেজ</label><textarea id="nb"></textarea></div>
      <button class="btn block" id="nsend">সবাইকে পাঠান</button>
    </div>`;
  }

  function viewLogs() {
    return `<div class="list">${(S.logs || []).slice(0, 120).map((l) => `
      <div class="item"><h4>${esc(l.action)}</h4><p>${esc(l.actor)} · ${esc(l.detail)} · ${esc(l.at)} · ${esc(l.ip)}</p></div>
    `).join("")}</div>`;
  }

  function viewSet() {
    const s = S.settings || {};
    return `<div class="card grid2">
      <div class="field"><label>সাইট নাম</label><input id="s_name" value="${esc(s.site_name || "")}" /></div>
      <div class="field"><label>ট্যাগলাইন</label><input id="s_tag" value="${esc(s.tagline || "")}" /></div>
      <div class="field"><label>সাপোর্ট ফোন</label><input id="s_ph" value="${esc(s.support_phone || "")}" /></div>
      <div class="field"><label>হোয়াটসঅ্যাপ</label><input id="s_wa" value="${esc(s.whatsapp || "")}" /></div>
      <div class="field" style="grid-column:1/-1"><label>নোটিশ</label><textarea id="s_notice">${esc(s.notice || "")}</textarea></div>
      <div class="field" style="grid-column:1/-1"><label>মার্কি মেসেজ</label><textarea id="s_tick">${esc(s.ticker || "")}</textarea></div>
      <div class="field" style="grid-column:1/-1"><label>হাউজ কপি টেমপ্লেট</label><textarea id="s_tpl">${esc(s.house_template || "")}</textarea></div>
      <div class="field"><label>মিন ডিপোজিট</label><input id="s_md" value="${esc(s.min_deposit ?? 50)}" /></div>
      <div class="field"><label>মিন রিচার্জ</label><input id="s_mr" value="${esc(s.min_recharge ?? 10)}" /></div>
      <div class="field"><label>ম্যাক্স রিচার্জ</label><input id="s_xr" value="${esc(s.max_recharge ?? 5000)}" /></div>
      <div class="field"><label>মিন উইথড্র</label><input id="s_mw" value="${esc(s.min_withdraw ?? 100)}" /></div>
      <div class="field"><label>ম্যাক্স উইথড্র</label><input id="s_xw" value="${esc(s.max_withdraw ?? 50000)}" /></div>
      <div class="field"><label>মিন বিল</label><input id="s_mb" value="${esc(s.min_bill ?? 50)}" /></div>
      <div class="field"><label>ম্যাক্স বিল</label><input id="s_xb" value="${esc(s.max_bill ?? 20000)}" /></div>
      <div class="field"><label>মিন ট্রান্সফার</label><input id="s_mt" value="${esc(s.min_transfer ?? 10)}" /></div>
      <div class="field"><label>নতুন অ্যাডমিন কী</label><input id="s_pw" type="password" placeholder="খালি রাখলে বদলাবে না" /></div>
    </div>
    <button class="btn block" id="ssave">সেটিংস সেভ</button>`;
  }

  function overlay(html) {
    S.overlay = html;
    drawO();
  }
  function closeO() { S.overlay = null; $$(".overlay").forEach((e) => e.remove()); }
  function drawO() {
    $$(".overlay").forEach((e) => e.remove());
    if (!S.overlay) return;
    const d = document.createElement("div");
    d.className = "overlay";
    d.innerHTML = `<div class="sheet">${S.overlay}</div>`;
    d.addEventListener("click", (e) => { if (e.target === d) closeO(); });
    document.body.appendChild(d);
    bind(d);
  }

  function render() {
    const root = rootEl();
    if (!root) return;
    if (!S.admin) { root.innerHTML = gate(); bind(root); return; }
    let inner = "";
    switch (S.page) {
      case "orders": inner = viewOrders(); break;
      case "offers": inner = viewOffers(); break;
      case "users": inner = viewUsers(); break;
      case "pay": inner = viewPay(); break;
      case "methods": inner = viewMethods(); break;
      case "ops": inner = viewOps(); break;
      case "bills": inner = viewBills(); break;
      case "note": inner = viewNote(); break;
      case "logs": inner = viewLogs(); break;
      case "set": inner = viewSet(); break;
      default: inner = viewDash();
    }
    root.innerHTML = shell(inner);
    bind(root);
    drawO();
    if (S.menu) setMenu(true);
  }

  function setMenu(on) {
    S.menu = !!on;
    const d = document.getElementById("drawer");
    if (d) d.classList.toggle("on", S.menu);
    document.body.style.overflow = S.menu ? "hidden" : "";
  }

  function bind(r) {
    const g = $("#gform", r);
    if (g) g.addEventListener("submit", async (e) => {
      e.preventDefault();
      const res = await api("admin_login", { username: $("#gu").value, password: $("#gp").value });
      if (!res.ok) return toast(res.error || "ব্যর্থ", "bad");
      S.token = res.token; S.admin = res.admin; localStorage.setItem("oh_at", res.token);
      await bootAdmin();
    });
    $$("[data-go]", r).forEach((b) => b.addEventListener("click", () => { setMenu(false); goto(b.dataset.go); }));
    const out = $("#out", r); const out2 = $("#out2", r);
    const bye = async () => {
      await api("admin_logout");
      S.token = ""; S.admin = null;
      localStorage.removeItem("oh_at");
      localStorage.removeItem("oh_is_admin");
      localStorage.removeItem("oh_token");
      if (window.top !== window) {
        window.top.location.replace("./");
        return;
      }
      if (/nx\.php$/i.test(location.pathname)) {
        render();
        return;
      }
      location.replace("./");
    };
    if (out) out.addEventListener("click", bye);
    if (out2) out2.addEventListener("click", bye);
    const hamb = $("#hamb", r);
    if (hamb) hamb.addEventListener("click", () => setMenu(true));
    const dclose = $("#dclose", r);
    if (dclose) dclose.addEventListener("click", () => setMenu(false));
    const ddim = $("#ddim", r);
    if (ddim) ddim.addEventListener("click", () => setMenu(false));
    $$("[data-offop]", r).forEach((b) => b.addEventListener("click", () => {
      S.offOp = b.dataset.offop || "all";
      if (S.page === "offers") render();
    }));
    $$("[data-offtype]", r).forEach((b) => b.addEventListener("click", () => {
      S.offType = b.dataset.offtype || "all";
      if (S.page === "offers") render();
    }));
    const offcat = $("#offcat", r);
    if (offcat) offcat.addEventListener("change", () => {
      S.offCat = offcat.value || "all";
      if (S.page === "offers") render();
    });
    $$("[data-flt]", r).forEach((b) => b.addEventListener("click", () => { S.filter = b.dataset.flt; loadOrders(); }));
    const oq = $("#oq", r);
    if (oq) oq.addEventListener("change", () => { S.q = oq.value; loadOrders(); });
    $$("[data-ord]", r).forEach((b) => b.addEventListener("click", async () => {
      const res = await api("admin_order", { id: b.dataset.ord, do: b.dataset.do });
      if (!res.ok) return toast(res.error, "bad");
      toast("আপডেট হয়েছে");
      if (S.page === "dash") { await loadStats(); await loadDashQueues(); render(); }
      else { loadOrders(); loadStats(); }
    }));
    $$("[data-copy-id]", r).forEach((b) => b.addEventListener("click", async () => {
      const res = await api("admin_copy", { id: b.dataset.copyId });
      if (!res.ok) return toast(res.error, "bad");
      copy(res.text);
      overlay(`<h3>হাউজ কপি</h3><div class="copybox">${esc(res.text)}</div><button class="btn block" data-copy="${esc(res.text)}">আবার কপি</button><button class="btn ghost block" style="margin-top:8px" id="xclose">বন্ধ</button>`);
    }));
    $$("[data-copy]", r).forEach((b) => b.addEventListener("click", () => copy(b.dataset.copy)));
    const xclose = $("#xclose", r);
    if (xclose) xclose.addEventListener("click", closeO);
    const newo = $("#newoffer", r);
    if (newo) newo.addEventListener("click", async () => {
      if (!S.operators.length) {
        const b = await api("admin_boot");
        if (b.ok) { S.operators = b.operators || []; S.methods = b.methods || S.methods; }
      }
      overlay(offerForm({}));
    });
    $$("[data-edito]", r).forEach((b) => b.addEventListener("click", () => {
      const o = S.offers.find((x) => x.id === b.dataset.edito);
      if (o) overlay(offerForm(o));
    }));
    $$("[data-clone]", r).forEach((b) => b.addEventListener("click", () => {
      const o = { ...(S.offers.find((x) => x.id === b.dataset.clone) || {}) };
      o.id = "";
      overlay(offerForm(o));
    }));
    $$("[data-delo]", r).forEach((b) => b.addEventListener("click", async () => {
      if (!confirm("ডিলিট করবেন?")) return;
      await api("admin_offer_del", { id: b.dataset.delo });
      loadOffers();
    }));
    const syncCom = () => {
      const box = document.querySelector(".overlay .sheet") || r;
      const el = box.querySelector("#o_comv");
      if (!el) return;
      const type = box.querySelector("#o_type")?.value || "regular";
      const wrap = box.querySelector("#o_drvwrap");
      if (wrap) wrap.style.display = type === "drive" ? "" : "none";
      el.style.display = type === "drive" ? "" : "none";
      const reg = Number(box.querySelector("#o_reg")?.value || 0);
      const drv = Number(box.querySelector("#o_drv")?.value || 0);
      const com = type === "drive" && reg > 0 && drv > 0 ? Math.max(0, Math.round((reg - drv) * 100) / 100) : 0;
      el.textContent = "কমিশন ৳" + money(com) + "  ·  রেগুলার − ড্রাইভ";
    };
    ["#o_reg", "#o_drv", "#o_type"].forEach((id) => {
      const n = $(id, r);
      if (n) n.addEventListener(id === "#o_type" ? "change" : "input", syncCom);
    });
    syncCom();
    const osave = $("#osave", r);
    if (osave) osave.addEventListener("click", async () => {
      const box = osave.closest(".sheet") || document.querySelector(".overlay .sheet") || r;
      const val = (sel) => String(box.querySelector(sel)?.value || "").trim();
      if (!S.operators.length) {
        const b = await api("admin_boot");
        if (b.ok) {
          S.operators = b.operators || [];
          if (Array.isArray(b.offers) && b.offers.length) S.offers = b.offers;
        }
      }
      let operator = val("#o_op");
      if (!operator && S.operators[0]) operator = S.operators[0].id;
      const volume = val("#o_vol");
      const validity = val("#o_val") || "—";
      const type = val("#o_type") || "regular";
      const regular_price = Number(val("#o_reg") || 0);
      const drive_price = type === "drive" ? Number(val("#o_drv") || 0) : 0;
      const price = type === "drive" ? drive_price : regular_price;
      if (!operator) return toast("অপারেটর বাছাই করুন", "bad");
      if (!volume) return toast("ভলিউম ও ভ্যালিডিটি দিন", "bad");
      if (type === "drive") {
        if (!(regular_price > 0) || !(drive_price > 0)) return toast("রেগুলার ও ড্রাইভ মূল্য দিন", "bad");
        if (!(drive_price < regular_price)) return toast("ড্রাইভ মূল্য রেগুলারের চেয়ে কম হতে হবে", "bad");
      } else if (!(regular_price > 0)) {
        return toast("রেগুলার মূল্য দিন", "bad");
      }
      const title = [volume, validity].filter(Boolean).join(" · ");
      const res = await api("admin_offer", {
        id: val("#oid"), operator, type, category: val("#o_cat") || "internet",
        title, volume, validity,
        regular_price, drive_price, price,
        note: val("#o_note"),
        status: val("#o_st") || "active", sort: val("#o_sort") || 0,
      });
      if (!res.ok) return toast(res.error || "সেভ হয়নি", "bad");
      toast("সেভ হয়েছে"); closeO(); loadOffers();
    });
    $$("[data-bal]", r).forEach((b) => b.addEventListener("click", async () => {
      const amt = prompt("যোগ করার পরিমাণ");
      if (!amt) return;
      const res = await api("admin_balance", { id: b.dataset.bal, amount: amt, mode: "add" });
      if (!res.ok) return toast(res.error, "bad");
      toast("ব্যালেন্স আপডেট"); loadUsers();
    }));
    $$("[data-tog]", r).forEach((b) => b.addEventListener("click", async () => {
      await api("admin_user", { id: b.dataset.tog, status: b.dataset.st });
      loadUsers();
    }));
    $$("[data-role]", r).forEach((b) => b.addEventListener("click", async () => {
      await api("admin_user", { id: b.dataset.role, role: b.dataset.next });
      toast(b.dataset.next === "admin" ? "এই নম্বরে লগইন করলেই কনসোল খুলবে" : "স্টাফ অ্যাকসেস সরানো হয়েছে");
      loadUsers();
    }));
    $$("[data-pay]", r).forEach((b) => b.addEventListener("click", async () => {
      const res = await api("admin_payment", { id: b.dataset.pay, do: b.dataset.do });
      if (!res.ok) return toast(res.error, "bad");
      toast("পেমেন্ট আপডেট");
      if (S.page === "dash") { await loadStats(); await loadDashQueues(); render(); }
      else { loadPay(); loadStats(); }
    }));
    $$("[data-editm]", r).forEach((b) => {
      b.addEventListener("click", () => {
        const m = S.methods.find((x) => x.id === b.dataset.editm);
        if (m) overlay(methodForm(m));
      });
    });
    const msave = $("#msave", r);
    if (msave) msave.addEventListener("click", async () => {
      const res = await api("admin_methods", {
        id: $("#mid").value, number: $("#m_num").value, type: $("#m_type").value,
        bank_name: $("#m_bank").value, account_name: $("#m_acc").value, instructions: $("#m_ins").value,
        min: $("#m_min").value, status: $("#m_st").value, auto: $("#m_auto").value === "1",
      });
      if (!res.ok) return toast(res.error, "bad");
      S.methods = res.methods; toast("সেভ"); closeO(); render();
    });
    $$("[data-edito2]", r).forEach((b) => b.addEventListener("click", () => {
      const o = S.operators.find((x) => x.id === b.dataset.edito2);
      if (!o) return;
      overlay(`<h3>${esc(o.name_bn)}</h3>
        <div class="field"><label>নাম</label><input id="opn" value="${esc(o.name_bn)}" /></div>
        <div class="field"><label>শর্ট</label><input id="ops" value="${esc(o.short)}" /></div>
        <div class="field"><label>প্রিফিক্স (কমা)</label><input id="opp" value="${esc((o.prefixes || []).join(","))}" /></div>
        <div class="field"><label>কালার</label><input id="opc" value="${esc(o.color)}" /></div>
        <div class="field"><label>স্ট্যাটাস</label><select id="opst"><option value="active">অন</option><option value="inactive" ${o.status === "inactive" ? "selected" : ""}>অফ</option></select></div>
        <button class="btn block" id="opsave" data-id="${esc(o.id)}">সেভ</button>
        <button class="btn ghost block" style="margin-top:8px" id="xclose">বন্ধ</button>`);
    }));
    const opsave = $("#opsave", r);
    if (opsave) opsave.addEventListener("click", async () => {
      const res = await api("admin_operators", {
        id: opsave.dataset.id, name_bn: $("#opn").value, short: $("#ops").value,
        prefixes: $("#opp").value, color: $("#opc").value, status: $("#opst").value,
      });
      if (res.ok) { S.operators = res.operators; toast("সেভ"); closeO(); render(); }
    });
    $$("[data-editb]", r).forEach((b) => b.addEventListener("click", () => {
      const o = (S.bills || []).find((x) => x.id === b.dataset.editb);
      if (!o) return;
      overlay(`<h3>${esc(o.name_bn)}</h3>
        <div class="field"><label>নাম</label><input id="bnm" value="${esc(o.name_bn)}" /></div>
        <div class="field"><label>শর্ট</label><input id="bsh" value="${esc(o.short || o.id)}" /></div>
        <div class="field"><label>হিন্ট</label><input id="bht" value="${esc(o.hint || "")}" /></div>
        <div class="field"><label>কালার</label><input id="bcl" value="${esc(o.color || "#0f766e")}" /></div>
        <div class="field"><label>স্ট্যাটাস</label><select id="bst"><option value="active">অন</option><option value="inactive" ${o.status === "inactive" ? "selected" : ""}>অফ</option></select></div>
        <button class="btn block" id="bsave" data-id="${esc(o.id)}">সেভ</button>
        <button class="btn ghost block" style="margin-top:8px" id="xclose">বন্ধ</button>`);
    }));
    const bsave = $("#bsave", r);
    if (bsave) bsave.addEventListener("click", async () => {
      const res = await api("admin_bills", {
        id: bsave.dataset.id, name_bn: $("#bnm").value, short: $("#bsh").value,
        hint: $("#bht").value, color: $("#bcl").value, status: $("#bst").value,
      });
      if (res.ok) { S.bills = res.bills; toast("সেভ"); closeO(); render(); }
    });
    const nsend = $("#nsend", r);
    if (nsend) nsend.addEventListener("click", async () => {
      const res = await api("admin_notify", { title: $("#nt").value, body: $("#nb").value, to: "all" });
      if (!res.ok) return toast(res.error, "bad");
      toast("পাঠানো হয়েছে"); $("#nt").value = ""; $("#nb").value = "";
    });
    const ssave = $("#ssave", r);
    if (ssave) ssave.addEventListener("click", async () => {
      const res = await api("admin_settings", {
        site_name: $("#s_name").value, tagline: $("#s_tag").value, support_phone: $("#s_ph").value,
        whatsapp: $("#s_wa").value, notice: $("#s_notice").value, ticker: $("#s_tick") ? $("#s_tick").value : "", house_template: $("#s_tpl").value,
        min_deposit: $("#s_md").value, min_recharge: $("#s_mr").value, max_recharge: $("#s_xr").value,
        min_withdraw: $("#s_mw")?.value, max_withdraw: $("#s_xw")?.value, min_bill: $("#s_mb")?.value, max_bill: $("#s_xb")?.value, min_transfer: $("#s_mt")?.value,
        admin_password: $("#s_pw").value,
      });
      if (!res.ok) return toast(res.error, "bad");
      S.settings = res.settings; toast("সেটিংস সেভ");
    });
  }

  async function loadStats() { const r = await api("admin_stats"); if (r.ok) S.stats = r.stats; }
  async function loadOrders() {
    const r = await api("admin_orders", { status: S.filter, q: S.q });
    if (r.ok) { S.orders = r.orders; if (S.page === "orders") render(); }
  }
  async function loadOffers() { const r = await api("admin_offers"); if (r.ok) { S.offers = r.offers; if (S.page === "offers") render(); } }
  async function loadUsers() { const r = await api("admin_users"); if (r.ok) { S.users = r.users; if (S.page === "users") render(); } }
  async function loadPay() { const r = await api("admin_payments"); if (r.ok) { S.payments = r.payments; if (S.page === "pay") render(); } }
  async function loadLogs() { const r = await api("admin_logs"); if (r.ok) { S.logs = r.logs; if (S.page === "logs") render(); } }

  async function loadDashQueues() {
    const o = await api("admin_orders", { status: "pending", q: "" });
    if (o.ok) S.dashOrders = o.orders || [];
    const p = await api("admin_payments");
    if (p.ok) S.dashPays = (p.payments || []).filter((x) => x.status === "pending");
  }

  async function loadPage() {
    if (S.page === "dash") {
      await loadStats();
      await loadDashQueues();
    }
    if (S.page === "orders") await loadOrders();
    if (S.page === "offers") await loadOffers();
    if (S.page === "users") await loadUsers();
    if (S.page === "pay") await loadPay();
    if (S.page === "logs") await loadLogs();
    render();
  }

  async function bootAdmin() {
    const r = await api("admin_boot");
    if (!r.ok) { S.admin = null; render(); return; }
    S.admin = r.admin; S.settings = r.settings; S.operators = r.operators; S.methods = r.methods;
    if (Array.isArray(r.bills)) S.bills = r.bills;
    if (Array.isArray(r.offers) && r.offers.length) S.offers = r.offers;
    await loadPage();
  }

  (async () => {
    window.AdminApp = {
      start(token) {
        if (token) {
          S.token = token;
          localStorage.setItem("oh_at", token);
        }
        const app = document.getElementById("app");
        const root = rootEl();
        if (app) app.style.display = "none";
        if (root) {
          root.style.display = "block";
          root.style.minHeight = "100vh";
        }
        document.documentElement.classList.add("admin-mode");
        if (S.token) bootAdmin();
        else render();
      }
    };
    if (!document.getElementById("app")) {
      if (S.token) await bootAdmin();
      else render();
    }
  })();

  setInterval(async () => {
    if (!S.admin) return;
    if (S.menu || S.overlay) return;
    if (S.page === "orders") loadOrders();
    if (S.page === "pay") loadPay();
    if (S.page === "dash") {
      await loadStats();
      await loadDashQueues();
      render();
    }
  }, 5000);
})();
