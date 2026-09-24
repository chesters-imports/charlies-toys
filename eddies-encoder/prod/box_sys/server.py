#!/usr/bin/env python3
"""Eddy's Encoder Envelope · CO.TOY-004-EEE · port 43145 · Deck Host only"""

from __future__ import annotations

import json
import os
import random
import re
import secrets
import time
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any
from urllib.parse import parse_qs, urlparse

BOX_SYS = Path(__file__).resolve().parent
PROD = BOX_SYS.parent
SAFE = PROD / "safe_box"
ENTRIES = SAFE / "eddie.teehee"
LINKS = SAFE / "eddylinks.teehee"
PREFS = SAFE / "eddie_prefs.json"
PAPER = SAFE / "paper"
HOST = "127.0.0.1"
PORT = 43145

# Chester-ish letter pool (not full A-Z noise)
ANI = list("ABSDKIQEPLWXYZMNR")
QUAI = list("0123456789")

DEFAULT_LINK_TYPES = [
    "relates_to",
    "aka_of",
    "from_log_as",
    "saw_with",
    "not_the_same_as",
]


def now() -> int:
    return int(time.time())


def ensure_dir() -> None:
    SAFE.mkdir(parents=True, exist_ok=True)


def empty_entries() -> dict[str, Any]:
    return {
        "product": "eddies-encoder",
        "chip": "CO.TOY-004-EEE",
        "file": "eddie.teehee",
        "version": 1,
        "updated": now(),
        "link_types": list(DEFAULT_LINK_TYPES),
        "entries": [],
    }


def empty_links() -> dict[str, Any]:
    return {
        "product": "eddies-encoder",
        "chip": "CO.TOY-004-EEE",
        "file": "eddylinks.teehee",
        "version": 1,
        "updated": now(),
        "links": [],
    }


