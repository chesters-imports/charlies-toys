/* Hermes · letter mint OR desk parcel (leaf / card / envelope) */
(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);
  let mode = "letter"; // letter | parcel

  const status = (msg, ok) => {
    const el = $("status");
    if (!el) return;
    el.textContent = msg || "—";
    el.style.color = ok === false ? "#802020" : ok === true ? "#1a5038" : "";
  };

  async function api(path, opts) {
    const r = await fetch(path, opts);
    const j = await r.json().catch(() => ({}));
    return j;
  }

  function setMode(next) {
    mode = next === "parcel" ? "parcel" : "letter";
    const letter = $("letterFields");
    const parcel = $("parcelFields");
    if (letter) letter.hidden = mode !== "letter";
    if (parcel) parcel.hidden = mode !== "parcel";
    document.querySelectorAll(".hm-mode-btn").forEach((btn) => {
      btn.classList.toggle("is-on", btn.getAttribute("data-mode") === mode);
    });
    if (mode === "parcel") {
      refreshCatalog();
    }
  }

  async function refreshInboxes() {
    const j = await api("/api/inboxes");
    const ul = $("inboxList");
    if (!ul) return;
    ul.innerHTML = "";
    if (!j.ok) {
      status(j.error || "SophiaDesk offline · start the desk", false);
    }
    const rows = j.inboxes || [];
    const stations = j.stations || [];
    if (!rows.length && !stations.length) {
      status(
        j.ok
          ? "no inboxes · install inbox on desk · or letter To: drx"
          : j.error || "offline",
        false
      );
      return;
    }
    const bits = [];
    if (rows.length) bits.push(rows.length + " desk box(es)");
    if (stations.length) bits.push(stations.length + " stations (letter only)");
    status(bits.join(" · ") || "ready", true);
    rows.forEach((row) => {
      const li = document.createElement("li");
      const n = (row.mail && row.mail.length) || row.count || 0;
      li.textContent =
        "DESK · " +
        (row.address || row.uid || "?") +
        (row.hasMail || n ? " · mail " + n : " · empty");
      li.title = "click → desk mailbox (letters + parcels)";
      li.onclick = () => {
        if ($("addr") && row.address) $("addr").value = row.address;
      };
      ul.appendChild(li);
    });
    stations.forEach((row) => {
      const li = document.createElement("li");
      const sid = row.id || row.address || "?";
      li.textContent = "TERMINALS · " + String(sid).toUpperCase() + " · letter only";
      li.title = "text wire only — no leaf/card/envelope parcels";
      li.onclick = () => {
        if ($("addr")) $("addr").value = sid;
        if (mode === "parcel") {
          status("parcels cannot go to TERMINALS · switched note", false);
        }
      };
      ul.appendChild(li);
    });
  }

  async function refreshCatalog() {
    const sel = $("parcel");
    if (!sel) return;
    status("loading desk parcels…");
    const j = await api("/api/catalog");
    sel.innerHTML = "";
    if (!j.ok) {
      const o = document.createElement("option");
      o.value = "";
      o.textContent = "— desk offline · start SophiaDesk —";
      sel.appendChild(o);
      status(j.error || "catalog failed · is SophiaDesk running?", false);
      return;
    }
    const items = j.items || [];
    if (!items.length) {
      const o = document.createElement("option");
      o.value = "";
      o.textContent = "— no free parcels on desk —";
      sel.appendChild(o);
      status("desk has no free leaf/card/envelope to ship", false);
      return;
    }
    const blank = document.createElement("option");
    blank.value = "";
    blank.textContent = "— pick leaf / card / envelope (" + items.length + ") —";
    sel.appendChild(blank);
    const groups = { deck: "ENVELOPES", leaf: "LEAVES", card: "CARDS" };
    ["deck", "leaf", "card"].forEach((kind) => {
      const subset = items.filter((it) => it.kind === kind);
      if (!subset.length) return;
      const og = document.createElement("optgroup");
      og.label = groups[kind] || kind;
      subset.forEach((it) => {
        const o = document.createElement("option");
        o.value = it.id;
        o.textContent = it.label || it.title || it.id;
        o.dataset.kind = it.kind;
        og.appendChild(o);
      });
      sel.appendChild(og);
    });
    status(items.length + " parcels ready on desk", true);
  }

  async function send() {
    const address = (($("addr") && $("addr").value) || "").trim();
    const from = (($("from") && $("from").value) || "").trim() || "Hermes";
    if (!address) {
      status("need To: desk address (the.archivist) or station (letter only)", false);
      return;
    }

    if (mode === "parcel") {
      const itemId = (($("parcel") && $("parcel").value) || "").trim();
      if (!itemId) {
        status("pick a parcel (leaf · card · envelope)", false);
        return;
      }
      status("shipping parcel…");
      const j = await api("/api/send", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          address: address,
          item_id: itemId,
          from: from,
        }),
      });
      if (!j.ok) {
        status(j.error || "ship failed", false);
        return;
      }
      const kind = j.kind || "parcel";
      const title = j.title || itemId;
      const n = j.member_count != null ? " · " + j.member_count + " inside" : "";
      status(
        "shipped " +
          kind +
          " · " +
          title +
          n +
          " → " +
          (j.address || address) +
          " · take out of HERMES MAIL on that desk",
        true
      );
      refreshInboxes();
      refreshCatalog();
      return;
    }

    // letter
    const title = (($("subj") && $("subj").value) || "").trim() || "letter";
    const body = ($("body") && $("body").value) || "";
    status("sending letter…");
    const j = await api("/api/send", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        address: address,
        title: title,
        body: body,
        from: from,
      }),
    });
    if (!j.ok) {
      status(j.error || "send failed", false);
      return;
    }
    if (j.via === "terminals") {
      status(
        "wire · " +
          (j.mail_id || "mail") +
          " → TERMINALS " +
          String(j.station || address).toUpperCase(),
        true
      );
    } else {
      status(
        "letter · " +
          (j.leaf_id || "leaf") +
          " → " +
          (j.address || address) +
          " · take out of the box",
        true
      );
    }
    refreshInboxes();
  }

  function boot() {
    const btn = $("btnSend");
    const ref = $("btnRefresh");
    const cat = $("btnCatalog");
    if (btn) btn.onclick = send;
    if (ref) ref.onclick = refreshInboxes;
    if (cat) cat.onclick = refreshCatalog;
    document.querySelectorAll(".hm-mode-btn").forEach((btn) => {
      btn.addEventListener("click", () => setMode(btn.getAttribute("data-mode")));
    });
    setMode("letter");
    refreshInboxes();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
