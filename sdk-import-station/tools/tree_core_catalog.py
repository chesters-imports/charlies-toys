"""
Tree-core catalog — multi-testament glass → one numbered forest.

sdk-import-station defaults:
  OT  ALICE_BOX/.glass-logs/ot/conversations.json
  NT  ALICE_BOX/.glass-logs/nt/
  OUT island work/  (catalog.json + glass/ extracts + later wip/exports)

Union by conversation_id. Sort by create_time → face_id 001…N.
Does not rewrite glass inputs. Writes only under work/.

Usage:
  python tools/tree_core_catalog.py
  python tools/tree_core_catalog.py --ot path --nt path --out path
"""

from __future__ import annotations

import argparse
import json
import os
import time
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

# sdk-import-station defaults — glass-logs inputs, work/ outputs (no alphabet)
ISLAND = Path(__file__).resolve().parent.parent
DEFAULT_GLASS = Path(os.environ.get("GLASS_LOGS_ROOT", r"C:\ALICE_BOX\.glass-logs"))
DEFAULT_OT = DEFAULT_GLASS / "ot" / "conversations.json"
DEFAULT_NT = DEFAULT_GLASS / "nt"
DEFAULT_OUT = ISLAND / "work"


def _msg_count(mapping: dict[str, Any]) -> int:
    n = 0
    if not isinstance(mapping, dict):
        return 0
    for node in mapping.values():
        if not isinstance(node, dict):
            continue
        msg = node.get("message")
        if not msg or not isinstance(msg, dict):
            continue
        author = (msg.get("author") or {}) if isinstance(msg.get("author"), dict) else {}
        role = (author.get("role") or "").lower()
        if role not in ("user", "assistant"):
            continue
        content = msg.get("content") or {}
        parts = content.get("parts") if isinstance(content, dict) else None
        if not parts:
            continue
        text = " ".join(str(p) for p in parts if p is not None and str(p).strip())
        if text.strip():
            n += 1
    return n


def _mapping_size(mapping: Any) -> int:
    return len(mapping) if isinstance(mapping, dict) else 0


def _normalize_row(
    conv: dict[str, Any],
    *,
    testament: str,
    shard: str,
    export_key: str,
) -> dict[str, Any] | None:
    cid = str(conv.get("conversation_id") or conv.get("id") or "").strip()
    if not cid:
        return None
    ct = conv.get("create_time")
    try:
        create_time = float(ct) if ct is not None else 0.0
    except (TypeError, ValueError):
        create_time = 0.0
    ut = conv.get("update_time")
    try:
        update_time = float(ut) if ut is not None else None
    except (TypeError, ValueError):
        update_time = None
    mapping = conv.get("mapping") if isinstance(conv.get("mapping"), dict) else {}
    title = str(conv.get("title") or "untitled").strip() or "untitled"
    return {
        "conversation_id": cid,
        "title": title,
        "create_time": create_time,
        "update_time": update_time,
        "message_count": _msg_count(mapping),
        "mapping_nodes": _mapping_size(mapping),
        "is_archived": bool(conv.get("is_archived")),
        "testament": testament,
        "shard": shard,
        "export_key": export_key,
    }


def load_ot(path: Path) -> list[dict[str, Any]]:
    if not path.is_file():
        return []
    data = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(data, list):
        return []
    rows: list[dict[str, Any]] = []
    for conv in data:
        if not isinstance(conv, dict):
            continue
        r = _normalize_row(
            conv,
            testament="OT",
            shard=path.name,
            export_key="ot",
        )
        if r:
            rows.append(r)
    return rows


def load_nt(export_root: Path) -> list[dict[str, Any]]:
    if not export_root.is_dir():
        return []
    rows: list[dict[str, Any]] = []
    for shard in sorted(export_root.glob("conversations-*.json")):
        data = json.loads(shard.read_text(encoding="utf-8"))
        if not isinstance(data, list):
            continue
        for conv in data:
            if not isinstance(conv, dict):
                continue
            r = _normalize_row(
                conv,
                testament="NT",
                shard=shard.name,
                export_key="nt",
            )
            if r:
                rows.append(r)
    return rows


