# meta-time-machine

**Charlie-toy · TPS / Time Machina** — pocket time rail ROM.

Load a sealed sdk-import **`.glassbox`**, walk messages by **chip**, keep **history books** of which datmats were born on which line.

| | |
|--|--|
| **Status** | **Slice A live** (load · walk · empty book · mats 0 · cord publish) |
| Planner | [docs/meta-time-machine-planner.md](./docs/meta-time-machine-planner.md) |
| Cords (no conspiracy) | [prod/cords/README.md](./prod/cords/README.md) |
| Hands rough UI | [docs/early-rail-mockup.png](./docs/early-rail-mockup.png) |
| Rail mock HTML | [docs/rail-mock-refined.html](./docs/rail-mock-refined.html) |
| Figma | [Time Machina · rail](https://www.figma.com/design/ILka9ftaaO25YlxqAI2KrK) |

### Run

```bat
cd prod
run-machina.bat
```

Launches Deck Host with **`--profile companion`** (narrow rail, always-on-top) — not a full desk ROM window.

- Classic big window: set `DECK_HOST_PROFILE=desk` then run  
- Browser-only: `prod\run-machina-browser.bat` → http://127.0.0.1:43111/

### Layout

```
meta-time-machine/
  docs/                 planner + mocks
  prod/
    machina_sys/        ROM UI + server
    books/              *.tpsbook per export_id (history books)
    cords/              session.now + cord law
    run-machina.bat
    run-in-deck-host.py
```

Wood prep ROM: `../sdk-import-station/` → `work/exports/*.glassbox`.

### Next slices

| | |
|--|--|
| **B** | Book polish / browse |
| **C** | Manual register mat UI on rail |
| **D** | One ROM whisper (loreBOX peeks cord, POSTs mat) — still no mutual imports |
| **E** | Thin desktop rail polish |
