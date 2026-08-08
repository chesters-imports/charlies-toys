/* CO.KDE-002-KD1 · Detective K · Kitten Noir · office toy */
(() => {
  "use strict";

  const $ = (id) => document.getElementById(id);

  const screenTitle = $("screenTitle");
  const screenOffice = $("screenOffice");
  const btnStart = $("btnStart");
  const btnTitle = $("btnTitle");
  const btnKnock = $("btnKnock");
  const blotterNote = $("blotterNote");
  const officeStatus = $("officeStatus");
  const officeClock = $("officeClock");
  const dossier = $("dossier");
  const dossierId = $("dossierId");
  const dossierTitle = $("dossierTitle");
  const dossierBody = $("dossierBody");
  const trayBadge = $("trayBadge");
  const nameplateText = $("nameplateText");
  const lampGlow = $("lampGlow");

  /** @type {{ id: string, title: string, body: string, pinned?: boolean }[]} */
  let inbox = [];
  let lampOn = true;
  let knocks = 0;
  let caseDelivered = false;
  let nameTries = 0;

  const FILES = {
    "case-meow": {
      id: "KD1-CASE-001",
      title: "The Missing Meow",
      body:
        "<p><strong>CLIENT:</strong> anonymous slip under the door</p>" +
        "<p><strong>COMPLAINT:</strong> “Something soft is missing from Case Street. " +
        "It used to wait on the stoop. Now only rain.”</p>" +
        "<p><strong>ATTACHED:</strong> one damp business card, blank both sides. " +
        "Smells like tuna and secrets.</p>" +
        "<p class=\"dim\">Stamp when ready. (Stamps not invented yet. Pin is fine.)</p>",
    },
    "pin-who": {
      id: "CORK-01",
      title: "who am I",
      body:
        "<p>Handwriting is yours. Or is it?</p>" +
        "<p>Every morning the plate says <em>DETECTIVE ???</em> " +
        "and every evening the cork already knew.</p>" +
        "<p class=\"dim\">Do not remove. The string will notice.</p>",
    },
    "pin-case": {
      id: "CORK-02",
      title: "78 Case Street",
      body:
        "<p>Address of this office. Also a street that loops if you walk it wrong.</p>" +
        "<p>Neon outside spells CASE STREET like a dare.</p>",
    },
    "pin-q": {
      id: "CORK-03",
      title: "???",
      body:
        "<p>Three question marks. Classic.</p>" +
        "<p>Could be a clue. Could be the detective. Could be the cat.</p>",
    },
    phone: {
      id: "TALK-00",
      title: "Talk box",
      body:
        "<p><em>…dial tone like a lonely accordion…</em></p>" +
        "<p>No one is calling. Or everyone is, and the line only holds one ghost at a time.</p>" +
        "<p class=\"dim\">Talk boxes arrive with cases. Be patient, detective.</p>",
    },
    window: {
      id: "VIEW-01",
      title: "Case Street · rain",
      body:
        "<p>Blinds cut the neon into tiger stripes.</p>" +
        "<p>A figure might have been across the street. Or a coat on a chair. " +
        "Or a memory wearing a coat.</p>" +
        "<p class=\"dim\">Rain does not file reports.</p>",
    },
    cat: {
      id: "CAT-??",
      title: "Floor unit",
      body:
        "<p>It is a cat. Probably.</p>" +
        "<p>It does not answer to Detective. It answers to treats that do not exist yet.</p>" +
        "<p class=\"dim\">Evidence rating: soft. Motive: unknown. Alibi: always here.</p>",
    },
    mug: {
      id: "MUG-01",
      title: "Mug",
      body:
        "<p>Coffee that forgot itself. Still warm. Suspicious.</p>" +
        "<p>Lipprint on the rim — yours? Ring stain older than the office?</p>",
    },
    lamp: {
      id: "LAMP-01",
      title: "Desk lamp",
      body:
        "<p>Click. Amber pool. The blotter becomes a stage.</p>" +
        "<p class=\"dim\">(You can toggle the lamp by clicking it again.)</p>",
    },
    name: {
      id: "ID-00",
      title: "Nameplate",
      body:
        "<p><strong>DETECTIVE ???</strong></p>" +
        "<p>Engraving budget exceeded. Identity still in procurement.</p>" +
        "<p class=\"dim\">Click the plate a few times. Maybe it remembers.</p>",
    },
    door: {
      id: "DOOR-01",
      title: "Office door",
      body:
        "<p>Painted once. Peeling twice.</p>" +
        "<p>Knock from the bar if you want the universe to answer. " +
        "Or click here to press your ear to the wood.</p>" +
        "<p class=\"dim\">…rain. A distant trolley. Something with paws.</p>",
    },
    clock: {
      id: "TIME-02",
      title: "Wall clock",
      body:
        "<p>Hour stuck near two. Minutes still try.</p>" +
        "<p>Noir policy: the day is optional.</p>",
    },
  };

  function setStatus(t) {
    if (officeStatus) officeStatus.textContent = t;
  }

  function setBlotter(html) {
    if (blotterNote) blotterNote.innerHTML = html;
  }

  function show(which) {
    const title = which === "title";
    if (screenTitle) {
      screenTitle.hidden = !title;
      screenTitle.classList.toggle("is-on", title);
    }
    if (screenOffice) {
      screenOffice.hidden = title;
      screenOffice.classList.toggle("is-on", !title);
    }
    if (dossier && !dossier.hidden) closeDossier();
    setStatus(
      title ? "title · CO.KDE-002-KD1" : "office open · click the room · CO.KDE-002-KD1"
    );
  }

  function tickClock() {
    if (!officeClock) return;
    const m = String(new Date().getMinutes()).padStart(2, "0");
    officeClock.textContent = `2:${m}`;
  }

  function openDossier(keyOrFile) {
    const f = typeof keyOrFile === "string" ? FILES[keyOrFile] : keyOrFile;
    if (!f || !dossier) return;
    if (dossierId) dossierId.textContent = f.id || "FILE";
    if (dossierTitle) dossierTitle.textContent = f.title || "—";
    if (dossierBody) dossierBody.innerHTML = f.body || "";
    dossier.hidden = false;
    setStatus(`reading · ${f.id || "file"} · CO.KDE-002-KD1`);
  }

  function closeDossier() {
    if (dossier) dossier.hidden = true;
    setStatus(
      inbox.length
        ? `IN tray · ${inbox.length} paper(s) · CO.KDE-002-KD1`
        : "office open · CO.KDE-002-KD1"
    );
  }

  function deliverCase() {
    if (caseDelivered) return;
    caseDelivered = true;
    inbox.push({ ...FILES["case-meow"] });
    if (trayBadge) {
      trayBadge.hidden = false;
      trayBadge.textContent = String(inbox.length);
    }
    const top = $("trayPaperTop");
    if (top) top.classList.add("is-hot");
    setBlotter(
      "A paper hit the tray.<br /><span class=\"dim\">Click <strong>IN</strong> to read The Missing Meow.</span>"
    );
    setStatus("NEW PAPER · IN tray · CO.KDE-002-KD1");
  }

  function onKnock() {
    /* Someone outside — you answer the door (knock is the “someone’s here” cue). */
    knocks += 1;
    if (knocks === 1) {
      setBlotter(
        "A soft knock from the hall.<br /><span class=\"dim\">Answer again… or click the door.</span>"
      );
      setStatus("knock ×1 · visitor? · CO.KDE-002-KD1");
    } else if (knocks === 2) {
      setBlotter(
        "Envelope slides under the door. Damp corners.<br /><span class=\"dim\">Something soft is missing… Check <strong>IN</strong>.</span>"
      );
      setStatus("knock ×2 · envelope · CO.KDE-002-KD1");
      deliverCase();
    } else {
      setBlotter(
        "Only rain answers now.<br /><span class=\"dim\">Case is in the tray if you haven’t opened it.</span>"
      );
      setStatus(`knock ×${knocks} · quiet hall · CO.KDE-002-KD1`);
      if (!caseDelivered) deliverCase();
    }
  }

  function openInbox() {
    if (!inbox.length) {
      openDossier({
        id: "IN-00",
        title: "IN tray",
        body:
          "<p>Empty forms. Dust that might be dandruff or glitter.</p>" +
          "<p class=\"dim\">Knock twice. The universe is shy.</p>",
      });
      return;
    }
    const paper = inbox[inbox.length - 1];
    openDossier(paper);
  }

  function pinCurrent() {
    const title = (dossierTitle && dossierTitle.textContent) || "note";
    setBlotter(
      `Pinned: <em>${escapeHtml(title)}</em><br /><span class="dim">Cork board pretends it always knew.</span>`
    );
    const pinC = document.querySelector(".pin-c");
    if (pinC && title.length < 24) {
      pinC.textContent = title.slice(0, 14);
    }
    closeDossier();
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
  }

  function onNameplate() {
    nameTries += 1;
    const names = [
      "DETECTIVE ???",
      "DETECTIVE K",
      "KITTEN NOIR",
      "DETECTIVE ???",
      "K… something",
      "DETECTIVE K",
    ];
    if (nameplateText) {
      nameplateText.textContent = names[Math.min(nameTries, names.length - 1)];
    }
    if (nameTries >= 2) {
      openDossier({
        id: "ID-FLICKER",
        title: "Nameplate glitch",
        body:
          "<p>For a second it said <strong>DETECTIVE K</strong>.</p>" +
          "<p>Then <strong>KITTEN NOIR</strong>.</p>" +
          "<p>Then the question marks came home like cats at dusk.</p>" +
          "<p class=\"dim\">You are allowed to be more than one name.</p>",
      });
    } else {
      openDossier("name");
    }
  }

  // wire
  if (btnStart) btnStart.addEventListener("click", () => show("office"));
  if (btnTitle) btnTitle.addEventListener("click", () => show("title"));
  if (btnKnock) btnKnock.addEventListener("click", onKnock);
  if ($("dossierClose")) $("dossierClose").addEventListener("click", closeDossier);
  if ($("dossierPin")) $("dossierPin").addEventListener("click", pinCurrent);
  if (dossier) {
    dossier.addEventListener("click", (e) => {
      if (e.target === dossier) closeDossier();
    });
  }

  document.querySelectorAll("[data-inspect]").forEach((el) => {
    el.addEventListener("click", () => openDossier(el.getAttribute("data-inspect")));
  });

  if ($("inTray")) $("inTray").addEventListener("click", openInbox);
  if ($("hotPhone")) $("hotPhone").addEventListener("click", () => openDossier("phone"));
  if ($("hotWindow")) $("hotWindow").addEventListener("click", () => openDossier("window"));
  if ($("hotCat")) $("hotCat").addEventListener("click", () => openDossier("cat"));
  if ($("hotMug")) $("hotMug").addEventListener("click", () => openDossier("mug"));
  if ($("hotDoor")) {
    $("hotDoor").addEventListener("click", () => {
      // door is also a way to “answer” — first open peeks, further knocks deliver
      if (!caseDelivered && knocks >= 1) {
        onKnock();
      } else {
        openDossier("door");
      }
    });
  }
  if (officeClock) officeClock.addEventListener("click", () => openDossier("clock"));
  if ($("hotName")) {
    $("hotName").addEventListener("click", onNameplate);
    $("hotName").addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") onNameplate();
    });
  }
  if ($("hotLamp")) {
    $("hotLamp").addEventListener("click", () => {
      lampOn = !lampOn;
      if (lampGlow) lampGlow.style.opacity = lampOn ? "1" : "0.15";
      document.querySelector(".desk-top")?.classList.toggle("lamp-off", !lampOn);
      openDossier("lamp");
    });
  }

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && dossier && !dossier.hidden) closeDossier();
  });

  tickClock();
  setInterval(tickClock, 15000);
  show("title");
})();