def merge_testaments(
    ot_rows: list[dict[str, Any]],
    nt_rows: list[dict[str, Any]],
) -> list[dict[str, Any]]:
    """
    One row per conversation_id.
    Prefer richer mapping for load (mapping_nodes, then message_count).
    Keep create_time = min known; title from preferred body source.
    """
    by_id: dict[str, dict[str, Any]] = {}

    def absorb(row: dict[str, Any]) -> None:
        cid = row["conversation_id"]
        if cid not in by_id:
            by_id[cid] = {
                "conversation_id": cid,
                "title": row["title"],
                "create_time": row["create_time"],
                "update_time": row.get("update_time"),
                "message_count": row["message_count"],
                "mapping_nodes": row["mapping_nodes"],
                "is_archived": row["is_archived"],
                "testaments": [row["testament"]],
                "sources": [
                    {
                        "testament": row["testament"],
                        "shard": row["shard"],
                        "export_key": row["export_key"],
                        "message_count": row["message_count"],
                        "mapping_nodes": row["mapping_nodes"],
                    }
                ],
                # preferred load pointer
                "load_testament": row["testament"],
                "load_shard": row["shard"],
                "load_export_key": row["export_key"],
            }
            return

        cur = by_id[cid]
        if row["testament"] not in cur["testaments"]:
            cur["testaments"].append(row["testament"])
        cur["sources"].append(
            {
                "testament": row["testament"],
                "shard": row["shard"],
                "export_key": row["export_key"],
                "message_count": row["message_count"],
                "mapping_nodes": row["mapping_nodes"],
            }
        )
        # earliest create
        if row["create_time"] and (
            not cur["create_time"] or row["create_time"] < cur["create_time"]
        ):
            cur["create_time"] = row["create_time"]
        # latest update
        ru, cu = row.get("update_time"), cur.get("update_time")
        if ru is not None and (cu is None or ru > cu):
            cur["update_time"] = ru
        # prefer richer body for load
        richer = (
            row["mapping_nodes"] > cur["mapping_nodes"]
            or (
                row["mapping_nodes"] == cur["mapping_nodes"]
                and row["message_count"] > cur["message_count"]
            )
        )
        if richer:
            cur["title"] = row["title"]
            cur["message_count"] = row["message_count"]
            cur["mapping_nodes"] = row["mapping_nodes"]
            cur["load_testament"] = row["testament"]
            cur["load_shard"] = row["shard"]
            cur["load_export_key"] = row["export_key"]
            cur["is_archived"] = row["is_archived"]

    for r in ot_rows:
        absorb(r)
    for r in nt_rows:
        absorb(r)

    merged = list(by_id.values())
    for m in merged:
        tags = sorted(set(m["testaments"]))
        if tags == ["OT", "NT"] or set(tags) == {"NT", "OT"}:
            m["testament_tag"] = "OT+NT"
        elif tags == ["OT"]:
            m["testament_tag"] = "OT"
        elif tags == ["NT"]:
            m["testament_tag"] = "NT"
        else:
            m["testament_tag"] = "+".join(tags)
        m["testaments"] = tags
    merged.sort(key=lambda r: (r["create_time"] or 0.0, r["conversation_id"]))
    return merged


def _safe_glass_name(conversation_id: str) -> str:
    """Filesystem-safe name from conversation_id."""
    s = "".join(ch if ch.isalnum() or ch in "-_" else "_" for ch in conversation_id)
    return s[:120] or "unknown"


