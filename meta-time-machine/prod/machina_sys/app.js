/* Time Machina · companion rail */
(() => {
  const $ = (id) => document.getElementById(id);

  let session = null;
  let rawOn = false;
  let lotsCache = [];
  let exportsDir = "";
  /** @type {null | { lot_id: string, glass_title?: string, segments: any[] }} */
  let openLot = null;

  async function api(path, opts) {
    const res = await fetch(path, {
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      ...opts,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || res.statusText || "request failed");
    return data;
  }

  function redactHtml(text) {
    const s = String(text ?? "");
    const esc = s
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
    return esc.replace(/([█]{2,}|_{3,})/g, '<span class="red">$1</span>');
  }

  function fmtTime(msg) {
    if (!msg) return "—";
    if (msg.create_time_iso) {
      return String(msg.create_time_iso)
        .replace("T", " ")
        .replace(/\+00:00$/, "")
        .slice(0, 16);
    }
    if (msg.create_time) {
      try {
        return new Date(Number(msg.create_time) * 1000)
          .toISOString()
          .replace("T", " ")
          .slice(0, 16);
      } catch {
        return String(msg.create_time);
      }
    }
    return "—";
  }

  function setLoading(on) {
    document.getElementById("app").classList.toggle("loading", !!on);
    const loader = $("loader");
    if (on) loader.removeAttribute("hidden");
    else loader.setAttribute("hidden", "");
  }

  function closeLoader() {
    openLot = null;
    setLoading(false);
  }

  function hasTextSelection() {
    try {
      const sel = window.getSelection();
      if (!sel || sel.isCollapsed || !sel.rangeCount) return false;
      return String(sel).length > 0;
    } catch {
      return false;
    }
  }

  function sameChip(a, b) {
    if (!a || !b) return false;
    return (
      a.export_id === b.export_id &&
      a.cursor === b.cursor &&
      (a.message && a.message.chip_id) === (b.message && b.message.chip_id)
    );
  }

  /** Soft tick: mats/pocket only — never rewrite message body (protects copy). */
  function renderSoft(sess) {
    if (!sess) return;
    const prev = session;
    session = sess;
    const pocket = $("btn-pocket");
    pocket.textContent = sess.pocket_on ? "pocket on" : "pocket off";
    pocket.classList.toggle("off", !sess.pocket_on);
    $("mats-n").textContent = String(sess.mats_on_chip ?? 0);
    $("book-name").textContent = sess.book_file || "—";
    noteMatsPulse(sess.mats_on_chip);
    // If chip changed under us (shouldn't while selecting), full paint next hard refresh
    if (prev && !sameChip(prev, sess) && !hasTextSelection()) {
      render(sess, { forceBody: true });
    }
  }

  function render(sess, opts) {
    opts = opts || {};
    const prev = session;
    session = sess || session;
    if (!session) return;

    const keepBody =
      !opts.forceBody &&
      prev &&
      sameChip(prev, session) &&
      hasTextSelection();

    const pocket = $("btn-pocket");
    const on = !!session.pocket_on;
    pocket.textContent = on ? "pocket on" : "pocket off";
    pocket.classList.toggle("off", !on);

    const meta = session.meta || {};
    $("m-chip").textContent = meta.export_id || session.export_id || "—";
    $("m-title").textContent = meta.title || "—";
    $("m-count").textContent =
      session.msg_count != null ? String(session.msg_count) : "—";

    let test = meta.testament || "—";
    if (meta.part != null && meta.part_count != null) {
      test = `${meta.testament || "?"} · part ${meta.part}/${meta.part_count}`;
    }
    $("m-test").textContent = test;

    const n = session.msg_count || 0;
    const cur = session.cursor || 0;
    $("m-idx").textContent = n ? `${cur + 1} / ${n}` : "—";

    const msg = session.message;
    const roleEl = $("m-role");
    if (msg) {
      roleEl.textContent = msg.role || "—";
      roleEl.className =
        "role " + (msg.role === "assistant" ? "assistant" : "user");
      $("m-chipid").textContent = msg.chip_id || "—";
      $("m-time").textContent = fmtTime(msg);
      if (!keepBody) {
        $("m-body").innerHTML = redactHtml(msg.text || "");
        $("msg-raw").textContent = JSON.stringify(msg, null, 2);
      }
    } else if (!keepBody) {
      roleEl.textContent = "—";
      roleEl.className = "role";
      $("m-chipid").textContent = "—";
      $("m-time").textContent = "—";
      $("m-body").textContent = "Load a sealed glassbox to walk time.";
      $("msg-raw").textContent = "";
    }

    $("mats-n").textContent = String(session.mats_on_chip ?? 0);
    $("book-name").textContent = session.book_file || "—";

    if (session.export_path) {
      const base = session.export_path.split(/[/\\]/).pop();
      $("load-label").innerHTML = `Load · <strong>${base}</strong>`;
    } else {
      $("load-label").textContent = "Load · pick glass log";
    }

    $("btn-prev").disabled = !n || cur <= 0;
    $("btn-next").disabled = !n || cur >= n - 1;

    const chrome = $("chrome-meta");
    if (session.export_id) {
      chrome.textContent = `${session.export_id} · ${cur + 1}/${n || 0}`;
    } else {
      chrome.textContent = "companion rail · drag here";
    }

    noteMatsPulse(session.mats_on_chip);
  }

  async function refresh(mode) {
    const sess = await api("/api/session");
    if (mode === "soft" || (mode !== "hard" && hasTextSelection())) {
      renderSoft(sess);
    } else {
      render(sess);
    }
  }

  /** Currently loaded glassbox file name + lot id (for picker highlight). */
  function currentLoad() {
    const path = (session && session.export_path) || "";
    const file = path ? path.split(/[/\\]/).pop() : "";
    const eid = (session && session.export_id) || "";
    let lotId = "";
    if (eid) {
      const m = String(eid).match(/^(\d+-(?:OT|NT|BN)-\d+)/);
      lotId = m ? m[1] : String(eid).replace(/\.\d+$/, "");
    }
    if (!lotId && file) {
      const m2 = file.replace(/\.glassbox$/i, "").match(/^(\d+-(?:OT|NT|BN)-\d+)/);
      lotId = m2 ? m2[1] : "";
    }
    // Prefer lot from cache if segment file matches
    if (file && lotsCache.length) {
      for (const lot of lotsCache) {
        for (const s of lot.segments || []) {
          if (s.file === file || s.export_id === eid) {
            return { file, exportId: eid || s.export_id, lotId: lot.lot_id };
          }
        }
      }
    }
    return { file, exportId: eid, lotId };
  }

  function renderLotList() {
    openLot = null;
    const cur = currentLoad();
    $("loader-title").textContent = "Glass logs";
    $("btn-loader-back").hidden = true;
    $("loader-path").textContent = exportsDir || "";
    $("loader-hint").textContent = cur.file
      ? `Loaded · ${cur.file} · pick another main log or open its parent.`
      : "Step 1 · pick a main log · then a sealed segment.";

    const list = $("loader-list");
    list.innerHTML = "";

    if (!lotsCache.length) {
      list.innerHTML =
        '<p class="empty">No *.glassbox in exports. Seal wood in sdk-import-station first.</p>';
      return;
    }

    for (const lot of lotsCache) {
      const b = document.createElement("button");
      b.type = "button";
      const segs = lot.segment_count ?? (lot.segments || []).length;
      const msgs = lot.msg_total ?? 0;
      const title = lot.glass_title || lot.lot_id;
      const isHere = cur.lotId && lot.lot_id === cur.lotId;
      if (isHere) {
        b.classList.add("is-current", "is-current-parent");
        b.setAttribute("aria-current", "true");
      }
      b.innerHTML = `<span class="eid">${lot.lot_id}${
        isHere ? ' <em class="here">here</em>' : ""
      }</span>
        <span class="sub">${title}</span>
        <span class="badge">${segs} segment${segs === 1 ? "" : "s"} · ${msgs} msg${
        isHere && cur.exportId ? " · open " + cur.exportId : ""
      }</span>`;
      b.addEventListener("click", () => renderSegmentList(lot));
      list.appendChild(b);
    }

    // Scroll parent into view when reopening picker mid-session
    const here = list.querySelector(".is-current-parent");
    if (here) here.scrollIntoView({ block: "nearest" });
  }

  function renderSegmentList(lot) {
    openLot = lot;
    const cur = currentLoad();
    $("loader-title").textContent = lot.lot_id;
    $("btn-loader-back").hidden = false;
    $("loader-path").textContent = lot.glass_title || lot.lot_id;
    $("loader-hint").textContent =
      cur.lotId === lot.lot_id && cur.file
        ? `Loaded · ${cur.file} · pick another segment or stay.`
        : "Step 2 · pick a sealed segment to walk.";

    const list = $("loader-list");
    list.innerHTML = "";
    const segs = lot.segments || [];
    if (!segs.length) {
      list.innerHTML = '<p class="empty">No segments in this lot.</p>';
      return;
    }

    for (const item of segs) {
      const b = document.createElement("button");
      b.type = "button";
      const part =
        item.part != null && item.part_count != null
          ? `part ${item.part}/${item.part_count}`
          : "segment";
      const isHere =
        (cur.file && item.file === cur.file) ||
        (cur.exportId && item.export_id === cur.exportId);
      if (isHere) {
        b.classList.add("is-current");
        b.setAttribute("aria-current", "true");
      }
      b.innerHTML = `<span class="eid">${item.export_id || item.file}${
        isHere ? ' <em class="here">here</em>' : ""
      }</span>
        <span class="sub">${item.title || "—"} · ${item.msg_count ?? "?"} msg · ${part}</span>
        <span class="badge">${item.file}</span>`;
      b.addEventListener("click", () => loadFile(item.file));
      list.appendChild(b);
    }

    const here = list.querySelector(".is-current");
    if (here) here.scrollIntoView({ block: "nearest" });
  }

  async function loadFile(file) {
    try {
      const res = await api("/api/load", {
        method: "POST",
        body: JSON.stringify({ file }),
      });
      closeLoader();
      render(res.session);
    } catch (e) {
      alert(e.message || String(e));
    }
  }

  async function openLoader() {
    const data = await api("/api/exports");
    exportsDir = data.exports_dir || "";
    lotsCache = data.lots || [];
    // Fallback: synthesize lots from flat items if old server
    if (!lotsCache.length && data.items && data.items.length) {
      const map = {};
      for (const it of data.items) {
        const lid = it.lot_id || (it.export_id || "").replace(/\.\d+$/, "") || it.file;
        if (!map[lid]) {
          map[lid] = {
            lot_id: lid,
            glass_title: it.glass_title || it.title || lid,
            segments: [],
            segment_count: 0,
            msg_total: 0,
          };
        }
        map[lid].segments.push(it);
        map[lid].msg_total += it.msg_count || 0;
        map[lid].segment_count = map[lid].segments.length;
      }
      lotsCache = Object.keys(map)
        .sort()
        .map((k) => map[k]);
    }
    setLoading(true);
    renderLotList();
  }

  async function step(delta) {
    const res = await api("/api/cursor", {
      method: "POST",
      body: JSON.stringify({ delta }),
    });
    render(res.session);
  }

  async function togglePocket() {
    const on = !(session && session.pocket_on);
    const res = await api("/api/pocket", {
      method: "POST",
      body: JSON.stringify({ on }),
    });
    render(res.session);
  }

  async function showMats() {
    const data = await api("/api/book");
    const ul = $("mats-list");
    ul.innerHTML = "";
    const msg = session && session.message;
    const chip = msg && msg.chip_id;
    const mid = msg && msg.message_id;
    const book = data.book;
    let mats = [];
    if (book && book.entries) {
      for (const e of book.entries) {
        if ((chip && e.chip_id === chip) || (mid && e.message_id === mid)) {
          mats = e.mats || [];
          break;
        }
      }
    }
    $("mats-hint").textContent = chip
      ? `chip ${chip} · ${mats.length} mat(s). Whisper later (Slice D).`
      : "No current chip.";
    if (!mats.length) {
      const li = document.createElement("li");
      li.className = "empty";
      li.textContent = "— empty";
      ul.appendChild(li);
    } else {
      for (const m of mats) {
        const li = document.createElement("li");
        li.textContent = `${m.mat}${m.rom ? " · " + m.rom : ""}`;
        ul.appendChild(li);
      }
    }
    $("mats-dialog").showModal();
  }

  let lastMats = -1;

  function noteMatsPulse(count) {
    const n = Number(count) || 0;
    const btn = $("btn-mats");
    if (lastMats >= 0 && n > lastMats) {
      btn.classList.add("mats-pulse");
      setTimeout(() => btn.classList.remove("mats-pulse"), 900);
    }
    lastMats = n;
  }

  /** Soft poll: mats only. Never kills text selection / copy. */
  function startLiveRefresh() {
    setInterval(() => {
      if (document.hidden) return;
      if (document.getElementById("app").classList.contains("loading")) return;
      if (!session || !session.export_id) return;
      refresh("soft").catch(() => {});
    }, 1500);
  }

  function wire() {
    $("btn-load").addEventListener("click", () => {
      openLoader().catch((e) => alert(e.message || e));
    });
    $("btn-loader-close").addEventListener("click", () => closeLoader());
    $("btn-loader-back").addEventListener("click", () => renderLotList());
    $("btn-prev").addEventListener("click", () => step(-1).catch(alert));
    $("btn-next").addEventListener("click", () => step(1).catch(alert));
    $("btn-pocket").addEventListener("click", () => togglePocket().catch(alert));
    $("btn-mats").addEventListener("click", () => showMats().catch(alert));
    $("btn-raw").addEventListener("click", () => {
      rawOn = !rawOn;
      $("msg-pane").classList.toggle("raw-on", rawOn);
      $("btn-raw").classList.toggle("on", rawOn);
      $("btn-raw").textContent = rawOn ? "pretty" : "raw";
    });
    document.addEventListener("keydown", (ev) => {
      if (ev.target && /input|textarea/i.test(ev.target.tagName)) return;
      if (ev.key === "Escape" && document.getElementById("app").classList.contains("loading")) {
        if (openLot) renderLotList();
        else closeLoader();
        return;
      }
      if (document.getElementById("app").classList.contains("loading")) return;
      if (ev.key === "ArrowLeft") {
        ev.preventDefault();
        step(-1).catch(() => {});
      }
      if (ev.key === "ArrowRight") {
        ev.preventDefault();
        step(1).catch(() => {});
      }
    });
    document.addEventListener("visibilitychange", () => {
      if (!document.hidden && session && session.export_id) {
        refresh(hasTextSelection() ? "soft" : "hard").catch(() => {});
      }
    });
  }

  wire();
  startLiveRefresh();
  refresh("hard").catch((e) => {
    $("m-body").textContent = "Server offline? " + (e.message || e);
  });
})();
