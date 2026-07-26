#!/usr/bin/env python3
"""
Time Machina (TPS Machina) — Slice A desk server.

Loads sealed *.glassbox from sdk-import exports, walks messages,
keeps a history book per export_id (mats empty until Slice C/D).

Does NOT wire other ROMs. Cord endpoints are a deliberate stub:
  GET  /api/cord/now  — publish current chip (others may poll later)
  POST /api/cord/mat  — register a mat on current chip (manual; Slice C face)

Cords are optional files under prod/cords/ — no secret channels, no mutual imports.
"""

from __future__ import annotations

import json
import re
import sys
import time
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any
from urllib.parse import parse_qs, urlparse

MACHINA_SYS = Path(__file__).resolve().parent
PROD = MACHINA_SYS.parent
BOOKS = PROD / "books"
CORDS = PROD / "cords"
# Cord "now" is a text blob, not a .json shrine (content is still structured)
SESSION_PATH = CORDS / "session.now"
SESSION_PATH_LEGACY = CORDS / "session.now.json"

# ALICE_BOX/charlies-toys/meta-time-machine/prod → parents[2] = charlies-toys
CHARLIES = PROD.parents[1]
DEFAULT_EXPORTS = CHARLIES / "sdk-import-station" / "work" / "exports"

HOST = "127.0.0.1"
PORT = 43111  # distinct from loreBOX 42929 / shotBOX 43001

# In-memory pocket (rebuilt from disk cord on boot if present)
_state: dict[str, Any] = {
    "pocket_on": True,
    "export_path": None,
    "export_id": None,
    "meta": None,
    "messages": [],
    "ven_pot": {},
    "cursor": 0,
}


def ensure_dirs() -> None:
    BOOKS.mkdir(parents=True, exist_ok=True)
    CORDS.mkdir(parents=True, exist_ok=True)


def load_json(path: Path) -> Any:
    return json.loads(path.read_text(encoding="utf-8-sig"))


