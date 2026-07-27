# ineffable-box

**Charlie-toy · always-on reporter** for the ineffable: omens, hymns, secrets.

Not a terminal. Not invent-ory. **Ship at the moment of notice.**

Yellow butterfly out the window → Oman → type → **THROW IN**.

## Run

**Deck Host (preferred — small, on top):**

```bat
cd C:\ALICE_BOX\charlies-toys\ineffable-box\prod
run-ineffable.bat
```

Profile: **companion** (override `DECK_HOST_PROFILE=desk` if you want big).

**Browser only:** `run-browser.bat` → http://127.0.0.1:43120/

## Faces (services)

| Chip | Whom | Paper |
|------|------|-------|
| **OMAN** | Oman O’mente | `prod/paper/omens/*.ineff.json` |
| **JASMINE** | Princess Jasmine | `prod/paper/hymns/` |
| **TEEHEE** | Lil’ Secret Keeper | `prod/paper/secrets/` |

Tags field is always there (Charlie-shaped raw tags OK). Leave blank if you want.

## Chip codes (narrative)

Drops are **not** opaque hex. Filename + `id` / `chip` look like:

`OMN-yellow-swallowtail-YSW-260726-a3f2`

| Bit | Meaning |
|-----|---------|
| `OMN` / `HYM` / `SCR` | service pipe |
| slug | words from the notice |
| initials | TYPE A–style first letters |
| date | `yymmdd` of event |
| salt | short collision guard |

Also stores `source: ineffable-box`, `chip_face` (OMAN/JASMINE/TEEHEE), `whom`.  
So a later card can still tell **what pipe** and **what it said**.

## Law

- Buckets before Skyline buildings.  
- Skyline was the old landlord; **service faces** are the product.  
- fileKeeper = documents; this = field of the ineffable.  
- Invent dual-write optional later — v0 is **direct to the box**.

## Keys

- **Ctrl+Enter** in notice → throw  
- Face chips or deck menu to switch whom  

See also: `Builds/my-pocket-internet/mypi docs/REPORT-SERVICES-FROM-TERMINAL.md`