def _write_json(path: Path, doc: dict[str, Any]) -> None:
    ensure_dir()
    doc["updated"] = now()
    tmp = path.with_suffix(path.suffix + ".tmp")
    tmp.write_text(json.dumps(doc, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    tmp.replace(path)


def load_entries() -> dict[str, Any]:
    ensure_dir()
    if not ENTRIES.is_file():
        _write_json(ENTRIES, empty_entries())
    try:
        return json.loads(ENTRIES.read_text(encoding="utf-8"))
    except (json.JSONDecodeError, OSError):
        doc = empty_entries()
        _write_json(ENTRIES, doc)
        return doc


def load_links() -> dict[str, Any]:
    ensure_dir()
    if not LINKS.is_file():
        _write_json(LINKS, empty_links())
    try:
        return json.loads(LINKS.read_text(encoding="utf-8"))
    except (json.JSONDecodeError, OSError):
        doc = empty_links()
        _write_json(LINKS, doc)
        return doc


def save_entries(doc: dict[str, Any]) -> None:
    _write_json(ENTRIES, doc)


def save_links(doc: dict[str, Any]) -> None:
    _write_json(LINKS, doc)


def load_prefs() -> dict[str, Any]:
    ensure_dir()
    if not PREFS.is_file():
        return {"mode_angry": False, "updated": now()}
    try:
        doc = json.loads(PREFS.read_text(encoding="utf-8"))
        return doc if isinstance(doc, dict) else {"mode_angry": False}
    except (json.JSONDecodeError, OSError):
        return {"mode_angry": False, "updated": now()}


def save_prefs(doc: dict[str, Any]) -> None:
    ensure_dir()
    doc = dict(doc or {})
    doc["updated"] = now()
    _write_json(PREFS, doc)


def mint_kven() -> str:
    letters = "".join(random.choice(ANI) for _ in range(3))
    digits = "".join(random.choice(QUAI) for _ in range(3))
    return f"{letters}-{digits}"


def valid_kven(s: str) -> bool:
    return bool(re.fullmatch(r"[A-Za-z]{3}-\d{3}", (s or "").strip()))


def mint_crate_id() -> str:
    return "crate." + os.urandom(8).hex().upper()


def yaml_scalar(s: str) -> str:
    t = str(s or "")
    if not t:
        return '""'
    if re.search(r'[:#\[\]{}"\n&*!|>%@`]', t) or t.strip() != t or t.lower() in {
        "true",
        "false",
        "null",
        "yes",
        "no",
    }:
        return json.dumps(t, ensure_ascii=False)
    return t


def yaml_list(vals: list[str]) -> str:
    bits = [str(x).strip() for x in vals if str(x).strip()]
    if not bits:
        return "[]"
    return "[" + ", ".join(yaml_scalar(x) for x in bits) + "]"


def split_fm(text: str) -> tuple[dict[str, Any], str]:
    raw = (text or "").replace("\r\n", "\n").replace("\r", "\n")
    if raw.startswith("\ufeff"):
        raw = raw[1:]
    if not raw.startswith("---\n"):
        return {}, raw
    end = raw.find("\n---\n", 4)
    if end < 0:
        return {}, raw
    head = raw[4:end]
    body = raw[end + 5 :]
    meta: dict[str, Any] = {}
    for line in head.split("\n"):
        if not line.strip() or line.lstrip().startswith("#") or ":" not in line:
            continue
        key, val = line.split(":", 1)
        key = key.strip()
        val = val.strip()
        if not key:
            continue
        if val.startswith("[") and val.endswith("]"):
            inner = val[1:-1].strip()
            meta[key] = (
                [p.strip().strip('"').strip("'") for p in inner.split(",") if p.strip()]
                if inner
                else []
            )
        else:
            meta[key] = val.strip('"').strip("'")
    return meta, body


def paper_path(kven: str) -> Path:
    return PAPER / (kven.strip().upper() + ".md")


def edges_for(kven: str, links: list[dict[str, Any]]) -> list[str]:
    k = kven.strip().upper()
    out: list[str] = []
    seen: set[str] = set()
    for row in links or []:
        other = ""
        if str(row.get("from_kven") or "").strip().upper() == k:
            other = str(row.get("to_kven") or "").strip().upper()
        elif str(row.get("to_kven") or "").strip().upper() == k:
            other = str(row.get("from_kven") or "").strip().upper()
        if other and other not in seen and valid_kven(other):
            seen.add(other)
            out.append(other)
    return out


def pull_entry_from_paper(ent: dict[str, Any]) -> dict[str, Any]:
    """BIOS / roam Keep on the md file is the face Eddy should show."""
    kven = str(ent.get("kven") or "").strip().upper()
    if not valid_kven(kven):
        return ent
    path = paper_path(kven)
    if not path.is_file():
        return ent
    try:
        text = path.read_text(encoding="utf-8")
    except OSError:
        return ent
    meta, _body = split_fm(text)
    title = str(meta.get("title") or "").strip()
    if title:
        ent["label"] = title
    if "aliases" in meta:
        ent["alts"] = _str_list(meta.get("aliases"))
    if "matches" in meta:
        ent["matches"] = _str_list(meta.get("matches"))
    if str(meta.get("type") or "").strip():
        ent["type"] = str(meta.get("type") or "").strip()
    if "note" in meta:
        ent["notes"] = str(meta.get("note") or "").strip()
    crate = str(meta.get("crate") or "").strip()
    if crate:
        ent["crate"] = crate
    for key in ("first_occurred", "last_occurred"):
        if key in meta:
            ent[key] = meta.get(key) or None
    return ent


def write_kven_paper(ent: dict[str, Any], links: list[dict[str, Any]] | None = None) -> Path | None:
    """Write YAML face. Keep the body (your writing). Never eat a live crate."""
    kven = str(ent.get("kven") or "").strip().upper()
    if not valid_kven(kven):
        return None
    PAPER.mkdir(parents=True, exist_ok=True)
    path = paper_path(kven)
    meta: dict[str, Any] = {}
    body = ""
    if path.is_file():
        try:
            meta, body = split_fm(path.read_text(encoding="utf-8"))
        except OSError:
            meta, body = {}, ""
    crate = str(ent.get("crate") or meta.get("crate") or "").strip()
    if not crate:
        crate = mint_crate_id()
    ent["crate"] = crate
    label = str(ent.get("label") or meta.get("title") or kven).strip() or kven
    owned = {
        "crate": crate,
        "title": label,
        "kven": kven,
        "kind": "kven",
        "type": str(ent.get("type") or meta.get("type") or "person").strip() or "person",
        "aliases": list(ent.get("alts") or []),
        "matches": list(ent.get("matches") or []),
        "edges": edges_for(kven, links or []),
        "note": str(ent.get("notes") or "").strip(),
    }
    for key in ("first_occurred", "last_occurred"):
        val = ent.get(key)
        if val not in (None, "", []):
            owned[key] = val
    skip = {
        "crate",
        "title",
        "kven",
        "kind",
        "type",
        "aliases",
        "matches",
        "edges",
        "note",
        "first_occurred",
        "last_occurred",
    }
    extras = [(k, meta[k]) for k in meta if k not in skip]
    lines = ["---"]
    for key in (
        "crate",
        "title",
        "kven",
        "kind",
        "type",
        "aliases",
        "matches",
        "edges",
        "note",
        "first_occurred",
        "last_occurred",
    ):
        if key not in owned:
            continue
        val = owned[key]
        if key in {"aliases", "matches", "edges"}:
            lines.append(key + ": " + yaml_list(list(val)))
        elif key == "note" and not val:
            continue
        else:
            lines.append(key + ": " + yaml_scalar(str(val)))
    for key, val in extras:
        if isinstance(val, list):
            lines.append(key + ": " + yaml_list([str(x) for x in val]))
        else:
            lines.append(key + ": " + yaml_scalar(str(val)))
    lines.append("---")
    body = (body or "").replace("\r\n", "\n").replace("\r", "\n")
    if not body.strip():
        body = "# {{title}}\n"
    text = "\n".join(lines) + "\n\n" + body.lstrip("\n")
    if not text.endswith("\n"):
        text += "\n"
    path.write_text(text, encoding="utf-8", newline="\n")
    return path


def write_hall_if_missing() -> None:
    PAPER.mkdir(parents=True, exist_ok=True)
    shell = PAPER / "_shell.md"
    if not shell.is_file():
        shell.write_text(
            "---\n"
            "crate: " + mint_crate_id() + "\n"
            "title: Eddy's Encoder\n"
            "---\n\n"
            "{{paper}}\n",
            encoding="utf-8",
            newline="\n",
        )
    blot = PAPER / "index.md"
    if not blot.is_file():
        blot.write_text(
            "---\n"
            "crate: " + mint_crate_id() + "\n"
            "title: Eddy's Encoder\n"
            "---\n\n"
            "# {{title}}\n\n"
            "VEN slips. Roam this folder. Write on a slip; Eddy keeps the YAML face.\n\n"
            "{{files}}\n",
            encoding="utf-8",
            newline="\n",
        )


def sync_papers() -> None:
    write_hall_if_missing()
    e = load_entries()
    links = load_links().get("links") or []
    changed = False
    for ent in e.get("entries") or []:
        before = str(ent.get("crate") or "")
        write_kven_paper(ent, links)
        if str(ent.get("crate") or "") != before:
            changed = True
    if changed:
        save_entries(e)


def refresh_entries_from_paper(entries: list[dict[str, Any]]) -> list[dict[str, Any]]:
    return [pull_entry_from_paper(dict(ent)) for ent in entries]


def json_bytes(obj: Any, code: int = 200) -> tuple[int, bytes, str]:
    body = json.dumps(obj, ensure_ascii=False).encode("utf-8")
    return code, body, "application/json; charset=utf-8"


class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args: Any, **kwargs: Any) -> None:
        super().__init__(*args, directory=str(BOX_SYS), **kwargs)

    def log_message(self, fmt: str, *args: Any) -> None:
        print(f"[eddie] {self.address_string()} {fmt % args}")

    def _send(self, code: int, body: bytes, ctype: str) -> None:
        self.send_response(code)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def _read_json(self) -> dict[str, Any]:
        n = int(self.headers.get("Content-Length") or 0)
        raw = self.rfile.read(n) if n else b"{}"
        try:
            data = json.loads(raw.decode("utf-8") or "{}")
        except json.JSONDecodeError:
            return {}
        return data if isinstance(data, dict) else {}

    def do_GET(self) -> None:  # noqa: N802
        path = urlparse(self.path).path
        qs = parse_qs(urlparse(self.path).query)
        if path == "/api/health":
            code, body, ctype = json_bytes(
                {"ok": True, "product": "eddies-encoder", "chip": "CO.TOY-004-EEE", "port": PORT}
            )
            self._send(code, body, ctype)
            return
        if path == "/api/pull":
            # strange machine pull — candidate only, not saved
            code, body, ctype = json_bytes({"kven": mint_kven()})
            self._send(code, body, ctype)
            return
        if path == "/api/prefs":
            code, body, ctype = json_bytes({"ok": True, "prefs": load_prefs()})
            self._send(code, body, ctype)
            return
        if path == "/api/bag":
            e = load_entries()
            l = load_links()
            p = load_prefs()
            entries = refresh_entries_from_paper(e.get("entries") or [])
            code, body, ctype = json_bytes(
                {
                    "entries": entries,
                    "links": l.get("links") or [],
                    "link_types": e.get("link_types") or DEFAULT_LINK_TYPES,
                    "prefs": p,
                    "updated": max(int(e.get("updated") or 0), int(l.get("updated") or 0)),
                    "paper": str(PAPER),
                }
            )
            self._send(code, body, ctype)
            return
        if path == "/api/search":
            q = (qs.get("q") or [""])[0].strip().lower()
            e = load_entries()
            hits = []
            for ent in refresh_entries_from_paper(e.get("entries") or []):
                blob = " ".join(
                    [
                        ent.get("kven") or "",
                        ent.get("label") or "",
                        " ".join(ent.get("alts") or []),
                        " ".join(ent.get("matches") or []),
                        ent.get("notes") or "",
                        ent.get("type") or "",
                    ]
                ).lower()
                if not q or q in blob:
                    hits.append(ent)
            code, body, ctype = json_bytes({"entries": hits})
            self._send(code, body, ctype)
            return
        if path == "/" or path == "":
            self.path = "/index.html"
        return SimpleHTTPRequestHandler.do_GET(self)

    def do_POST(self) -> None:  # noqa: N802
        path = urlparse(self.path).path
        data = self._read_json()
        if path == "/api/prefs":
            p = load_prefs()
            if "mode_angry" in data:
                p["mode_angry"] = bool(data.get("mode_angry"))
            save_prefs(p)
            code, body, ctype = json_bytes({"ok": True, "prefs": p})
            self._send(code, body, ctype)
            return
        if path == "/api/entries":
            e = load_entries()
            kven = (data.get("kven") or "").strip().upper()
            if not valid_kven(kven):
                code, body, ctype = json_bytes(
                    {"error": "kven must be LLL-DDD (3 letters, dash, 3 digits)"}, 400
                )
                self._send(code, body, ctype)
                return
            existing = {x.get("kven") for x in (e.get("entries") or [])}
            if kven in existing:
                code, body, ctype = json_bytes({"error": "kven already in bag"}, 400)
                self._send(code, body, ctype)
                return
            t = now()
            ent = {
                "kven": kven,
                "alts": _str_list(data.get("alts")),
                "label": str(data.get("label") or "").strip(),
                "matches": _str_list(data.get("matches")),
                "notes": str(data.get("notes") or "").strip(),
                "type": str(data.get("type") or "person").strip() or "person",
                "created": t,
                "modified": t,
                "first_occurred": data.get("first_occurred") or None,
                "last_occurred": data.get("last_occurred") or None,
            }
            e.setdefault("entries", []).insert(0, ent)
            save_entries(e)
            write_kven_paper(ent, load_links().get("links") or [])
            save_entries(e)
            code, body, ctype = json_bytes({"entry": ent, "paper": str(paper_path(kven))}, 201)
            self._send(code, body, ctype)
            return

        m = re.fullmatch(r"/api/entries/([^/]+)", path)
        if m:
            kven = m.group(1).upper()
            e = load_entries()
            ent = next((x for x in (e.get("entries") or []) if x.get("kven") == kven), None)
            if not ent:
                code, body, ctype = json_bytes({"error": "not found"}, 404)
                self._send(code, body, ctype)
                return
            if data.get("delete"):
                e["entries"] = [x for x in e["entries"] if x.get("kven") != kven]
                # drop links involving this kven
                l = load_links()
                l["links"] = [
                    x
                    for x in (l.get("links") or [])
                    if x.get("from_kven") != kven and x.get("to_kven") != kven
                ]
                save_links(l)
                save_entries(e)
                for ent in e.get("entries") or []:
                    write_kven_paper(ent, l.get("links") or [])
                code, body, ctype = json_bytes({"ok": True})
                self._send(code, body, ctype)
                return
            for key in ("label", "notes", "type", "first_occurred", "last_occurred"):
                if key in data:
                    ent[key] = data.get(key)
            if "alts" in data:
                ent["alts"] = _str_list(data.get("alts"))
            if "matches" in data:
                ent["matches"] = _str_list(data.get("matches"))
            ent["modified"] = now()
            save_entries(e)
            write_kven_paper(ent, load_links().get("links") or [])
            save_entries(e)
            code, body, ctype = json_bytes({"entry": ent, "paper": str(paper_path(kven))})
            self._send(code, body, ctype)
            return

        if path == "/api/links":
            l = load_links()
            e = load_entries()
            fk = (data.get("from_kven") or "").strip().upper()
            tk = (data.get("to_kven") or "").strip().upper()
            lt = (data.get("link_type") or "relates_to").strip()
            if not valid_kven(fk) or not valid_kven(tk):
                code, body, ctype = json_bytes({"error": "from/to need LLL-DDD"}, 400)
                self._send(code, body, ctype)
                return
            if lt and lt not in (e.get("link_types") or []):
                e.setdefault("link_types", []).append(lt)
                save_entries(e)
            link = {
                "id": f"lnk_{secrets.token_hex(4)}",
                "from_kven": fk,
                "to_kven": tk,
                "link_type": lt or "relates_to",
                "note": str(data.get("note") or "").strip(),
                "created": now(),
            }
            l.setdefault("links", []).insert(0, link)
            save_links(l)
            for kven in (fk, tk):
                row = next((x for x in (e.get("entries") or []) if x.get("kven") == kven), None)
                if row:
                    write_kven_paper(row, l.get("links") or [])
            code, body, ctype = json_bytes({"link": link, "link_types": e.get("link_types")}, 201)
            self._send(code, body, ctype)
            return

        m = re.fullmatch(r"/api/links/([^/]+)", path)
        if m:
            lid = m.group(1)
            l = load_links()
            dropped = next((x for x in (l.get("links") or []) if x.get("id") == lid), None)
            l["links"] = [x for x in (l.get("links") or []) if x.get("id") != lid]
            if dropped is None:
                code, body, ctype = json_bytes({"error": "not found"}, 404)
                self._send(code, body, ctype)
                return
            save_links(l)
            e = load_entries()
            links_now = l.get("links") or []
            for kven in (
                str(dropped.get("from_kven") or "").upper(),
                str(dropped.get("to_kven") or "").upper(),
            ):
                row = next((x for x in (e.get("entries") or []) if x.get("kven") == kven), None)
                if row:
                    write_kven_paper(row, links_now)
            code, body, ctype = json_bytes({"ok": True})
            self._send(code, body, ctype)
            return

        code, body, ctype = json_bytes({"error": "not found"}, 404)
        self._send(code, body, ctype)


def _str_list(v: Any) -> list[str]:
    if isinstance(v, list):
        return [str(x).strip() for x in v if str(x).strip()]
    if isinstance(v, str):
        return [p.strip() for p in re.split(r"[,;\n]+", v) if p.strip()]
    return []


def main() -> None:
    load_entries()
    load_links()
    sync_papers()
    httpd = ThreadingHTTPServer((HOST, PORT), Handler)
    print(f"Eddy's Encoder · CO.TOY-004-EEE · http://{HOST}:{PORT}/")
    print(f"  bag: {ENTRIES}")
    print(f"  links: {LINKS}")
    print(f"  paper: {PAPER}")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nbye eddie")


if __name__ == "__main__":
    main()
