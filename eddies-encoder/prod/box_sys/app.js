/* Eddy's Encoder · CO.TOY-004-EEE · desk + modal */
(() => {
  const state = {
    entries: [],
    links: [],
    linkTypes: [],
    selected: null,
    pull: null,
    editing: false,
  };

  const $ = (id) => document.getElementById(id);
  const toastEl = $("toast");
  const modal = $("creatureModal");

  function toast(msg, err) {
    toastEl.textContent = msg;
    toastEl.classList.toggle("is-err", !!err);
    toastEl.hidden = false;
    clearTimeout(toastEl._t);
    toastEl._t = setTimeout(() => {
      toastEl.hidden = true;
    }, 2400);
  }

  function esc(s) {
    return String(s ?? "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  async function api(path, opts = {}) {
    const res = await fetch(path, {
      headers: { "Content-Type": "application/json", ...(opts.headers || {}) },
      ...opts,
    });
    let data = {};
    try {
      data = await res.json();
    } catch {
      /* */
    }
    if (!res.ok) throw new Error((data && data.error) || res.statusText);
    return data;
  }

  function fillLinkTypes() {
    const sel = $("linkType");
    if (!sel) return;
    const types = state.linkTypes.length
      ? state.linkTypes
      : ["relates_to", "aka_of", "from_log_as"];
    sel.innerHTML = types.map((t) => `<option value="${esc(t)}">${esc(t)}</option>`).join("");
  }

  function renderList(filter) {
    const q = (filter || "").toLowerCase();
    let list = state.entries || [];
    if (q) {
      list = list.filter((e) => {
        const blob = [
          e.kven,
          e.label,
          ...(e.alts || []),
          ...(e.matches || []),
          e.notes,
          e.type,
        ]
          .join(" ")
          .toLowerCase();
        return blob.includes(q);
      });
    }
    $("bagCount").textContent = String(list.length);
    $("entryList").innerHTML =
      list
        .map(
          (e) => `
      <button type="button" class="ee-card ${state.selected === e.kven ? "is-on" : ""}" data-kven="${esc(e.kven)}">
        <div class="ee-card-kven">${esc(e.kven)}</div>
        <div class="ee-card-type">${esc(e.type || "—")}</div>
        <div class="ee-card-meta">${esc(e.label || e.alts?.[0] || "no label")}</div>
      </button>`
        )
        .join("") ||
      '<div class="ee-empty-hint">empty index · pull stamp · + new creature</div>';

    $("entryList").querySelectorAll("[data-kven]").forEach((btn) => {
      btn.addEventListener("click", () => openEntry(btn.getAttribute("data-kven")));
    });
  }

  function openModal() {
    modal.hidden = false;
  }

  function closeModal() {
    modal.hidden = true;
    state.selected = null;
    state.editing = false;
    renderList($("searchQ").value);
    $("statusLine").textContent = "click a card to open · bag holds the rest";
  }

  function openEntry(kven) {
    const e = state.entries.find((x) => x.kven === kven);
    if (!e) return;
    state.selected = kven;
    state.editing = true;
    $("fKven").value = e.kven || "";
    $("fKven").readOnly = true;
    $("fLabel").value = e.label || "";
    $("fAlts").value = (e.alts || []).join(", ");
    $("fMatches").value = (e.matches || []).join(", ");
    $("fType").value = e.type || "person";
    $("fNotes").value = e.notes || "";
    $("fFirst").value = e.first_occurred != null ? String(e.first_occurred) : "";
    $("fLast").value = e.last_occurred != null ? String(e.last_occurred) : "";
    $("linkFrom").textContent = e.kven;
    $("btnDelete").hidden = false;
    $("modalTitle").textContent = e.kven;
    renderLinks();
    openModal();
    renderList($("searchQ").value);
    $("statusLine").textContent = `open ${e.kven}`;
  }

  function openNew(prefillKven) {
    state.selected = null;
    state.editing = false;
    $("fKven").readOnly = false;
    $("fKven").value = prefillKven || "";
    $("fLabel").value = "";
    $("fAlts").value = "";
    $("fMatches").value = "";
    $("fType").value = "person";
    $("fNotes").value = "";
    $("fFirst").value = "";
    $("fLast").value = "";
    $("linkFrom").textContent = prefillKven || "—";
    $("linkList").innerHTML = '<div class="ee-empty-hint">save creature first · then tape</div>';
    $("btnDelete").hidden = true;
    $("modalTitle").textContent = "NEW CREATURE";
    openModal();
    $("statusLine").textContent = prefillKven ? `using ${prefillKven}` : "new slip";
    if (!prefillKven) $("fKven").focus();
  }

  function renderLinks() {
    const from = state.selected;
    if (!from) return;
    const rows = (state.links || []).filter(
      (l) => l.from_kven === from || l.to_kven === from
    );
    $("linkList").innerHTML =
      rows
        .map((l) => {
          const dir = l.from_kven === from ? `→ ${l.to_kven}` : `← ${l.from_kven}`;
          const note = l.note ? ` · ${l.note}` : "";
          return `<div class="ee-link-row">
          <span><strong>${esc(l.link_type)}</strong> ${esc(dir)}${esc(note)}</span>
          <button type="button" data-del-link="${esc(l.id)}">×</button>
        </div>`;
        })
        .join("") || '<div class="ee-empty-hint">no tape yet · pick kind + optional note</div>';
    $("linkList").querySelectorAll("[data-del-link]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        try {
          await api(`/api/links/${encodeURIComponent(btn.getAttribute("data-del-link"))}`, {
            method: "POST",
            body: "{}",
          });
          await loadBag();
          renderLinks();
          toast("link gone");
        } catch (e) {
          toast(e.message, true);
        }
      });
    });
  }

  async function loadBag() {
    const data = await api("/api/bag");
    state.entries = data.entries || [];
    state.links = data.links || [];
    state.linkTypes = data.link_types || [];
    fillLinkTypes();
    renderList($("searchQ").value);
  }

  $("btnPull").addEventListener("click", async () => {
    try {
      const face = $("pullFace");
      const slot = face.closest(".ee-machine-slot") || face.parentElement;
      face.classList.remove("is-live", "is-glitch");
      if (slot) slot.classList.remove("is-glitching");
      void face.offsetWidth;
      // chaotic roll — scramble face then land on real KVEN
      if (slot) slot.classList.add("is-glitching");
      face.classList.add("is-glitch");
      // ASCII only — multi-byte block glyphs broke whole script parse (BUG10)
      const glyphs = "ABCDEFGHJKLMNPQRSTUVWXYZ0123456789#@$%*+=";
      let ticks = 0;
      let scramble = setInterval(() => {
        let s = "";
        for (let i = 0; i < 9; i++) {
          s += glyphs[(Math.random() * glyphs.length) | 0];
          if (i === 2 || i === 5) s += " ";
        }
        face.textContent = s;
        ticks++;
        if (ticks > 10) {
          clearInterval(scramble);
          scramble = null;
        }
      }, 42);
      const data = await api("/api/pull");
      await new Promise((r) => setTimeout(r, 480));
      if (scramble) clearInterval(scramble);
      state.pull = data.kven;
      face.textContent = data.kven;
      face.classList.remove("is-glitch");
      face.classList.add("is-live");
      if (slot) slot.classList.remove("is-glitching");
      $("statusLine").textContent = "pulled · USE or PULL again";
    } catch (e) {
      toast(e.message || "pull fail", true);
      const face = $("pullFace");
      if (face) face.classList.remove("is-glitch");
      const slot = face && face.closest(".ee-machine-slot");
      if (slot) slot.classList.remove("is-glitching");
    }
  });