def save_json(path: Path, data: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(
        json.dumps(data, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
        newline="\n",
    )


def exports_dir() -> Path:
    raw = (__import__("os").environ.get("MACHINA_EXPORTS") or "").strip()
    if raw:
        env = Path(raw)
        if env.is_dir():
            return env.resolve()
    return DEFAULT_EXPORTS.resolve()


def list_exports() -> list[dict[str, Any]]:
    """Flat list of sealed glassbox files (compat). Prefer list_export_lots()."""
    root = exports_dir()
    out: list[dict[str, Any]] = []
    if not root.is_dir():
        return out
    for p in sorted(root.glob("*.glassbox")):
        out.append(_export_item(p))
    return out


def _export_item(p: Path) -> dict[str, Any]:
    item: dict[str, Any] = {
        "file": p.name,
        "path": str(p.resolve()),
        "export_id": p.stem,
        "size": p.stat().st_size,
    }
    try:
        head = load_json(p)
        item["export_id"] = head.get("export_id") or p.stem
        item["title"] = head.get("title") or head.get("part_title") or ""
        item["testament"] = head.get("testament") or head.get("testament_tag") or ""
        item["msg_count"] = len(head.get("messages") or [])
        item["part"] = head.get("part")
        item["part_count"] = head.get("part_count")
        item["parent_face"] = head.get("parent_face") or head.get("face_id") or ""
        item["import_order"] = head.get("import_order")
        item["glass_title"] = head.get("glass_title") or head.get("yard_title") or ""
        item["conversation_id"] = head.get("conversation_id") or ""
        # Lot id groups segment chips of one main log: 001-OT-008
        eid = str(item["export_id"])
        m = re.match(r"^(\d+-(?:OT|NT|BN)-\d+)", eid)
        if m:
            item["lot_id"] = m.group(1)
        elif item.get("import_order") is not None and item.get("testament") and item.get(
            "parent_face"
        ):
            face = str(item["parent_face"]).split(".")[0]
            item["lot_id"] = f"{int(item['import_order']):03d}-{item['testament']}-{face}"
        else:
            item["lot_id"] = eid
    except (OSError, json.JSONDecodeError, TypeError):
        item["error"] = "unreadable"
        item["lot_id"] = p.stem
    return item


def list_export_lots() -> list[dict[str, Any]]:
    """
    Two-level browse: main glass log (lot) → sealed segment glassboxes.
    Example lot 001-OT-008 with parts .1 .2 .3 .4.
    """
    items = list_exports()
    lots: dict[str, dict[str, Any]] = {}
    for it in items:
        lid = it.get("lot_id") or it.get("export_id") or it["file"]
        lot = lots.get(lid)
        if not lot:
            lot = {
                "lot_id": lid,
                "glass_title": it.get("glass_title") or "",
                "testament": it.get("testament") or "",
                "import_order": it.get("import_order"),
                "parent_face": str(it.get("parent_face") or "").split(".")[0],
                "conversation_id": it.get("conversation_id") or "",
                "segments": [],
            }
            lots[lid] = lot
        if it.get("glass_title") and not lot.get("glass_title"):
            lot["glass_title"] = it["glass_title"]
        lot["segments"].append(it)

    out: list[dict[str, Any]] = []
    for lid in sorted(lots.keys()):
        lot = lots[lid]
        segs = lot["segments"]
        segs.sort(
            key=lambda s: (
                s.get("part") is None,
                s.get("part") if isinstance(s.get("part"), int) else 999,
                s.get("export_id") or s.get("file") or "",
            )
        )
        lot["segment_count"] = len(segs)
        lot["msg_total"] = sum(int(s.get("msg_count") or 0) for s in segs)
        # Prefer glass_title; else first segment title
        if not lot.get("glass_title") and segs:
            lot["glass_title"] = segs[0].get("title") or segs[0].get("export_id") or lid
        out.append(lot)
    return out


def book_path(export_id: str) -> Path:
    """History book: {export_id}.tpsbook  (no .json — Jason can sit this one out)."""
    safe = re.sub(r"[^\w.\-]+", "_", export_id or "unknown")
    return BOOKS / f"{safe}.tpsbook"


def book_path_legacy(export_id: str) -> Path:
    return BOOKS / f"{re.sub(r'[^\w.\-]+', '_', export_id or 'unknown')}.tpsbook.json"


def empty_book(export_id: str, meta: dict[str, Any] | None = None) -> dict[str, Any]:
    meta = meta or {}
    return {
        "schema": "tpsbook.v0",
        "export_id": export_id,
        "title": meta.get("title") or meta.get("part_title") or "",
        "testament": meta.get("testament") or meta.get("testament_tag") or "",
        "source_file": meta.get("_source_file") or "",
        "created_at": time.time(),
        "updated_at": time.time(),
        "entries": [],
    }


def load_or_create_book(export_id: str, meta: dict[str, Any] | None = None) -> dict[str, Any]:
    path = book_path(export_id)
    legacy = book_path_legacy(export_id)
    if path.is_file():
        try:
            return load_json(path)
        except (OSError, json.JSONDecodeError):
            pass
    if legacy.is_file():
        try:
            book = load_json(legacy)
            save_json(path, book)
            try:
                legacy.unlink()
            except OSError:
                pass
            return book
        except (OSError, json.JSONDecodeError):
            pass
    book = empty_book(export_id, meta)
    save_json(path, book)
    return book


def ensure_entry(book: dict[str, Any], msg: dict[str, Any]) -> dict[str, Any]:
    """Ensure a book row exists for this message (mats stay empty in Slice A)."""
    chip = msg.get("chip_id") or ""
    mid = msg.get("message_id") or ""
    entries = book.setdefault("entries", [])
    for e in entries:
        if (chip and e.get("chip_id") == chip) or (
            mid and e.get("message_id") == mid
        ):
            return e
    entry = {
        "message_id": mid,
        "chip_id": chip,
        "bag_seq": msg.get("bag_seq"),
        "glass_seq": msg.get("glass_seq"),
        "role": msg.get("role"),
        "event_unix": msg.get("create_time"),
        "event_iso": msg.get("create_time_iso"),
        "mats": [],
    }
    entries.append(entry)
    book["updated_at"] = time.time()
    return entry


def mats_on_current() -> int:
    msg = current_message()
    if not msg or not _state.get("export_id"):
        return 0
    book = load_or_create_book(_state["export_id"], _state.get("meta") or {})
    chip = msg.get("chip_id") or ""
    mid = msg.get("message_id") or ""
    for e in book.get("entries") or []:
        if (chip and e.get("chip_id") == chip) or (
            mid and e.get("message_id") == mid
        ):
            return len(e.get("mats") or [])
    return 0


def current_message() -> dict[str, Any] | None:
    msgs = _state.get("messages") or []
    if not msgs:
        return None
    i = int(_state.get("cursor") or 0)
    i = max(0, min(i, len(msgs) - 1))
    return msgs[i]


def vencodes_for_message(msg: dict[str, Any] | None) -> list[dict[str, Any]]:
    """VEN codes present on this chip (from glassbox ven_pot / message.vencodes)."""
    if not msg:
        return []
    raw = msg.get("vencodes")
    if isinstance(raw, list) and raw:
        out: list[dict[str, Any]] = []
        for v in raw:
            if not isinstance(v, dict):
                continue
            code = str(v.get("code") or "").strip()
            if not code:
                continue
            out.append(
                {
                    "code": code,
                    "alias": str(v.get("alias") or "").strip(),
                }
            )
        return out
    pot = _state.get("ven_pot") or {}
    chip = str(msg.get("chip_id") or "")
    if chip and isinstance(pot, dict) and chip in pot:
        rows = pot.get(chip) or []
        if isinstance(rows, list):
            return vencodes_for_message({"vencodes": rows})
    return []


def publish_cord_now() -> dict[str, Any]:
    """Write cords/session.now — mat clock + vencodes on current chip."""
    msg = current_message()
    vens = vencodes_for_message(msg)
    payload = {
        "schema": "machina.cord.now.v0",
        "rom": "meta-time-machine",
        "pocket_on": bool(_state.get("pocket_on")),
        "export_id": _state.get("export_id"),
        "export_path": _state.get("export_path"),
        "cursor": _state.get("cursor"),
        "msg_count": len(_state.get("messages") or []),
        "updated_at": time.time(),
        "current": None,
        "vencodes": vens,
    }
    if msg:
        payload["current"] = {
            "chip_id": msg.get("chip_id"),
            "message_id": msg.get("message_id"),
            "role": msg.get("role"),
            "event_unix": msg.get("create_time"),
            "event_iso": msg.get("create_time_iso"),
            "bag_seq": msg.get("bag_seq"),
            "glass_seq": msg.get("glass_seq"),
            "text_preview": (msg.get("text") or "")[:160],
            "vencodes": vens,
        }
    save_json(SESSION_PATH, payload)
    if SESSION_PATH_LEGACY.is_file():
        try:
            SESSION_PATH_LEGACY.unlink()
        except OSError:
            pass
    return payload


def session_public() -> dict[str, Any]:
    msgs = _state.get("messages") or []
    n = len(msgs)
    cur = int(_state.get("cursor") or 0)
    if n:
        cur = max(0, min(cur, n - 1))
    msg = msgs[cur] if n else None
    meta = _state.get("meta") or {}
    return {
        "pocket_on": bool(_state.get("pocket_on")),
        "export_id": _state.get("export_id"),
        "export_path": _state.get("export_path"),
        "exports_dir": str(exports_dir()),
        "meta": {
            "export_id": meta.get("export_id") or _state.get("export_id"),
            "title": meta.get("title") or meta.get("part_title") or "",
            "testament": meta.get("testament") or meta.get("testament_tag") or "",
            "part": meta.get("part"),
            "part_count": meta.get("part_count"),
            "msg_count": n,
            "glass_title": meta.get("glass_title") or meta.get("yard_title") or "",
        },
        "cursor": cur,
        "msg_count": n,
        "message": msg,
        "vencodes": vencodes_for_message(msg),
        "mats_on_chip": mats_on_current(),
        "book_file": (
            book_path(_state["export_id"]).name if _state.get("export_id") else None
        ),
    }


def load_glassbox(path: Path) -> dict[str, Any]:
    path = path.resolve()
    if not path.is_file() or path.suffix.lower() != ".glassbox":
        raise ValueError("not a .glassbox file")
    # stay inside exports dir when possible
    root = exports_dir().resolve()
    try:
        path.relative_to(root)
    except ValueError:
        # allow absolute pick only if filename ends glassbox and exists
        if path.parent.resolve() != root and not str(path).endswith(".glassbox"):
            raise ValueError("glassbox outside exports dir")

    data = load_json(path)
    if not isinstance(data, dict):
        raise ValueError("glassbox root must be object")
    msgs = data.get("messages")
    if not isinstance(msgs, list):
        raise ValueError("glassbox missing messages[]")

    export_id = data.get("export_id") or path.stem
    meta = dict(data)
    meta.pop("messages", None)
    meta["_source_file"] = path.name

    # ven_pot: chip_id → [{code, alias}]; also hydrate message.vencodes if only pot exists
    ven_pot = data.get("ven_pot") if isinstance(data.get("ven_pot"), dict) else {}
    if not ven_pot:
        # rebuild from per-message vencodes when pot missing
        for m in msgs:
            if not isinstance(m, dict):
                continue
            chip = str(m.get("chip_id") or "")
            vens = m.get("vencodes")
            if chip and isinstance(vens, list) and vens:
                ven_pot[chip] = vens
    else:
        for m in msgs:
            if not isinstance(m, dict):
                continue
            chip = str(m.get("chip_id") or "")
            if chip and chip in ven_pot and not m.get("vencodes"):
                m["vencodes"] = ven_pot[chip]

    _state["export_path"] = str(path)
    _state["export_id"] = export_id
    _state["meta"] = meta
    _state["messages"] = msgs
    _state["ven_pot"] = ven_pot
    _state["cursor"] = 0

    book = load_or_create_book(export_id, meta)
    if msgs:
        ensure_entry(book, msgs[0])
        save_json(book_path(export_id), book)

    publish_cord_now()
    return session_public()


def set_cursor(index: int | None = None, delta: int | None = None) -> dict[str, Any]:
    msgs = _state.get("messages") or []
    if not msgs:
        return session_public()
    cur = int(_state.get("cursor") or 0)
    if index is not None:
        cur = int(index)
    elif delta is not None:
        cur = cur + int(delta)
    cur = max(0, min(cur, len(msgs) - 1))
    _state["cursor"] = cur

    export_id = _state.get("export_id")
    if export_id:
        book = load_or_create_book(export_id, _state.get("meta") or {})
        ensure_entry(book, msgs[cur])
        save_json(book_path(export_id), book)

    publish_cord_now()
    return session_public()


def register_mat(body: dict[str, Any]) -> dict[str, Any]:
    """Manual mat register (Slice C surface; available early for cord tests)."""
    if not _state.get("pocket_on"):
        return {"ok": False, "error": "pocket off — refuse bind"}
    msg = current_message()
    if not msg or not _state.get("export_id"):
        return {"ok": False, "error": "no current message"}
    mat = (body.get("mat") or body.get("slug") or body.get("code") or "").strip()
    if not mat:
        return {"ok": False, "error": "mat required"}
    book = load_or_create_book(_state["export_id"], _state.get("meta") or {})
    entry = ensure_entry(book, msg)
    mats = entry.setdefault("mats", [])
    row = {
        "mat": mat,
        "stem": body.get("stem") or "",
        "unit_code": body.get("unit_code") or "",
        "rom": body.get("rom") or "manual",
        "at": time.time(),
    }
    # VEN codes present on the current chip (from glass ven_pot) — who on this TPS
    vens = body.get("vencodes")
    if not isinstance(vens, list) or not vens:
        vens = vencodes_for_message(msg)
    if vens:
        row["vencodes_present"] = vens
    mats.append(row)
    book["updated_at"] = time.time()
    save_json(book_path(_state["export_id"]), book)
    publish_cord_now()
    return {"ok": True, "entry": entry, "session": session_public()}


def unregister_mat(body: dict[str, Any]) -> dict[str, Any]:
    """
    Remove a mat from history books (card delete / unbind).
    Does not require pocket on — cleanup must work after the fact.
    Scopes:
      - export_id + chip_id if provided (tight)
      - export_id only → all chips in that book
      - neither → scan all *.tpsbook for matching mat code
    """
    mat = (body.get("mat") or body.get("slug") or body.get("code") or "").strip()
    if not mat:
        return {"ok": False, "error": "mat required"}
    want_export = (body.get("export_id") or body.get("tps_export") or "").strip()
    want_chip = (body.get("chip_id") or body.get("tps_chip") or "").strip()
    rom_filter = (body.get("rom") or "").strip()

    paths: list[Path] = []
    if want_export:
        p = book_path(want_export)
        if p.is_file():
            paths.append(p)
        leg = book_path_legacy(want_export)
        if leg.is_file():
            paths.append(leg)
    else:
        paths.extend(sorted(BOOKS.glob("*.tpsbook")))
        paths.extend(sorted(BOOKS.glob("*.tpsbook.json")))

    removed: list[dict[str, Any]] = []
    seen: set[str] = set()
    for path in paths:
        key = str(path.resolve())
        if key in seen:
            continue
        seen.add(key)
        try:
            book = load_json(path)
        except (OSError, json.JSONDecodeError):
            continue
        dirty = False
        for entry in book.get("entries") or []:
            if want_chip and (entry.get("chip_id") or "") != want_chip:
                continue
            mats = entry.get("mats") or []
            keep: list[Any] = []
            for row in mats:
                if not isinstance(row, dict):
                    keep.append(row)
                    continue
                code = row.get("mat") or row.get("unit_code") or ""
                same = code == mat
                if same and rom_filter and row.get("rom") and row.get("rom") != rom_filter:
                    keep.append(row)
                    continue
                if same:
                    removed.append(
                        {
                            "export_id": book.get("export_id")
                            or path.name.replace(".tpsbook.json", "").replace(
                                ".tpsbook", ""
                            ),
                            "chip_id": entry.get("chip_id"),
                            "mat": mat,
                            "rom": row.get("rom"),
                        }
                    )
                    dirty = True
                else:
                    keep.append(row)
            if dirty:
                entry["mats"] = keep
        if dirty:
            book["updated_at"] = time.time()
            # always save as .tpsbook (no .json)
            eid = book.get("export_id") or path.name.replace(".tpsbook.json", "").replace(
                ".tpsbook", ""
            )
            save_json(book_path(str(eid)), book)
            if path.suffix == ".json" or path.name.endswith(".tpsbook.json"):
                try:
                    path.unlink()
                except OSError:
                    pass

    publish_cord_now()
    return {
        "ok": True,
        "removed": removed,
        "count": len(removed),
        "session": session_public(),
    }


class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args: Any, **kwargs: Any) -> None:
        super().__init__(*args, directory=str(MACHINA_SYS), **kwargs)

    def log_message(self, fmt: str, *args: Any) -> None:
        sys.stderr.write("%s - %s\n" % (self.address_string(), fmt % args))

    def end_headers(self) -> None:
        # Local ROM cords: loreBOX / shotBOX may POST from other origins (ports).
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, DELETE, OPTIONS")
        self.send_header(
            "Access-Control-Allow-Headers", "Content-Type, Accept"
        )
        super().end_headers()

    def do_OPTIONS(self) -> None:  # noqa: N802
        self.send_response(204)
        self.send_header("Content-Length", "0")
        self.end_headers()

    def _json(self, code: int, data: Any) -> None:
        body = json.dumps(data, ensure_ascii=False).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def _read_json(self) -> dict[str, Any]:
        n = int(self.headers.get("Content-Length") or 0)
        if n <= 0:
            return {}
        raw = self.rfile.read(n)
        try:
            data = json.loads(raw.decode("utf-8"))
        except json.JSONDecodeError:
            return {}
        return data if isinstance(data, dict) else {}

    def do_GET(self) -> None:  # noqa: N802
        u = urlparse(self.path)
        path = u.path.rstrip("/") or "/"

        if path == "/api/health":
            self._json(
                200,
                {
                    "ok": True,
                    "rom": "meta-time-machine",
                    "slice": "A",
                    "build": "ven-pot-v1",
                    "port": PORT,
                    "exports_dir": str(exports_dir()),
                    "ven_pot_size": len(_state.get("ven_pot") or {}),
                },
            )
            return

        if path == "/api/exports":
            items = list_exports()
            self._json(
                200,
                {
                    "exports_dir": str(exports_dir()),
                    "items": items,
                    "lots": list_export_lots(),
                },
            )
            return

        if path == "/api/session":
            self._json(200, session_public())
            return

        if path == "/api/book":
            eid = _state.get("export_id")
            if not eid:
                self._json(200, {"book": None})
                return
            book = load_or_create_book(eid, _state.get("meta") or {})
            self._json(200, {"book": book, "path": book_path(eid).name})
            return

        if path == "/api/cord/now":
            # Prefer live memory; refresh disk
            self._json(200, publish_cord_now())
            return

        if path.startswith("/api/"):
            self._json(404, {"error": "unknown api"})
            return

        super().do_GET()

    def do_POST(self) -> None:  # noqa: N802
        u = urlparse(self.path)
        path = u.path.rstrip("/") or "/"
        body = self._read_json()

        if path == "/api/load":
            name = (body.get("file") or body.get("name") or "").strip()
            raw_path = (body.get("path") or "").strip()
            try:
                if raw_path:
                    p = Path(raw_path)
                elif name:
                    p = exports_dir() / Path(name).name
                else:
                    self._json(400, {"error": "file or path required"})
                    return
                if not p.is_file():
                    self._json(404, {"error": f"not found: {p.name}"})
                    return
                sess = load_glassbox(p)
                self._json(200, {"ok": True, "session": sess})
            except (OSError, ValueError, json.JSONDecodeError) as e:
                self._json(400, {"error": str(e)})
            return

        if path == "/api/cursor":
            if "index" in body:
                sess = set_cursor(index=int(body["index"]))
            elif "delta" in body:
                sess = set_cursor(delta=int(body["delta"]))
            else:
                sess = session_public()
            self._json(200, {"ok": True, "session": sess})
            return

        if path == "/api/pocket":
            if "on" in body:
                _state["pocket_on"] = bool(body["on"])
                publish_cord_now()
            self._json(200, {"ok": True, "session": session_public()})
            return

        if path == "/api/cord/mat":
            self._json(200, register_mat(body))
            return

        if path in ("/api/cord/mat/remove", "/api/cord/unmat"):
            self._json(200, unregister_mat(body))
            return

        self._json(404, {"error": "unknown api"})

    def do_DELETE(self) -> None:  # noqa: N802
        u = urlparse(self.path)
        path = u.path.rstrip("/") or "/"
        body = self._read_json()
        # DELETE /api/cord/mat  body: { mat, chip_id?, export_id? }
        if path == "/api/cord/mat":
            self._json(200, unregister_mat(body))
            return
        self._json(404, {"error": "unknown api"})


def main() -> int:
    ensure_dirs()
    # restore empty cord
    publish_cord_now()
    httpd = ThreadingHTTPServer((HOST, PORT), Handler)
    print(f"Time Machina · http://{HOST}:{PORT}/", flush=True)
    print(f"  exports: {exports_dir()}", flush=True)
    print(f"  books:   {BOOKS}", flush=True)
    print(f"  cords:   {CORDS} (session.now)", flush=True)
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nstop", flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
