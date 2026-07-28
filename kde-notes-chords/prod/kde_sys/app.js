/* KDE Notes & Chords · Instrumental Stories
   Port of myPI pageInstrumentalStories — self-contained */

(function () {
  const symbols = [
    "SP",
    "HP1",
    "HP2",
    "MP1",
    "MP2",
    "MP3",
    "LP1",
    "LP2",
    "LP3",
    "LP4",
  ];
  const atmosphere = [
    "Rain pounds outside.",
    "There is the subtle sense of a heartbeat.",
    "My head pounds. I try to focus.",
  ];
  const scale = [
    "C4",
    "D5",
    "E4",
    "F5",
    "G4",
    "A5",
    "B4",
    "C5",
    "D4",
    "E5",
    "F4",
    "G5",
    "A4",
    "B5",
    "C6",
  ];

  const cases = [
    {
      title: "The Smudged Man",
      slug: "casey1",
      customer: "Some Mary",
      clientBio:
        "A woman of an average age. Just some mary in a red dress wearing a necklace with a lamb pendant.",
      clues: [
        "It's a cold Tuesday. Every Tuesday's been cold for decades now.",
        "A woman walks into the Office at 78 Case Street. Sour face. Sour hands.",
        "I ask her how I can help. She looks bothered I even exist.",
        "I wonder what drives a woman to ask for help in a place that clearly disgusts her.",
        "She hands me a set of keys and a black wallet.",
        "She leaves without a word.",
        "I check the wallet.",
        "It contains an ID but the face is blurred. So is most of the name.",
        "I can make out only a C starting the first name, and nothing more.",
        "There is a business card in the wallet.",
        "The card reads 'ChesterImports.com! Now importing ~anything~.'",
        "There is no indication that any of this means anything. (end)",
      ],
    },
    {
      title: "The Unattended Reservation",
      slug: "red_lady1",
      clientBio:
        "A wild-eyed woman who appears to not slept in weeks. Her hair is in a tight bun.",
      customer: "The Red Lady",
      clues: [
        "She is flustered and slightly damp. It must be raining pretty well tonight.",
        "There was anger, almost fear in her eyes.",
        "'I was told they would be there. No one arrived.'",
        "I ask her what's wrong. She said she'd been invited to a special meeting.",
        "'Something went wrong. Something changed.' she said.",
        "'No one came, but everything changed.'",
        "I asked her to elaborate on the change.",
        "She wouldn't exactly say. She only redirected to the meeting.",
        "'The table was reserved. It should have happened correctly.' she said.",
        "The only thing she was certain of was that there was 'Two glasses. One untouched.'",
        "And yet no one ever arrived.",
        "I called the restaurant. The host looked briefly at the previous bookings.",
        "'Yup, see the reservation right here. For 3. No name attached.'",
        "I called the restaurant one more time. Surely a name on the bill existed.",
        "No name. Paid in cash. Someone had paid before she got there.",
        "I called her back into the office a few nights later.",
        "She was drier now, both physically, and in tone.",
        "'It's only gotten worse.' she said.",
        "She kept checking the door.",
        "'Got somewhere else to be?' I asked. She didn't reply.",
        "With no other leads to go on, and an uncooperative client, I close the case.",
        "If it could even be called that. (end)",
      ],
    },
    {
      title: "The Man Who Never Left",
      slug: "invis_man1",
      customer: "The Hotel Manager",
      clientBio: "A man with a stone face.",
      clues: [
        "He checked in three nights ago.",
        "Room's empty. Always is.",
        "Key keeps turning up at the desk.",
        "No one saw him leave.",
        "The bed is still warm.",
      ],
    },
    {
      title: "The Import That Waited",
      slug: "import_wait1",
      customer: "A Girl Who Will Not Give Her Name",
      clientBio:
        "Coat too thin for the rain. Hands that keep checking a pocket that is empty. She looks at the office like it might vanish if she blinks.",
      clues: [
        "She says the package never arrived. She also says she never ordered it.",
        "On the counter: a paper slip with a code that looks like a prayer and a tracking number.",
        "The slip is damp. The ink has run into a shape that almost looks like a door.",
        "I ask who sent it. She says, 'He always sends things. He never stays.'",
        "Outside, a neon sign buzzes: CHESTER'S IMPORTS. Half the letters are dark.",
        "She will not go in. She wants me to go in for her.",
        "Inside the shop the air smells like dust and ozone and something sweet that should not be sweet.",
        "The clerk is not a clerk. The clerk is a terminal with a green face and a login that already knows her.",
        "The terminal offers MAIL. Destination: other terminals. No proof of arrival.",
        "I find a crate tag under the counter. It says: WAITING TO BE IMPORTED.",
        "She flinches when I read it out loud. Like I named a bruise.",
        "In the crate: not goods. A stack of notes in different hands. One hand is hers. One is not.",
        "The not-hers hand writes: the spell took. the storm was required. stop being afraid.",
        "She asks if he is real. I am a detective. I am not a theologian.",
        "I tell her the truth I have: something is on the pier. It has her name on it. It has not entered the chest.",
        "She takes the notes. She leaves the code slip. She says, 'If I import it, I might not come back the same.'",
        "Rain again. The sign outside flickers fully on for one second. Then dies.",
        "Case status: open. The package is still waiting. So is she. (end)",
      ],
    },
  ];

  /*
   * Tone.js — only three rules that matter:
   * 1) Browsers block sound until a click (Tone.start() must ride that click).
   * 2) Build the synth ONCE; never stack a new one every spin.
   * 3) One path to speakers: synth → (optional reverb) → destination.
   * Your first JS did the hard part (spin + match + story). This just de-terrifies audio.
   */
  let synth = null;
  let reverb = null;
  let audioReady = false; // AudioContext unlocked + graph built
  let soundOn = false; // mute toggle (only meaningful after unlock)
  let audioBusy = false;
  let currentCase = null;
  let clueIndex = 0;
  let clueCount = 1;
  /** After last clue: hold the closed case on screen until the next win. */
  let caseClosed = false;
  /** Recent scrap positions [%] for spread. */
  let placedSpots = [];

  const reels = document.getElementById("reels");
  const caseEl = document.getElementById("case");
  const customerEl = document.getElementById("customer");
  const clueEl = document.getElementById("clue");
  const lastSpin = document.getElementById("last_spin");
  const app = document.getElementById("app");
  const btnPlay = document.getElementById("btnPlay");
  const btnAudio = document.getElementById("btnAudio");

  function deadSpin() {
    let output = "";
    for (let i = 0; i < 15; i++) {
      const sym = symbols[Math.floor(Math.random() * symbols.length)];
      output +=
        '<div class="tile ' +
        sym +
        '" data-index="' +
        i +
        '" data-sym="' +
        sym +
        '"></div>';
    }
    reels.innerHTML = output;
  }

  function toneAvailable() {
    return typeof Tone !== "undefined" && Tone && typeof Tone.start === "function";
  }

  function paintSoundBtn() {
    btnAudio.textContent = "SOUND";
    btnAudio.classList.toggle("is-on", soundOn);
    btnAudio.classList.toggle("is-muted", audioReady && !soundOn);
    btnAudio.title = !audioReady
      ? "Unlock sound"
      : soundOn
        ? "Mute"
        : "Unmute";
  }

  /** Build graph + unlock context (click only). Does not force soundOn. */
  async function ensureAudio() {
    if (audioReady && synth) return true;
    if (audioBusy) return false;
    if (!toneAvailable()) {
      lastSpin.textContent = "Tone.js did not load — story still works, no sound";
      return false;
    }
    audioBusy = true;
    try {
      await Tone.start();

      if (!synth) {
        Tone.getDestination().volume.value = -8;

        reverb = new Tone.Reverb({
          decay: 1.8,
          preDelay: 0.01,
          wet: 0.28,
        });
        await reverb.generate();

        synth = new Tone.PolySynth(Tone.Synth, {
          maxPolyphony: 16,
          oscillator: { type: "triangle" },
          envelope: {
            attack: 0.04,
            decay: 0.25,
            sustain: 0.35,
            release: 0.9,
          },
        });

        synth.connect(reverb);
        reverb.toDestination();
      }

      audioReady = true;
      return true;
    } catch (err) {
      lastSpin.textContent =
        "sound blocked (" +
        (err && err.message ? err.message : "unknown") +
        ")";
      return false;
    } finally {
      audioBusy = false;
    }
  }

  /** SOUND button: unlock if needed, then real mute toggle. */
  async function toggleSound() {
    if (!audioReady) {
      const ok = await ensureAudio();
      if (!ok) return;
      soundOn = true;
      paintSoundBtn();
      return;
    }
    soundOn = !soundOn;
    if (!soundOn && synth) {
      try {
        synth.releaseAll();
      } catch (e) {}
    }
    paintSoundBtn();
  }

  /**
   * Play a chord from strip positions.
   * notes: string[] like ["C4","E4","G4"] — played together, once.
   */
  function playChord(notes) {
    if (!audioReady || !soundOn || !synth || !notes || !notes.length) return;
    // unique notes only (duplicate keys would just stack the same pitch)
    const unique = [];
    const seen = {};
    notes.forEach(function (n) {
      if (n && !seen[n]) {
        seen[n] = true;
        unique.push(n);
      }
    });
    if (!unique.length) return;
    try {
      // release anything still hanging so rapid PLAY doesn't smear forever
      synth.releaseAll();
      // one shot: all notes of the strip-chord, short duration
      synth.triggerAttackRelease(unique, "2n");
    } catch (err) {
      // never let audio kill the toy
      lastSpin.textContent = "chord hiccup — strip still spun";
    }
  }

  const folderTab = document.getElementById("folderTab");
  const caseBanner = document.getElementById("caseBanner");

  /** Place scrap with real spread — avoid piling on the same corner. */
  function placeFrag(el) {
    const cardW = 36;
    const cardH = 32;
    const minDist = 18;
    let left = 4;
    let top = 4;
    let tries = 0;
    while (tries < 28) {
      left = 3 + Math.random() * (97 - cardW);
      top = 3 + Math.random() * (97 - cardH);
      let ok = true;
      for (let i = 0; i < placedSpots.length; i++) {
        const s = placedSpots[i];
        const dx = left - s.left;
        const dy = top - s.top;
        if (Math.sqrt(dx * dx + dy * dy) < minDist) {
          ok = false;
          break;
        }
      }
      if (ok) break;
      tries++;
    }
    // if crowded, still place but farther from last spot
    if (tries >= 28 && placedSpots.length) {
      const last = placedSpots[placedSpots.length - 1];
      left = (last.left + 25 + Math.random() * 30) % (97 - cardW);
      top = (last.top + 20 + Math.random() * 35) % (97 - cardH);
    }
    placedSpots.push({ left: left, top: top });
    if (placedSpots.length > 14) placedSpots.shift();
    const rot = (Math.random() * 12 - 6).toFixed(1);
    el.style.left = left.toFixed(1) + "%";
    el.style.top = top.toFixed(1) + "%";
    el.style.transform = "rotate(" + rot + "deg)";
    el.dataset.rot = rot;
  }

  function bindDrag(el) {
    let dragging = false;
    let ox = 0;
    let oy = 0;

    function onDown(e) {
      if (e.button != null && e.button !== 0) return;
      dragging = true;
      el.classList.add("is-dragging");
      el.classList.remove("is-pinned");
      const rect = el.getBoundingClientRect();
      ox = e.clientX - rect.left;
      oy = e.clientY - rect.top;
      el.style.zIndex = String(40 + clueCount);
      // switch from % / right pin to pixel coords for free drag
      el.style.right = "auto";
      e.preventDefault();
    }

    function onMove(e) {
      if (!dragging) return;
      const parent = clueEl.getBoundingClientRect();
      let x = e.clientX - parent.left - ox;
      let y = e.clientY - parent.top - oy;
      const maxX = parent.width - el.offsetWidth;
      const maxY = parent.height - el.offsetHeight;
      x = Math.max(0, Math.min(maxX, x));
      y = Math.max(0, Math.min(maxY, y));
      el.style.left = x + "px";
      el.style.top = y + "px";
      el.style.right = "auto";
      el.style.bottom = "auto";
      const rot = el.dataset.rot || "0";
      el.style.transform = "rotate(" + rot + "deg)";
    }

    function onUp() {
      if (!dragging) return;
      dragging = false;
      el.classList.remove("is-dragging");
    }

    el.addEventListener("pointerdown", onDown);
    window.addEventListener("pointermove", onMove);
    window.addEventListener("pointerup", onUp);
    window.addEventListener("pointercancel", onUp);
  }

  function appendFrag(text, kind) {
    kind = kind || "clue";
    const span = document.createElement("span");
    span.className = "frag frag-" + kind;
    span.id = "frag-" + clueCount;
    const safe = text.replace(/</g, "&lt;");
    // different objects on the desk — not just recolored white slips
    if (kind === "high") {
      span.innerHTML =
        '<span class="sticky-tab" aria-hidden="true"></span>' +
        '<span class="fragNm">' +
        clueCount +
        '</span><span class="frag-body">' +
        safe +
        "</span>";
    } else if (kind === "feel") {
      span.innerHTML =
        '<span class="fragNm">' +
        clueCount +
        '</span><span class="frag-body">' +
        safe +
        "</span>";
    } else if (kind === "special") {
      span.innerHTML =
        '<span class="key-stamp">KEY</span>' +
        '<span class="fragNm">' +
        clueCount +
        '</span><span class="frag-body">' +
        safe +
        "</span>";
    } else {
      span.innerHTML =
        '<span class="fragNm">' +
        clueCount +
        '</span><span class="frag-body">' +
        safe +
        "</span>";
    }
    placeFrag(span);
    clueEl.appendChild(span);
    bindDrag(span);
    clueCount++;
  }

  function flashBanner(msg, ms) {
    if (!caseBanner) return;
    caseBanner.textContent = msg;
    caseBanner.classList.add("is-on");
    if (folderTab) {
      folderTab.classList.remove("is-case-flash");
      void folderTab.offsetWidth;
      folderTab.classList.add("is-case-flash");
    }
    setTimeout(function () {
      caseBanner.classList.remove("is-on");
    }, ms || 1600);
  }

  function clearFolderKeepBanner() {
    Array.prototype.slice.call(clueEl.querySelectorAll(".frag")).forEach(function (n) {
      n.remove();
    });
    placedSpots = [];
  }

  /** Open a fresh case (clears folder). Does not drop scraps — caller decides. */
  function startCase() {
    currentCase = cases[Math.floor(Math.random() * cases.length)];
    clueIndex = 0;
    clueCount = 1;
    caseClosed = false;
    customerEl.textContent = "CASE: " + currentCase.title;
    if (caseEl) caseEl.textContent = "78 Case Street";
    app.className = "kde-main " + currentCase.slug;
    clearFolderKeepBanner();
  }

  /** Weird little case file ID — not a real database, just office magic. */
  function mintCaseId(c) {
    const slug = (c.slug || "case").toUpperCase().replace(/[^A-Z0-9]/g, "").slice(0, 6);
    const n = String(100 + Math.floor(Math.random() * 800));
    const tail = String.fromCharCode(65 + Math.floor(Math.random() * 26));
    return "78-CS-" + slug + "-" + n + tail;
  }

  /** Client dossier card — paperclipped upper-right; not a white scrap. */
  function dropBio() {
    if (!currentCase) return;
    const caseId = mintCaseId(currentCase);
    const bio = document.createElement("span");
    bio.className = "frag frag-bio is-pinned";
    bio.id = "bio";
    bio.innerHTML =
      '<span class="paperclip" aria-hidden="true"></span>' +
      '<span class="bio-stamp">CLIENT</span>' +
      '<span class="bio-id">' +
      caseId.replace(/</g, "&lt;") +
      "</span>" +
      '<span class="bio-who">' +
      (currentCase.customer || "Unknown").replace(/</g, "&lt;") +
      "</span>" +
      '<span class="frag-body">' +
      currentCase.clientBio.replace(/</g, "&lt;") +
      "</span>";
    bio.style.left = "auto";
    bio.style.right = "5%";
    bio.style.top = "6%";
    bio.style.transform = "rotate(1.5deg)";
    bio.dataset.rot = "1.5";
    bio.dataset.pinned = "1";
    bio.dataset.caseId = caseId;
    clueEl.appendChild(bio);
    bindDrag(bio);
    clueCount = Math.max(clueCount, 1);
  }

  /**
   * Pay table (highest only — one scrap per win):
   * SP KEY > HP CLUE > MP HINT > LP FEEL
   */
  function highestPayFamily(matchedSyms) {
    const has = function (prefix) {
      return matchedSyms.some(function (s) {
        return s.indexOf(prefix) === 0;
      });
    };
    if (has("SP")) return { family: "SP", kind: "special", usesClue: true };
    if (has("HP")) return { family: "HP", kind: "high", usesClue: true };
    if (has("MP")) return { family: "MP", kind: "clue", usesClue: true };
    if (has("LP")) return { family: "LP", kind: "feel", usesClue: false };
    return null;
  }

  function applyHighestPay(pay) {
    if (!pay || !currentCase || caseClosed) return;

    if (!pay.usesClue) {
      const rand = Math.floor(Math.random() * atmosphere.length);
      appendFrag(atmosphere[rand], "feel");
      return;
    }

    if (clueIndex >= currentCase.clues.length) {
      // safety: already out of clues
      closeCaseHold();
      return;
    }

    appendFrag(currentCase.clues[clueIndex], pay.kind);
    clueIndex++;

    if (clueIndex >= currentCase.clues.length) {
      closeCaseHold();
    }
  }

  /** End of case: keep scraps on desk, wait for next win. */
  function closeCaseHold() {
    caseClosed = true;
    if (currentCase) {
      customerEl.textContent = "CASE CLOSED · " + currentCase.title;
      caseEl.textContent = "CLIENT: " + currentCase.customer;
    }
    flashBanner("CASE CLOSED", 2000);
  }

  function spin() {
    let output = "";
    const all = [];
    for (let i = 0; i < 15; i++) {
      const sym = symbols[Math.floor(Math.random() * symbols.length)];
      all.push(sym);
      output +=
        '<div class="tile ' +
        sym +
        '" data-index="' +
        i +
        '" data-sym="' +
        sym +
        '"></div>';
    }
    reels.innerHTML = output;

    const counts = {};
    all.forEach(function (s) {
      counts[s] = (counts[s] || 0) + 1;
    });

    const matchedSyms = [];
    Object.keys(counts).forEach(function (sym) {
      if (counts[sym] >= 4) {
        matchedSyms.push(sym);
        reels.querySelectorAll('.tile[data-sym="' + sym + '"]').forEach(function (el) {
          el.classList.add("match");
        });
      }
    });

    lastSpin.textContent = matchedSyms.length
      ? "MATCH · " +
        matchedSyms
          .map(function (s) {
            return counts[s] + "× " + s;
          })
          .join(" · ")
      : "—";

    if (!matchedSyms.length) return;

    const pay = highestPayFamily(matchedSyms);
    // one payline: only highest family stays lit + feeds chord + one scrap
    if (pay) {
      reels.querySelectorAll(".tile.match").forEach(function (el) {
        if (el.dataset.sym.indexOf(pay.family) !== 0) {
          el.classList.remove("match");
        }
      });
      lastSpin.textContent = "HIT · " + pay.family;
    }

    const matched = Array.prototype.slice
      .call(reels.querySelectorAll(".tile.match"))
      .sort(function (a, b) {
        return (
          parseInt(a.dataset.index, 10) - parseInt(b.dataset.index, 10)
        );
      });
    const notes = [];
    matched.forEach(function (el) {
      const index = parseInt(el.dataset.index, 10);
      if (!isNaN(index) && scale[index]) notes.push(scale[index]);
    });
    playChord(notes);

    // narrative: exactly one scrap per win
    // opening a case (first hit or after CASE CLOSED) only drops the client bio —
    // never bio + clue on the same spin (that was the double-fragment bug)
    if (caseClosed) {
      flashBanner("NEW CASE", 1400);
      startCase();
      dropBio();
      return;
    }

    if (!currentCase) {
      startCase();
      dropBio();
      return;
    }

    applyHighestPay(pay);
  }

  btnAudio.addEventListener("click", function () {
    toggleSound();
  });

  btnPlay.addEventListener("click", function () {
    // PLAY may unlock audio the first time, and turns sound ON if still muted
    const go = function () {
      spin();
    };
    if (!audioReady) {
      ensureAudio().then(function (ok) {
        if (ok) {
          soundOn = true;
          paintSoundBtn();
        }
        go();
      });
    } else {
      go();
    }
  });

  deadSpin();
  paintSoundBtn();
  caseEl.textContent = "78 Case Street";
  customerEl.textContent = "—";
})();
