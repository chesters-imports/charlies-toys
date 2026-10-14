const state = { cards: [], colors: [], library: "", selected: "", filter: { q: "", klass: "", house: "", pile: "" } };
const boardEl = document.getElementById("board");
const cardsEl = document.getElementById("cards");
const linesEl = document.getElementById("lines");

function stripColor(klass) {
  let n = 0;
  for (const ch of klass || "?") n = (n * 33 + ch.charCodeAt(0)) >>> 0;
  return hslToHex(n % 360, 42, 38);
}

function hslToHex(h, s, l) {
  s /= 100;
  l /= 100;
  const a = s * Math.min(l, 1 - l);
  const f = (n) => {
    const k = (n + h / 30) % 12;
    const color = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1);
    return Math.round(255 * color).toString(16).padStart(2, "0");
  };
  return `#${f(0)}${f(8)}${f(4)}`;
}

function byCrate(crate) {
  return state.cards.find((c) => c.crate === crate);
}

async function api(path, opts) {
  const res = await fetch(path, {
    headers: { "Content-Type": "application/json" },
    ...opts,
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || res.statusText);
  return data;
}

async function reload() {
  const data = await api("/api/state");
  state.cards = data.cards;
  state.colors = data.colors || [];
  state.library = data.library;
  document.getElementById("library").value = data.library;
  fillSelect("by-class", "all classes", [...new Set(data.cards.map((c) => c.class).filter(Boolean))].sort());
  fillSelect("by-house", "all houses", [...new Set(data.cards.map((c) => c.house).filter(Boolean))].sort());
  fillSelect("by-pile", "all piles", data.piles);
  fillList("class-list", [...new Set(data.cards.map((c) => c.class).filter(Boolean))].sort());
  fillList("house-list", [...new Set(data.cards.map((c) => c.house).filter(Boolean))].sort());
  paintSwatches("make-swatches", document.getElementById("make-color"));
  paint();
}

function fillList(id, values) {
  document.getElementById(id).innerHTML = values.map((v) => `<option value="${escapeHtml(v)}"></option>`).join("");
}

function paintSwatches(boxId, input) {
  const box = document.getElementById(boxId);
  if (!box) return;
  box.innerHTML = state.colors.map((color) => (
    `<button type="button" data-color="${color}" style="background:${color}" title="${color}"></button>`
  )).join("");
  box.querySelectorAll("button").forEach((btn) => {
    btn.addEventListener("click", () => {
      input.value = btn.dataset.color;
      input.dispatchEvent(new Event("change"));
    });
  });
}

function fillSelect(id, label, values) {
  const el = document.getElementById(id);
  const current = el.value;
  el.innerHTML = `<option value="">${label}</option>` + values.map((v) => `<option>${escapeHtml(v)}</option>`).join("");
  if ([...el.options].some((o) => o.value === current)) el.value = current;
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
}

function filtered() {
  const { q, klass, house, pile } = state.filter;
  const needle = q.trim().toLowerCase();
  return state.cards.filter((c) => {
    if (klass && c.class !== klass) return false;
    if (house && c.house !== house) return false;
    if (pile && c.pile !== pile) return false;
    if (!needle) return true;
    return (c.title + " " + c.line + " " + c.class).toLowerCase().includes(needle);
  });
}

function paint() {
  const shown = filtered();
  const list = document.getElementById("list");
  list.innerHTML = shown.map((c) => (
    `<li data-crate="${c.crate}" class="${c.crate === state.selected ? "on" : ""}">` +
    `<strong>${escapeHtml(c.title)}</strong>` +
    `<small>${escapeHtml(c.class || "no class")}${c.house ? " · " + escapeHtml(c.house) : ""}${c.pile ? " · " + escapeHtml(c.pile) : ""}</small>` +
    `</li>`
  )).join("");
  cardsEl.innerHTML = shown.map((c, i) => {
    const x = c.x == null ? 40 + (i % 5) * 200 : c.x;
    const y = c.y == null ? 40 + Math.floor(i / 5) * 140 : c.y;
    c.x = x;
    c.y = y;
    return (
      `<article class="card${c.crate === state.selected ? " on" : ""}" data-crate="${c.crate}" style="left:${x}px;top:${y}px;--strip:${c.strip || stripColor(c.class)}">` +
      `<div class="marks"><div class="cls">${escapeHtml(c.class || "card")}</div>` +
      `<div class="house">${escapeHtml(c.house || "")}</div></div>` +
      `<h3>${escapeHtml(c.title)}</h3>` +
      `<p>${escapeHtml(c.line)}</p>` +
      `<span class="nib" title="drag from here onto another card"></span>` +
      `</article>`
    );
  }).join("");
  paintLines(shown);
  paintInspect();
}

function anchor(card) {
  const el = cardsEl.querySelector(`.card[data-crate="${card.crate}"]`);
  const h = el ? el.offsetHeight : 110;
  return { x: card.x + 90, y: card.y + h };
}

function paintLines(shown, rubber) {
  const have = new Set(shown.map((c) => c.crate));
  const seen = new Set();
  const parts = [];
  for (const card of shown) {
    for (const other of card.edges) {
      if (!have.has(other)) continue;
      const key = [card.crate, other].sort().join("|");
      if (seen.has(key)) continue;
      seen.add(key);
      const a = anchor(card);
      const b = anchor(byCrate(other));
      parts.push(line(a.x, a.y, b.x, b.y));
    }
  }
  if (rubber) parts.push(line(rubber.x1, rubber.y1, rubber.x2, rubber.y2, true));
  const box = cardsEl.getBoundingClientRect();
  linesEl.setAttribute("viewBox", `0 0 ${cardsEl.offsetWidth} ${cardsEl.offsetHeight}`);
  linesEl.style.width = cardsEl.offsetWidth + "px";
  linesEl.style.height = cardsEl.offsetHeight + "px";
  linesEl.innerHTML = parts.join("");
  void box;
}

function line(x1, y1, x2, y2, rubber) {
  const dash = rubber ? ' stroke-dasharray="5 4"' : "";
  return `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="#e7d7b0" stroke-width="2"${dash} />`;
}

function boardPoint(ev) {
  const rect = boardEl.getBoundingClientRect();
  return {
    x: ev.clientX - rect.left + boardEl.scrollLeft,
    y: ev.clientY - rect.top + boardEl.scrollTop,
  };
}

function paintInspect() {
  const pane = document.getElementById("inspect");
  const card = byCrate(state.selected);
  if (!card) {
    pane.hidden = true;
    return;
  }
  pane.hidden = false;
  document.getElementById("ins-class").textContent = card.class || "card";
  document.getElementById("ins-title").textContent = card.title;
  document.getElementById("ins-line").value = card.line;
  document.getElementById("ins-klass").value = card.class;
  document.getElementById("ins-house").value = card.house;
  document.getElementById("ins-color").value = card.strip || stripColor(card.class);
  paintSwatches("ins-swatches", document.getElementById("ins-color"));
  document.getElementById("ins-pile").value = card.pile || "";
  const birth = [card.from, card.source_crate].filter(Boolean).join(" · ");
  document.getElementById("ins-birth").textContent = birth
    ? "Old birth stamp: " + birth
    : "No birth room. This card was made as a card.";
  document.getElementById("clear-birth").hidden = !birth;
  document.getElementById("found").innerHTML = card.found.map((crate) => (
    `<li><span>${crate}</span><button type="button" data-drop-found="${crate}">drop</button></li>`
  )).join("") || "<li>nowhere yet</li>";
  document.getElementById("touches").innerHTML = card.edges.map((crate) => {
    const other = byCrate(crate);
    const label = other ? other.title : crate;
    return `<li><span>${escapeHtml(label)}</span><button type="button" data-drop-edge="${crate}">cut</button></li>`;
  }).join("") || "<li>nothing yet</li>";
}

document.getElementById("make").addEventListener("submit", async (ev) => {
  ev.preventDefault();
  const fd = new FormData(ev.target);
  const data = await api("/api/cards", {
    method: "POST",
    body: JSON.stringify({
      title: fd.get("title"),
      class: fd.get("class"),
      line: fd.get("line"),
      house: fd.get("house"),
      strip: document.getElementById("make-color").value,
    }),
  });
  ev.target.reset();
  state.selected = data.card.crate;
  await reload();
});

document.getElementById("show-trays").addEventListener("click", async () => {
  const box = document.getElementById("tray-pick");
  if (!box.hidden) {
    box.hidden = true;
    return;
  }
  const data = await api("/api/trays");
  document.getElementById("tray-list").innerHTML = data.cards.map((c, i) => (
    `<li><label><input type="checkbox" value="${c.crate}" ${i < 8 ? "checked" : ""} /> ` +
    `${escapeHtml(c.title)} <small>${escapeHtml(c.class || "")}</small></label></li>`
  )).join("") || "<li>no tray cards found</li>";
  box.hidden = false;
});

document.getElementById("bring-trays").addEventListener("click", async () => {
  const crates = [...document.querySelectorAll("#tray-list input:checked")].map((el) => el.value);
  const result = await api("/api/import", { method: "POST", body: JSON.stringify({ crates }) });
  document.getElementById("tray-pick").hidden = true;
  await reload();
  if (result.imported.length) state.selected = result.imported[0];
  paint();
});

document.getElementById("use-library").addEventListener("click", async () => {
  await api("/api/library", {
    method: "POST",
    body: JSON.stringify({ library: document.getElementById("library").value }),
  });
  state.selected = "";
  await reload();
});

for (const [id, key] of [["q", "q"], ["by-class", "klass"], ["by-house", "house"], ["by-pile", "pile"]]) {
  document.getElementById(id).addEventListener("input", (ev) => {
    state.filter[key] = ev.target.value;
    paint();
  });
}

document.getElementById("list").addEventListener("click", (ev) => {
  const li = ev.target.closest("li");
  if (!li) return;
  state.selected = li.dataset.crate;
  paint();
});

document.getElementById("save-card").addEventListener("click", async () => {
  const card = byCrate(state.selected);
  if (!card) return;
  await api("/api/cards/" + card.crate, {
    method: "PATCH",
    body: JSON.stringify({
      line: document.getElementById("ins-line").value,
      class: document.getElementById("ins-klass").value,
      house: document.getElementById("ins-house").value,
      strip: document.getElementById("ins-color").value,
      pile: document.getElementById("ins-pile").value,
    }),
  });
  await reload();
});

document.getElementById("ins-color").addEventListener("change", async (ev) => {
  const card = byCrate(state.selected);
  if (!card) return;
  card.strip = ev.target.value;
  document.querySelector(`.card[data-crate="${card.crate}"]`)?.style.setProperty("--strip", card.strip);
  await api("/api/cards/" + card.crate, {
    method: "PATCH",
    body: JSON.stringify({ strip: card.strip }),
  });
  if (!state.colors.includes(card.strip)) {
    state.colors.push(card.strip);
    paintSwatches("make-swatches", document.getElementById("make-color"));
    paintSwatches("ins-swatches", ev.target);
  }
});

document.getElementById("clear-birth").addEventListener("click", async () => {
  const card = byCrate(state.selected);
  if (!card) return;
  await api("/api/cards/" + card.crate, {
    method: "PATCH",
    body: JSON.stringify({ clear_birth: true }),
  });
  await reload();
});

document.getElementById("add-found").addEventListener("submit", async (ev) => {
  ev.preventDefault();
  const card = byCrate(state.selected);
  if (!card) return;
  const crate = new FormData(ev.target).get("crate");
  await api("/api/cards/" + card.crate, {
    method: "PATCH",
    body: JSON.stringify({ found: [...card.found, crate] }),
  });
  ev.target.reset();
  await reload();
});

document.getElementById("inspect").addEventListener("click", async (ev) => {
  const card = byCrate(state.selected);
  if (!card) return;
  const found = ev.target.dataset.dropFound;
  const edge = ev.target.dataset.dropEdge;
  if (found) {
    await api("/api/cards/" + card.crate, {
      method: "PATCH",
      body: JSON.stringify({ found: card.found.filter((c) => c !== found) }),
    });
    await reload();
  }
  if (edge) {
    await api("/api/edge", { method: "DELETE", body: JSON.stringify({ a: card.crate, b: edge }) });
    await reload();
  }
});

let drag = null;
cardsEl.addEventListener("pointerdown", (ev) => {
  const nib = ev.target.closest(".nib");
  const cardEl = ev.target.closest(".card");
  if (!cardEl) return;
  state.selected = cardEl.dataset.crate;
  paintInspect();
  document.querySelectorAll(".card").forEach((el) => el.classList.toggle("on", el.dataset.crate === state.selected));
  if (nib) {
    ev.preventDefault();
    const from = byCrate(cardEl.dataset.crate);
    drag = { kind: "edge", from: from.crate, start: anchor(from) };
    cardEl.style.cursor = "crosshair";
  } else {
    const card = byCrate(cardEl.dataset.crate);
    const rect = boardEl.getBoundingClientRect();
    drag = {
      kind: "move",
      crate: card.crate,
      ox: ev.clientX - rect.left + boardEl.scrollLeft - card.x,
      oy: ev.clientY - rect.top + boardEl.scrollTop - card.y,
      el: cardEl,
    };
  }
  cardEl.setPointerCapture(ev.pointerId);
});

cardsEl.addEventListener("pointermove", (ev) => {
  if (!drag) return;
  if (drag.kind === "edge") {
    const p = boardPoint(ev);
    paintLines(filtered(), { x1: drag.start.x, y1: drag.start.y, x2: p.x, y2: p.y });
    return;
  }
  const rect = boardEl.getBoundingClientRect();
  const x = ev.clientX - rect.left + boardEl.scrollLeft - drag.ox;
  const y = ev.clientY - rect.top + boardEl.scrollTop - drag.oy;
  drag.el.style.left = x + "px";
  drag.el.style.top = y + "px";
  const card = byCrate(drag.crate);
  card.x = x;
  card.y = y;
  paintLines(filtered());
});

cardsEl.addEventListener("pointerup", async (ev) => {
  if (!drag) return;
  const current = drag;
  drag = null;
  document.querySelectorAll(".card").forEach((el) => { el.style.cursor = ""; });
  if (current.kind === "move") {
    const card = byCrate(current.crate);
    await api("/api/cards/" + card.crate, {
      method: "PATCH",
      body: JSON.stringify({ x: card.x, y: card.y }),
    });
    return;
  }
  const hit = document.elementFromPoint(ev.clientX, ev.clientY);
  const other = hit && hit.closest(".card");
  if (!other || other.dataset.crate === current.from) {
    paintLines(filtered());
    return;
  }
  await api("/api/edge", {
    method: "POST",
    body: JSON.stringify({ a: current.from, b: other.dataset.crate }),
  });
  await reload();
});

reload().catch((err) => {
  document.getElementById("list").innerHTML = `<li>${escapeHtml(err.message)}</li>`;
});
