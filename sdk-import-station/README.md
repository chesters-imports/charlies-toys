# sdk-import-station

**Charlie-toy · AIDM SDK (lol)**  
IO machine as a **ROM**: import → cut / encode / redact → **seal export**.  
Alphabet abandoned. Glass mountain stays under `ALICE_BOX/.glass-logs`.

## Run

**The Deck Host (preferred):**

```bat
cd C:\ALICE_BOX\charlies-toys\sdk-import-station
run-sdk-import.bat
```

**Browser only:**

```bat
run.bat
```

→ **http://127.0.0.1:43101/** · health ` /api/health`  
First catalog: `run-catalog.bat` (slow once).

Sealed store naming discussion: [STORE.md](./STORE.md).

## Rooms (import / export only)

| URL | Job |
|-----|-----|
| `/?room=desk` | Load core # · WIP · split · encode · redact · seal |
| `/?room=active` | Active WIP list |
| `/?room=search` | Keyword hunt over glass · hit counts · open → desk |
| `/?room=exports` | Sealed `export_*.json` |

Search: **partial** (elon∈belong) or **whole word** (elon≠belong). Does not seal — you still process for TPS.

## Shell (pass B)

**Looks like the green IO station** — rail (`>|` · IOX), sidebar tree, broken-top, status bar, scanlines / phosphor (`public/shell/`).  
**Is not the pocket internet** — no files, email, chat, multi-station login.  
Chrome = CRT glitch; log body = cleaner mono (`sdk-cognition.css`).

## Paths

| | |
|--|--|
| Glass (read) | `C:\ALICE_BOX\.glass-logs\ot` · `nt` |
| Work (write) | `work/catalog.json` · `glass/` · `wip/` · `exports/` |

See [PATHS.md](./PATHS.md) · [PORT.md](./PORT.md).

## Notes

- **No ledger / ven required** — encode→VEN push soft-fails without venDesk (encode book still works).  
- Glass inputs never written.  
- PHP: XAMPP `C:\xampp\php\php.exe`. Deck Host packaging next if you want the window frame.