def extract_glass_files(
    cores: list[dict[str, Any]],
    ot_path: Path,
    nt_root: Path,
    glass_dir: Path,
) -> int:
    """
    Write one JSON file per core under glass_dir so PHP never loads 230MB OT.
    Prefer load_export_key body (richer mapping already chosen in merge).
    """
    glass_dir.mkdir(parents=True, exist_ok=True)

    # Index OT once
    ot_by_id: dict[str, dict[str, Any]] = {}
    if ot_path.is_file():
        print("  indexing OT conversations.json …", flush=True)
        data = json.loads(ot_path.read_text(encoding="utf-8"))
        if isinstance(data, list):
            for conv in data:
                if not isinstance(conv, dict):
                    continue
                cid = str(conv.get("conversation_id") or conv.get("id") or "")
                if cid:
                    ot_by_id[cid] = conv
        print(f"  OT indexed {len(ot_by_id)}", flush=True)

    # Index NT shards as needed
    nt_cache: dict[str, list[dict[str, Any]]] = {}

    def nt_list(shard_name: str) -> list[dict[str, Any]]:
        if shard_name in nt_cache:
            return nt_cache[shard_name]
        path = nt_root / shard_name
        if not path.is_file():
            nt_cache[shard_name] = []
            return []
        data = json.loads(path.read_text(encoding="utf-8"))
        nt_cache[shard_name] = data if isinstance(data, list) else []
        return nt_cache[shard_name]

    written = 0
    for i, core in enumerate(cores, start=1):
        cid = core["conversation_id"]
        key = core.get("load_export_key") or "nt"
        conv: dict[str, Any] | None = None
        if key == "ot":
            conv = ot_by_id.get(cid)
        else:
            shard = core.get("load_shard") or core.get("shard") or ""
            for c in nt_list(shard):
                if not isinstance(c, dict):
                    continue
                if str(c.get("conversation_id") or c.get("id") or "") == cid:
                    conv = c
                    break
        if not conv:
            # fallback: try the other testament
            conv = ot_by_id.get(cid)
            if not conv:
                for shard_name in sorted(nt_root.glob("conversations-*.json")):
                    for c in nt_list(shard_name.name):
                        if (
                            isinstance(c, dict)
                            and str(c.get("conversation_id") or c.get("id") or "")
                            == cid
                        ):
                            conv = c
                            break
                    if conv:
                        break
        if not conv:
            print(f"  WARN missing glass for {core.get('face_id')} {cid[:16]}…")
            continue
        out = glass_dir / f"{_safe_glass_name(cid)}.json"
        out.write_text(
            json.dumps(conv, ensure_ascii=False, separators=(",", ":")),
            encoding="utf-8",
        )
        core["glass_file"] = str(out.relative_to(glass_dir.parent)).replace("\\", "/")
        # also store basename for PHP under tree_cores/glass/
        core["glass_basename"] = out.name
        written += 1
        if i % 100 == 0 or i == len(cores):
            print(f"  glass {i}/{len(cores)}", flush=True)
    return written


