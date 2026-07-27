# Port law — terminal network in ALICE_BOX

**Rewritten:** 2026-07-26 (hands) · **status pass:** end of day (fileKeeper + AB live)  
**Supersedes:** 2026-07-25 “IO import-only is enough of the network.”

This island is the **terminal network** (IO + AB now) living as **one little program** under ALICE_BOX — **not** a resurrected alphabet / pocket-as-OS.

---

## North star

| Priority | Job |
|----------|-----|
| **Done** | **fileKeeper** on FS paper; date parser; quiet editor; media **print** (`media:m.HEX`). |
| **Done** | **IOX / ABX** in-app switch (chip + themed deck menu); per-station paper roots. |
| **Hands now** | Vault / mine → **station paper** by pull (e.g. Kat’s files into **AB**). Not agent vault crawl. |
| **Live** | **MAIL** room — station↔station wire + outside inject (`/api/mail/inject`, `tools/mail_inject.php`). |
| **Later** | RX / ICU skins · send-to-color · dossier on AB · Charlie threads. |
| **Not this season** | DATBOX as migration bus · glass-log religion · postBASIC as primary capture · full alphabet topology. |

**Narrative (load-bearing):** stations sit in different **timeline outcomes** of broken-time Chester; each holds files nearest that outcome’s artifact of the wife. **One global store is wrong.** Per-station paper is right. File desk preferred over postBASIC because thinking is **narrative objects in a room**.

---

## Yes

- Keep the **ROM-as-little-program** feel (Deck Host). Love the look; keep it.  
- **Merge fileKeeper** into this station network app, behavior close to myPI `t/tools/fileKeeper` (date parser / insert date mods included).  
- **Paper root for IO** on disk under ALICE_BOX (or this island) — **visible files**, not hard-to-look-at sludge. Prefer real FS paper over ledger-king if ledger was the “files to hell” path; keep **what worked** (UX + date handling), fix **where it landed**.  
- Soft / no **login gate** for personal use (diegetic login can return for show later).  
- Glass **logImport** rail stays: desk / active / search / seal → `.glassbox` (see [PATHS.md](./PATHS.md), [STORE.md](./STORE.md)). Separate ritual from **life files**.  
- Excavate tool behavior from Builds; **do not** mass-robocopy myPI.  
- DATBOX later: promote chips **out of** terminal paper when something earns a mat.

## No

- Do **not** carry `a/b/c/…` alphabet as runtime.  
- Do **not** re-crown **ledger / postBASIC / pocket-as-OS**.  
- Do **not** force multi-login or “always N windows” — **chip switch** is primary; multi-window optional.  
- Do **not** put glass IMPORTS on AB (Sam’s toolkit, not K’s).  
- Do **not** make chat-log processing the main job (extract thinking when needed; stop staring at reply distortion).  
- Do **not** invent store product names without hands.  
- Do **not** put COLOR pills in the station network sidebar (chip + deck menu only).

---

## Phase plan

### Phase 1 — Files on green IO

1. ~~Add **FILES** room to the existing IO shell~~ **done** (`/?room=files`).  
2. ~~Port **fileKeeper desk** ≈ as-is~~ **done** (list / view / edit / new; **date field parser** kept; image attach deferred).  
3. ~~Bind desk to an **IO paper root**~~ **done** — `paper/io` · `SDK_PAPER_ROOT` (see PATHS.md). **FS `.fk.json`**, not ledger.  
4. Prove: create/edit a file, date behaves, tree is legible.  
5. Optional: one pull of Sam / Terminal IO vault folder into that root (hands-driven).

### Phase 2 — IOX switch — **landed**

1. ~~Station registry: IO + AB~~ **done** (AB always second).  
2. ~~Chip cycles station~~ **done** — whole rail tile · skin · `paper/{station}` · session/cookie.  
3. ~~Deck menu lean + stations~~ **done**; caption/menu **themed** to phosphor.  
4. AB: FILES + K welcome · **no** glass import/export. Face **KDE**.  
5. **Send to color** (later) — move `.fk.json` between `paper/{from}` → `paper/{to}`.  
6. Later: RX / ICU skins (paper already holds icu/rx welcome files from rehome).
### Phase 3 — Network dream

- ~~Station-to-station **email**~~ **bootstrap live** (`/?room=mail`) · store `mail/messages/*.json` · inject token `wire-whisper` (env `SDK_MAIL_INJECT_TOKEN`).  
- **Charlie threads**: AI reads station files, mails connections into a station.  
- Voice capture into files (mic ordered; optional).

### Hide assistant on glass — **landed on import desk** (2026-07-26)

**Why:** First pass through logs should surface **hands’ critical thinking and narrative**, not model tone.

**UX (live on logImport desk):**

| Rule | Detail |
|------|--------|
| **Default** | Assistant **collapsed** — user messages always open |
| **Chunk** | Click assistant **header** (role · #seq · peek) to expand that turn only |
| **Toolbar** | expand all asst / collapse all asst |
| **Hash** | `#li-msg-N` opens that turn (expands if assistant) |

**Still future:** same law on meta-time-machine chip/book rail.

**Law one-liner:** *My words first; assistant on request per chunk.*

---

## Source to excavate

| Piece | Builds path |
|--------|-------------|
| fileKeeper | `t\tools\fileKeeper\` |
| IO files door | `m\doors\terminal\io\files.php` → `getTool('fileKeeper','Desk')` |
| Other station files doors | `m\doors\terminal\{ab,rx,icu}\files.php` |
| logImport (already mostly here) | `t\tools\logImport\` |
| Cast / chips | `mypi docs\TERMINAL-ICU-WATCHERS.md` (IOX · ABX · ICU · RX) |
| Vault mines (content) | `Builds\_VAULTS\Terminal IO` (etc.) — **content**, not runtime |

---

## Rooms (target shape)

| Room | Status |
|------|--------|
| FILES (fileKeeper) | **Phase 1 — build next** |
| IMPORTS / active / search glass | Live |
| EXPORTS (sealed wood) | Live |
| E-mail / chat | Later |
| IOX multi-station | Phase 2 |

---

## Glass (unchanged)

`C:\ALICE_BOX\.glass-logs\` — OT + NT. See [PATHS.md](./PATHS.md).  
Sealed timber ≠ station life paper.

---

## Law one-liner

**fileKeeper into the station as it works → paper you can see → then IOX colors → mail/Charlie last. Alphabet stays dead. DATBOX and glass are not this season’s center.**