$("btnUsePull").addEventListener("click", () => {
    if (!state.pull) {
      toast("pull first", true);
      return;
    }
    openNew(state.pull);
  });

  $("btnOwn").addEventListener("click", () => openNew(""));
  $("btnNew").addEventListener("click", () => openNew(state.pull || ""));

  $("btnCloseModal").addEventListener("click", closeModal);
  $("modalScrim").addEventListener("click", closeModal);
  document.addEventListener("keydown", (ev) => {
    if (ev.key === "Escape" && !modal.hidden) closeModal();
  });

  $("btnSave").addEventListener("click", async () => {
    const payload = {
      kven: $("fKven").value.trim(),
      label: $("fLabel").value,
      alts: $("fAlts").value,
      matches: $("fMatches").value,
      type: $("fType").value,
      notes: $("fNotes").value,
      first_occurred: $("fFirst").value.trim() || null,
      last_occurred: $("fLast").value.trim() || null,
    };
    try {
      if (state.editing && state.selected) {
        await api(`/api/entries/${encodeURIComponent(state.selected)}`, {
          method: "POST",
          body: JSON.stringify(payload),
        });
        toast("updated " + state.selected);
        await loadBag();
        openEntry(state.selected);
      } else {
        const data = await api("/api/entries", {
          method: "POST",
          body: JSON.stringify(payload),
        });
        toast("bagged " + data.entry.kven);
        await loadBag();
        openEntry(data.entry.kven);
      }
    } catch (e) {
      toast(e.message, true);
    }
  });

  $("btnDelete").addEventListener("click", async () => {
    if (!state.selected) return;
    if (!confirm("Delete " + state.selected + " from bag?")) return;
    try {
      await api(`/api/entries/${encodeURIComponent(state.selected)}`, {
        method: "POST",
        body: JSON.stringify({ delete: true }),
      });
      toast("deleted");
      closeModal();
      await loadBag();
    } catch (e) {
      toast(e.message, true);
    }
  });

  $("btnLink").addEventListener("click", async () => {
    if (!state.selected) {
      toast("save creature first", true);
      return;
    }
    try {
      await api("/api/links", {
        method: "POST",
        body: JSON.stringify({
          from_kven: state.selected,
          to_kven: $("linkTo").value,
          link_type: $("linkType").value,
          note: $("linkNote").value,
        }),
      });
      $("linkTo").value = "";
      $("linkNote").value = "";
      await loadBag();
      renderLinks();
      toast("linked");
    } catch (e) {
      toast(e.message, true);
    }
  });

  $("searchQ").addEventListener("input", () => renderList($("searchQ").value));

  /* ── dual mindset: karaoke desk ↔ dark angry (same layout + KARAOKE STAMP text) ── */
  const LS_ANGRY = "ee-mode-angry";

  function isAngry() {
    return document.body.classList.contains("is-angry");
  }

  function setAngry(on, opts) {
    const next = !!on;
    document.body.classList.toggle("is-angry", next);
    try {
      localStorage.setItem(LS_ANGRY, next ? "1" : "0");
    } catch (e) {
      /* Deck Host may sandbox — server prefs is source of truth */
    }
    if (!(opts && opts.skipServer)) {
      api("/api/prefs", {
        method: "POST",
        body: JSON.stringify({ mode_angry: next }),
      }).catch(() => {});
    }
  }

  function toggleAngry() {
    setAngry(!isAngry());
    toast(isAngry() ? "dark angry desk" : "karaoke desk");
  }

  // Deck doors for this ROM only — no back/forward/home junk.
  window.DECK_ROM_MENU = [
    {
      labelFn: function () {
        return isAngry() ? "Mode · karaoke desk" : "Mode · dark angry";
      },
      run: function () {
        toggleAngry();
      },
    },
    { sep: true },
    { label: "Hard refresh", action: "hard_refresh" },
    { label: "New window", action: "new_window" },
  ];

  async function boot() {
    // Prefer localStorage for instant paint, then server prefs (survives new window / host wipe)
    let angry = false;
    try {
      angry = localStorage.getItem(LS_ANGRY) === "1";
    } catch (e) {
      /* */
    }
    setAngry(angry, { skipServer: true });
    try {
      const pref = await api("/api/prefs");
      if (pref && pref.prefs && typeof pref.prefs.mode_angry === "boolean") {
        setAngry(!!pref.prefs.mode_angry, { skipServer: true });
        try {
          localStorage.setItem(LS_ANGRY, pref.prefs.mode_angry ? "1" : "0");
        } catch (e) {
          /* */
        }
      }
    } catch (e) {
      /* */
    }
    await loadBag();
  }

  boot().catch((e) => toast(e.message || "bag fail", true));
})();