def build_catalog(
    ot_path: Path,
    nt_root: Path,
    out_dir: Path,
    *,
    extract_glass: bool = True,
) -> dict[str, Any]:
    ot_rows = load_ot(ot_path)
    nt_rows = load_nt(nt_root)
    merged = merge_testaments(ot_rows, nt_rows)

    cores: list[dict[str, Any]] = []
    for i, r in enumerate(merged, start=1):
        face = f"{i:03d}"
        ct = r["create_time"] or 0.0
        date_str = (
            datetime.fromtimestamp(ct, tz=timezone.utc).strftime("%Y-%m-%d")
            if ct
            else "unknown"
        )
        cores.append(
            {
                "face_id": face,
                "n": i,
                "conversation_id": r["conversation_id"],
                "title": r["title"],
                "create_time": r["create_time"],
                "create_date_utc": date_str,
                "update_time": r.get("update_time"),
                "message_count": r["message_count"],
                "mapping_nodes": r["mapping_nodes"],
                "is_archived": r["is_archived"],
                "testament_tag": r["testament_tag"],
                "testaments": r["testaments"],
                "load_testament": r["load_testament"],
                "load_shard": r["load_shard"],
                "load_export_key": r["load_export_key"],
                "sources": r["sources"],
                # back-compat for loaders that still read shard/export
                "shard": r["load_shard"],
                "export_key": r["load_export_key"],
                "display": (
                    f"{face} · {date_str} · [{r['testament_tag']}] · {r['title']}"
                ),
            }
        )

    out_dir.mkdir(parents=True, exist_ok=True)
    glass_written = 0
    if extract_glass:
        print("extracting per-core glass (PHP-safe) …", flush=True)
        glass_written = extract_glass_files(
            cores, ot_path, nt_root, out_dir / "glass"
        )
        print(f"  wrote {glass_written} glass files", flush=True)

    n_ot = sum(1 for c in cores if c["testament_tag"] == "OT")
    n_nt = sum(1 for c in cores if c["testament_tag"] == "NT")
    n_both = sum(1 for c in cores if c["testament_tag"] == "OT+NT")

    catalog = {
        "version": 3,
        "scheme": "union_create_time",
        "rule": (
            "union by conversation_id; order create_time asc; "
            "face_id 001…N; testament_tag OT|NT|OT+NT; "
            "load prefers richer mapping; glass/ one file per core"
        ),
        "built_at": int(time.time()),
        "sources": {
            "ot": {
                "path": str(ot_path.resolve()) if ot_path.is_file() else str(ot_path),
                "n": len(ot_rows),
                "label": "OT",
                "note": "first set / older export (z/conversations.json)",
            },
            "nt": {
                "path": str(nt_root.resolve()) if nt_root.is_dir() else str(nt_root),
                "n": len(nt_rows),
                "label": "NT",
                "note": "Thunderdome / NEW_MASTER_USE_THESE",
            },
        },
        "stats": {
            "n_cores": len(cores),
            "n_ot_only": n_ot,
            "n_nt_only": n_nt,
            "n_both": n_both,
            "ot_raw": len(ot_rows),
            "nt_raw": len(nt_rows),
            "glass_files": glass_written,
        },
        "n_cores": len(cores),
        "cores": cores,
    }

    (out_dir / "catalog.json").write_text(
        json.dumps(catalog, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    lines = [
        f"# Tree-core catalog — union OT+NT ({len(cores)} cores)",
        "",
        f"- OT raw: {len(ot_rows)} (`{ot_path.name}`)",
        f"- NT raw: {len(nt_rows)} (Thunderdome shards)",
        f"- Union: **{len(cores)}** (OT only {n_ot} · NT only {n_nt} · both {n_both})",
        f"- Glass files: {glass_written} under `tree_cores/glass/` (one chat each — PHP loads these)",
        "- Numbering: create_time ascending → face_id 001…N",
        "- `testament_tag` on each core (for material notes later)",
        "",
        "| # | date UTC | tag | msgs | title |",
        "|---|----------|-----|------|-------|",
    ]
    for c in cores:
        t = c["title"].replace("|", "\\|")
        lines.append(
            f"| {c['face_id']} | {c['create_date_utc']} | {c['testament_tag']} "
            f"| {c['message_count']} | {t} |"
        )
    (out_dir / "catalog.md").write_text("\n".join(lines) + "\n", encoding="utf-8")
    return catalog


def load_catalog(out_dir: Path | None = None) -> dict[str, Any] | None:
    path = (out_dir or DEFAULT_OUT) / "catalog.json"
    if not path.is_file():
        return None
    return json.loads(path.read_text(encoding="utf-8"))


def core_by_face(face: str | int, out_dir: Path | None = None) -> dict[str, Any] | None:
    cat = load_catalog(out_dir)
    if not cat:
        return None
    s = str(face).strip()
    key = f"{int(s):03d}" if s.isdigit() else s
    for c in cat.get("cores") or []:
        if c.get("face_id") == key:
            return c
        if s.isdigit() and c.get("n") == int(s):
            return c
    return None


def _export_root_for(core: dict[str, Any], cat: dict[str, Any] | None) -> Path:
    key = core.get("load_export_key") or core.get("export_key") or "nt"
    if cat and isinstance(cat.get("sources"), dict):
        src = cat["sources"].get(key) or {}
        p = Path(src.get("path") or "")
        if key == "ot" and p.is_file():
            return p.parent  # not used for OT file load
        if key == "nt" and p.is_dir():
            return p
        if key == "ot" and p.is_file():
            return p
    if key == "ot":
        return DEFAULT_OT
    return DEFAULT_NT


def load_conversation_blob(
    core: dict[str, Any],
    export_root: Path | None = None,
) -> dict[str, Any] | None:
    """Read full conversation — prefer per-core glass file (never loads full OT)."""
    cat = load_catalog()
    out_dir = DEFAULT_OUT
    # Prefer extracted single-chat glass
    base = core.get("glass_basename")
    if base:
        gp = out_dir / "glass" / str(base)
        if gp.is_file():
            return json.loads(gp.read_text(encoding="utf-8"))
    cid = core.get("conversation_id") or ""
    if cid:
        gp = out_dir / "glass" / f"{_safe_glass_name(str(cid))}.json"
        if gp.is_file():
            return json.loads(gp.read_text(encoding="utf-8"))

    # Slow fallback (dev only) — same as before
    key = core.get("load_export_key") or core.get("export_key") or "nt"
    shard_name = core.get("load_shard") or core.get("shard") or ""

    if key == "ot":
        ot_path = DEFAULT_OT
        if cat and cat.get("sources", {}).get("ot", {}).get("path"):
            ot_path = Path(cat["sources"]["ot"]["path"])
        if export_root and Path(export_root).is_file():
            ot_path = Path(export_root)
        if not ot_path.is_file():
            return None
        data = json.loads(ot_path.read_text(encoding="utf-8"))
        if not isinstance(data, list):
            return None
        for conv in data:
            if not isinstance(conv, dict):
                continue
            if str(conv.get("conversation_id") or conv.get("id") or "") == cid:
                return conv
        return None

    root = Path(export_root) if export_root else DEFAULT_NT
    if cat and cat.get("sources", {}).get("nt", {}).get("path"):
        p = Path(cat["sources"]["nt"]["path"])
        if p.is_dir():
            root = p
    shard_path = root / shard_name
    if not shard_path.is_file():
        return None
    data = json.loads(shard_path.read_text(encoding="utf-8"))
    if not isinstance(data, list):
        return None
    for conv in data:
        if not isinstance(conv, dict):
            continue
        if str(conv.get("conversation_id") or conv.get("id") or "") == cid:
            return conv
    return None


def extract_messages(conv: dict[str, Any]) -> list[dict[str, Any]]:
    mapping = conv.get("mapping") or {}
    if not isinstance(mapping, dict):
        return []
    rows: list[dict[str, Any]] = []
    for mid, node in mapping.items():
        if not isinstance(node, dict):
            continue
        msg = node.get("message")
        if not msg or not isinstance(msg, dict):
            continue
        author = msg.get("author") if isinstance(msg.get("author"), dict) else {}
        role = (author.get("role") or "").lower()
        if role not in ("user", "assistant"):
            continue
        content = msg.get("content") if isinstance(msg.get("content"), dict) else {}
        parts = content.get("parts") or []
        texts = [str(p) for p in parts if p is not None and str(p).strip()]
        if not texts:
            continue
        ct = msg.get("create_time")
        try:
            create_time = float(ct) if ct is not None else None
        except (TypeError, ValueError):
            create_time = None
        rows.append(
            {
                "message_id": str(mid),
                "role": role,
                "create_time": create_time,
                "text": "\n".join(texts),
            }
        )
    rows.sort(
        key=lambda r: (
            r["create_time"] is None,
            r["create_time"] or 0.0,
            r["message_id"],
        )
    )
    for i, r in enumerate(rows):
        r["seq"] = i
    return rows


def main() -> None:
    ap = argparse.ArgumentParser(description="Build OT+NT union tree-core catalog")
    ap.add_argument("--ot", type=Path, default=DEFAULT_OT, help="OT conversations.json")
    ap.add_argument("--nt", type=Path, default=DEFAULT_NT, help="NT export folder")
    ap.add_argument("--out", type=Path, default=DEFAULT_OUT)
    args = ap.parse_args()
    cat = build_catalog(args.ot, args.nt, args.out)
    st = cat["stats"]
    print(
        f"union {st['n_cores']} cores "
        f"(OT-only {st['n_ot_only']} · NT-only {st['n_nt_only']} · both {st['n_both']})"
    )
    print(f"  OT raw {st['ot_raw']} · NT raw {st['nt_raw']}")
    print(f"  → {args.out / 'catalog.json'}")
    if cat["cores"]:
        print("  first:", cat["cores"][0]["display"])
        print("  last:", cat["cores"][-1]["display"])


if __name__ == "__main__":
    main()
