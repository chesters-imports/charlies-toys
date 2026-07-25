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

```
GLASS_LOGS_ROOT=C:\ALICE_BOX\.glass-logs
```

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
