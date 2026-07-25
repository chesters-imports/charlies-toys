# Store + paper naming (discuss / freeze later)

sdk-import is a **charlie-toy**, not a DATBOX SKU — but its **sealed wood** should still land as **mats** other systems can import, with names that rhyme with DATBOX paper.

## What is not the store

| Thing | Role |
|--------|------|
| `.glass-logs/` | Immutable glass mountain (OT/NT) — **not** the product store |
| `work/wip/` | Scratch while cutting |
| `work/glass/` | Per-core extracts for the desk (runtime) |

## What *is* the store (proposal)

**Working name: `timberBOX` / timber store** (hands can rename)

- **Being energy:** sawn logs from glass — sanitized, part-split, ready for DATBOX / yard / graph later  
- **Not** “ledger” · not Chester-as-sqlite-king  
- Unique enough to sit beside **loreBOX** / **shotBOX** without colliding

Alt names if timber is wrong: `sawmillBOX`, `importBOX`, `yardwood` — pick when freezing.

## House-format outputs (align with DATBOX paper)

DATBOX pattern today:

| Product | Extension | Stem idea |
|---------|-----------|-----------|
| loreBOX | `.lore` | TYPE A stem from box name |
| shotBOX | `.shot` | `{STEM}` / cards `{STEM}-001` |

**Live seal format (2026-07-25):** **`.glassbox`** — blackbox you can look inside.

| Piece | Form |
|--------|------|
| File | `{import_order}-{OT\|NT\|BN}-{face}[.{part}].glassbox` |
| Example | `001-OT-008.1.glassbox` |
| Msg chip | `{export_id}.{bag_seq}` e.g. `001-OT-008.1.0` |
| import_order | Mint-once **001…** at first seal / complete (prophetic release order) |
| Testament | **OT** · **NT** · **BN** (both) — future vaults/evernote nick codes TBD |
| bag_seq | Index **inside this glassbox section** (each split = its own log) |
| glass_seq | Original seq in full face (kept on message for return-to-log) |

Schema: `glassbox.v1` · path: `work/exports/`

## Law

- Glass never rewritten.  
- Store holds **operator seals**, not the mountain.  
- Naming freezes with Hands before mass rename of existing exports.
