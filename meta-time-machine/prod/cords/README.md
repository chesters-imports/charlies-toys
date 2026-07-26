# Cords (how ROMs whisper — without conspiring)

**Slice A ships the socket shape, not the conspiracy.**

Anxiety this answers: *how does loreBOX know Machina is on this message, and how does Machina know a datmat just genned?*

## Law

1. **No mutual imports.** loreBOX never imports Machina code. Machina never imports loreBOX.
2. **No secret bus.** If two ROMs share state, it is a **file or HTTP they both understand**, written in plain JSON under a known path (or a tiny local HTTP get).
3. **Machina owns the clock.** Other ROMs may *read* “now”; they do not *set* Machina’s cursor.
4. **Machina owns the history book.** Other ROMs may *offer* a mat code; Machina decides whether to bind it to the current chip.
5. **Pocket off = refuse auto-bind.** Manual paste can still be discussed later; auto whisper must not fire when pocket is off.

## Slice A surfaces (live)

| Surface | Path / API | Who writes | Who reads |
|---------|------------|------------|-----------|
| Live now | `cords/session.now` + `GET /api/cord/now` | **Machina only** | loreBOX / other ROMs |
| VEN pot | glassbox `ven_pot` + per-msg `vencodes` | **sdk seal** | Machina exposes on cord; mats get `vencodes_present` / `tps_vencodes` |
| Book | `books/{export_id}.tpsbook` | **Machina only** | Machina UI; later export/browse |
| Manual mat | `POST /api/cord/mat` `{ "mat", "rom"? }` | Human or ROM | Machina appends to book |
| Unbind mat | `POST /api/cord/mat/remove` or `DELETE /api/cord/mat` | loreBOX on card delete | Strip mat rows from `.tpsbook` |

**Extensions:** books are **`.tpsbook`**, cord is **`session.now`** — structured text inside, no `.json` on the filename. Jason does not own the island.

`session.now` shape (`machina.cord.now.v0`):

```json
{
  "schema": "machina.cord.now.v0",
  "rom": "meta-time-machine",
  "pocket_on": true,
  "export_id": "001-OT-008.1",
  "current": {
    "chip_id": "001-OT-008.1.0",
    "message_id": "…",
    "event_unix": 1739663544.7,
    "event_iso": "2025-02-15T23:52:24+00:00",
    "text_preview": "…",
    "vencodes": [{ "code": "ASH-001", "alias": "Archer" }]
  },
  "vencodes": [{ "code": "ASH-001", "alias": "Archer" }]
}
```

## Planned conspiracy (not built)

### Path D — loreBOX → Machina (mat born)

When operator mints a card/mat in loreBOX **and** wants time-binding:

1. loreBOX peeks `GET http://127.0.0.1:43111/api/cord/now` (on card mint).
2. If `pocket_on` and `current.chip_id` present →  
   `POST /api/cord/mat` with `{ "mat": lore_code, "stem", "rom": "loreBOX" }`
3. Machina appends to **its** `.tpsbook` under that chip. loreBOX may keep a short `tps_chip` nick on the card; full archaeology stays in the book.

**Live:** loreBOX + **shotBOX** mint whisper when Machina is up (CORS open on Machina API).  
- lore: mat = `lore_code` (`.lore` unit in a `.lore` bag; legacy `.frag` may exist), rom `loreBOX`  
- shot: mat = `shot_code` (`.shot`), rom `shotBOX`

### Path S — sopr Documenter (Big Box) ← peeks Machina

Product docs, not narrative mats:

1. On **Store fragment** (or Document → Stamp current TPS chip), sopr peeks `GET /api/cord/now`.
2. If `pocket_on` + `current.chip_id` → append to **document** `tps_chips[]` (dedupe by `chip_id`).  
3. **No** per-frag chip · **no** vencodes · **no** mat POST to Machina book (unless we later want reverse awareness).  
4. Knowledge UI: **Document → About this document…** — not on compose/print surface.

Port: sopr `42950` · Machina `43111`.

### Path reverse — Machina → loreBOX (awareness only)

loreBOX does **not** need a live subscription. On mint, it peeks once. That is enough for v1.

No shared SQLite. No “event bus framework.” Two toys, one drop file, one POST.

## What Slice A deliberately does *not* do

- Does not modify loreBOX / shotBOX
- Does not auto-open other ROMs
- Does not require Deck Host multi-ROM deck
- Does not invent LEDGER / alphabet cords

When hands greenlight Slice C/D, implement whisper as above — still no conspiracy, just manners.
