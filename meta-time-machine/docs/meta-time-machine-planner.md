# Planner Doc for: "meta-time-machine" (TPS Machina)

V0.2 2026-07-25 · ALICE_BOX · charlies-toys  
**Working folder:** `charlies-toys/meta-time-machine/`  
**Kind:** charlie-toy (personal object) — not a BRANDED SKU (yet)

> [!tip] SLICE A LIVE (2026-07-25)
> ROM runs under Deck Host. **Do not mass-migrate myPI.** Do not wire loreBOX yet.
>
> **Run:** `prod/run-machina.bat` · port **43111** · wood: `sdk-import-station/work/exports/*.glassbox`
>
> **Cords:** `prod/cords/README.md` — peek + POST only; no mutual imports. Publishes `cords/session.now` + `GET /api/cord/now`. Books: `*.tpsbook` (not `.json`). loreBOX mint whispers mat when pocket on (Slice D pilot).
>
> Mocks: `docs/early-rail-mockup.png` · `docs/rail-mock-refined.html` · [Figma](https://www.figma.com/design/ILka9ftaaO25YlxqAI2KrK)

### What is this?

**Time Machina / TPS Machina** is a background “time pocket” appliance.  
You set **now** (world / viewport / pocket) like a VPN rail. You **load a cleaned log** from sdk-import. You step **message by message**. While a message is current, **datmats minted in other ROMs** (and manual codes) are recorded into a **history book for that log** — major import meta lives in the **book**, not stuffed into every card deck.

> [!info] PRODUCT SHAPE (hands + wire, post-compact)
> - **sdk-import-station** prepares wood: clean · split · name · redact · encode → **exported cleaned log books only**
> - **Machina** loads **only that export format** (not raw glass mountain)
> - Bar: `< message >` — open message = work context (id, event unix, log id)
> - **Per loaded log → one history book**
> - Book rows: message_id · unix stamps · log identity · mat slugcodes born while that message was current
> - “Show mats for this message” = filter the book
> - **Per-card event time** still on the card (story time); pocket “now” is Machina’s job
> - Optional **short source nick** on mats; full archaeology = books

#Assigned-to-Wires **WIRE_SIGN_IN:** open · charlie-toy · ALICE_BOX desk

---

## Separation of powers

| Toy | Job |
|-----|-----|
| **sdk-import-station** | Clean logs → chunked sealed reports (filler wood) |
| **meta-time-machine** | Walk wood in time; history books of production |
| **DATBOX (lore/shot)** | Card decks — content + story event time + optional nick |
| **venBOOK** (later) | Manual character / slug / ven-code book (no auto-apply) |

**Law:** major import meta = **history books**. Card decks stay playable and light.

---

## User flow (target)

1. Cut & seal a log in **sdk-import** (export format only).  
2. Open **Time Machina**, load that export.  
3. Step `< >` through messages; current message owns the clock.  
4. Work in other ROMs (or paste mat codes); cord notes births into the **open log’s book**.  
5. Later: open book → see which mats came from which line / times.

---

## Store sketch (not frozen schema)

### Pocket / session (running rail)

- `pocket_id`, display name  
- `now`: world, viewport/sona, optional defaults  
- `loaded_log`: path / face / export identity  
- `cursor_message_id` / seq  
- on / off  

### History book (per loaded log)

```text
log_id / export face / source file
entries: [
  {
    message_id, seq?,
    event_unix, create_unix?, timezone?,
    mats: [ { mat, stem, unit_code, rom?, at } ]
  }
]
```

One book per log object; not one global soup.

---

## Cords (how ROMs whisper)

- Local convention later: env, tiny socket, or shared drop file.  
- First ship: **manual “register mat code”** on Machina + one ROM auto-whisper (e.g. loreBOX).  
- If Machina off or no current message → `unclocked` or refuse auto-bind.

---

## Non-goals (v0)

- Not the alphabet / full crate farm  
- Not auto-encode DB / auto ven apply  
- Not stuffing full provenance into every DATBOX card  
- Not reading raw `.glass-logs` NT shards (export books only)

---

## Repo spine (when we build)

Mirror DATBOX *lightly* as a charlie-toy:

```text
meta-time-machine/
  docs/          ← this planner
  prod/          ← later: box_sys-ish app, books store, rail UI
  README.md      ← short face
```

No fake EXE. Deck Host packaging when the toy runs.

---

## UI target (rail — not full ROM window)

**Shape:** narrow always-on rail (Figma AI rough → refined mock).

| Zone | Content |
|------|---------|
| Head | TPS machina · pocket on/off |
| Load | `.glassbox` only (`sdk-import-station/work/exports/`) |
| Lot meta | export CHIP · TITLE · MSG count · testament |
| Message | role · **chip_id** · time · body (pretty; raw JSON optional toggle) |
| Foot | `←` · **mats on chip** (book filter) · `→` |

- Hands rough: `docs/early-rail-mockup.png`  
- Refined HTML: `docs/rail-mock-refined.html`  
- Refined Figma: https://www.figma.com/design/ILka9ftaaO25YlxqAI2KrK  

**Pretty message default** (role · chip_id · time · body — not full JSON dump). **Raw JSON is a toggle**, not the main face.

## Build order (suggested)

| Step | Deliverable |
|------|-------------|
| **A** | **Slice A:** load `.glassbox` + walk `<>` + CURRENT chip/time/text + book + mats + cord publish — **done** |
| B | Book polish / browse entries |
| C | Manual “register mat code” UI on rail (`POST /api/cord/mat` exists) |
| **D** | loreBOX + shotBOX mint peeks cord + POSTs mat — **live** (CORS; soft fail if offline) |
| E | Desktop rail polish / thinner chrome |

**History books:** `prod/books/{export_id}.tpsbook` — one per loaded segment. Rows = chips you visited + mats whispered/registered while that chip was current. Not stuffed into DATBOX cards (optional short `tps_chip` nick only).

---

## Open decisions (hands)

1. Book extension: **`.tpsbook`** (decided — no `.json` on the file).  
2. Desktop top rail vs only in-window ROM first?  
3. story_event_id / retelling — book only, or optional nick on card?  

---

## Related

- `charlies-toys/sdk-import-station/` — wood prep  
- `charlies-toys/sdk-import-station/STORE.md` — timber / seal naming  
- DATBOX `house` + `mat` + `stem` + unit codes — what the book records  

*Leave-off: planner only. Build when hands say go.*


![[early-rail-mockup.png]]