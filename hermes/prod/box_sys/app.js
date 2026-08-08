/* Hermes · two fields that matter: address + payload */
(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);
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

  async function refreshInboxes() {
    const j = await api("/api/inboxes");
    const ul = $("inboxList");
    if (!ul) return;
    ul.innerHTML = "";
    if (!j.ok) {
      status(j.error || "pocket offline · start Pocket Desktop", false);
      return;
    }
    const rows = j.inboxes || [];
    if (!rows.length) {
      status("no inboxes · on desk: -ins inbox", false);
      return;
    }
    status(rows.length + " inbox(es) on pocket", true);
    rows.forEach((row) => {
      const li = document.createElement("li");
      const n = (row.mail && row.mail.length) || row.count || 0;
      li.textContent =
        (row.address || row.uid || "?") +
        (row.hasMail || n ? " · mail " + n : " · empty");
      li.title = "click to fill To";
      li.onclick = () => {
        if ($("addr") && row.address) $("addr").value = row.address;
      };
      ul.appendChild(li);
    });
  }

  async function send() {
    const address = ($("addr") && $("addr").value || "").trim();
    const title = ($("subj") && $("subj").value || "").trim() || "letter";
    const body = ($("body") && $("body").value) || "";
    const from = ($("from") && $("from").value || "").trim() || "Hermes";
    if (!address) {
      status("need an inbox address", false);
      return;
    }
    status("sending…");
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
    status(
      "delivered · " +
        (j.leaf_id || "leaf") +
        " → " +
        (j.address || address) +
        " (" +
        (j.mail_count || "?") +
        " waiting) · go take it out of the box",
      true
    );
    refreshInboxes();
  }

  function boot() {
    const btn = $("btnSend");
    const ref = $("btnRefresh");
    if (btn) btn.onclick = send;
    if (ref) ref.onclick = refreshInboxes;
    refreshInboxes();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();
