# Glass + IO paths

## Live glass root (desk)

```
C:\ALICE_BOX\.glass-logs\
```

| Key | Path | Role |
|-----|------|------|
| **ot** | `C:\ALICE_BOX\.glass-logs\ot\` | Full OT export: `conversations.json` + media (`file-*`, etc.) |
| **nt** | `C:\ALICE_BOX\.glass-logs\nt\` | NT master shards (was NEW_MASTER_USE_THESE) |

**No tree_cores in glass-logs.** ROM work product:

| Output | Path |
|--------|------|
| catalog | `sdk-import-station/work/catalog.json` |
| per-core glass | `sdk-import-station/work/glass/` |
| wip | `sdk-import-station/work/wip/` |
| sealed wood | `sdk-import-station/work/exports/*.glassbox` |
| | name: `{import_order}-{OT\|NT\|BN}-{face}[.{part}].glassbox` |
| | msg chip: `{export_id}.{bag_seq}` e.g. `001-OT-008.1.0` |
| **station paper (fileKeeper chips)** | `sdk-import-station/paper/{station}/` |
| | **archive.** leaf chips + `terminal[{auth}]-filekeeper.bin`. Not the live library. |
| **FILES library (live)** | `sdk-import-station/library/` |
| | Go paper vault. Pocket Go connects it as `roam.files`. Halls: **io** · **ab** · **cu** · **dr** · **osx** · **dc**. Starts empty. Do not copy chips or `go.terminals` into it. |

```
GLASS_LOGS_ROOT=C:\ALICE_BOX\.glass-logs
SDK_PAPER_ROOT=C:\ALICE_BOX\charlies-toys\sdk-import-station\paper\io
SDK_LIBRARY_ROOT=C:\ALICE_BOX\charlies-toys\sdk-import-station\library
```

**Live FILES library** is `library/` (markdown). The FILES desk still mounts chip paper for the toy until that room reads the vault. Other colors' chips sit on disk as archive.

**Paper ≠ glass ≠ library.** New life files go under `library/{station}`. Chat mountain stays under `.glass-logs`. Do not dump notes into `go.terminals`.

### Ledger → paper port (2026-07-26)

Hands’ prior fileKeeper work lived in Chester SQLite (not vaults):

- `Builds/my-pocket-internet/d/_LEDGER/chesters_imports.sqlite` (primary)
- `…/mypi.sqlite` (small extra set)

1. `tools/_port_ledger_files.php` — first land (all under io, oops).  
2. `tools/_rehome_by_station.php` — re-stamp + move by ledger **`dom`**:

| station | example content |
|---------|-----------------|
| **io** | most life bag, MUSIC, ALEPH BET, SINE_WAVE, IO welcome |
| **ab** | **MLK-038 LEEK FILES** (K), AGENT_TRACKER, WOLF IN THE WIRES, rEMINDERS, ABX welcome |
| **icu** | ICU welcome |
| **osx** | OSX welcome |

Safe re-run of port skips existing `legacy_stem`. Rehome is idempotent-ish (rewrites station field + path).

## Builds backup only (do not treat as live home)

```
C:\Builds\my-pocket-internet\z\conversations.json
C:\Builds\my-pocket-internet\z\logs\NEW_MASTER_USE_THESE\
C:\Builds\my-pocket-internet\z\logs\tree_cores\   ← abandoned for restart; optional excavate
```

## Agent rules

1. **Live reads/writes for new IO work** → `.glass-logs` + ROM work dirs.  
2. **Do not** robocopy glass into island repos.  
3. **Do not** delete Builds backup unless hands say so.  
4. Care rail: no forced full re-read of worst glass for extract UX.

## History

- 2026-07-25: NT + OT conversations **copied** into `.glass-logs`; tree_cores **not** migrated (restart).  
- 2026-07-25: Full OT materials from `D:\ChatGPT\raw-export-data` → `.glass-logs\ot\` (~2.2 GB, images included).  
- Pre-sawed dated `z\logs\2025-*` files left in graveyard only.
