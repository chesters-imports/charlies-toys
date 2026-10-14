#!/usr/bin/env python3
"""Memory Deck Builder — CO.KDE-003-MDB. Markdown cards, a board, no birth room."""

from __future__ import annotations

import json
import os
import re
import secrets
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlparse

SYS = Path(__file__).resolve().parent
PROD = SYS.parent
TOY = PROD.parent
SAFE = PROD / "safe_box"
DEFAULT_LIBRARY = TOY / "cards"
BOARD_FILE = SAFE / "board.json"
COLORS_FILE = SAFE / "colors.json"
LIBRARY_FILE = SAFE / "library.json"
ALICE = TOY.parents[1]
TRAYS = (
    ALICE
    / "my-pocket-things"
    / "pocket-go"
    / "castaways"
    / "~hosts"
    / "trays"
)
PORT = int(os.environ.get("MDB_PORT", "43173"))
LOCK = threading.Lock()

CRATE_RE = re.compile(r"^crate\.[0-9A-F]{16}$")
SKIP_NAMES = {"_shell.md", "index.md", "readme.md"}


def alice_ok(path: Path) -> bool:
    try:
        path.resolve().relative_to(ALICE.resolve())
    except ValueError:
        return False
    return True


def read_json(path: Path, fallback):
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return fallback


def library_path() -> Path:
    env = os.environ.get("MDB_LIBRARY", "").strip()
    if env:
        p = Path(env)
    else:
        cfg = read_json(LIBRARY_FILE, {})
        raw = str(cfg.get("library") or "").strip()
        p = Path(raw) if raw else DEFAULT_LIBRARY
    p.mkdir(parents=True, exist_ok=True)
    return p


def set_library(raw: str) -> Path:
    p = Path(raw.strip())
    if not p.is_dir():
        raise ValueError("folder is not there")
    if not alice_ok(p):
        raise ValueError("folder has to live inside ALICE_BOX")
    SAFE.mkdir(parents=True, exist_ok=True)
    LIBRARY_FILE.write_text(
        json.dumps({"library": str(p.resolve())}, indent=2) + "\n", encoding="utf-8"
    )
    return p.resolve()


def board() -> dict:
    data = read_json(BOARD_FILE, {})
    if not isinstance(data, dict):
        data = {}
    data.setdefault("pos", {})
    data.setdefault("pile", {})
    return data


def save_board(data: dict) -> None:
    SAFE.mkdir(parents=True, exist_ok=True)
    BOARD_FILE.write_text(json.dumps(data, indent=2) + "\n", encoding="utf-8")


def split_fm(text: str) -> tuple[str, str]:
    if not text.startswith("---"):
        return "", text
    end = text.find("\n---", 3)
    if end < 0:
        return "", text
    fm = text[3:end].strip("\n")
    rest = text[end + 4 :]
    if rest.startswith("\n"):
        rest = rest[1:]
    return fm, rest


def parse_fm(fm: str) -> dict[str, str]:
    out: dict[str, str] = {}
    for row in fm.splitlines():
        if not row.strip() or row.lstrip().startswith("#") or ":" not in row:
            continue
        key, val = row.split(":", 1)
        out[key.strip()] = val.strip()
    return out


def yaml_scalar(value: str) -> str:
    if value == "" or any(c in value for c in ':{}[]#&*!|>%@`"\n'):
        return '"' + value.replace("\\", "\\\\").replace('"', '\\"') + '"'
    return value


def parse_crate_list(raw: str) -> list[str]:
    inner = raw.strip()
    if inner.startswith("[") and inner.endswith("]"):
        inner = inner[1:-1]
    found = re.findall(r"crate\.[0-9A-Fa-f]{16}", inner)
    out: list[str] = []
    for crate in found:
        norm = "crate." + crate.split(".", 1)[1].upper()
        if norm not in out:
            out.append(norm)
    return out


def fmt_list(crates: list[str]) -> str:
    return "[" + ", ".join(crates) + "]"


def set_fm_key(fm: str, key: str, value: str | None) -> str:
    lines = fm.splitlines()
    next_lines: list[str] = []
    seen = False
    for row in lines:
        if row.startswith(key + ":"):
            seen = True
            if value is not None:
                next_lines.append(key + ": " + value)
            continue
        next_lines.append(row)
    if value is not None and not seen:
        next_lines.append(key + ": " + value)
    return "\n".join(next_lines).strip("\n")


def drop_fm_keys(fm: str, keys: set[str]) -> str:
    kept = []
    for row in fm.splitlines():
        name = row.split(":", 1)[0].strip() if ":" in row else ""
        if name in keys:
            continue
        kept.append(row)
    return "\n".join(kept).strip("\n")


def join_fm(fm: str, body: str) -> str:
    body = body.lstrip("\n")
    return f"---\n{fm}\n---\n\n{body}"


def unquote(value: str) -> str:
    value = value.strip()
    if len(value) >= 2 and value[0] == value[-1] and value[0] in {'"', "'"}:
        inner = value[1:-1]
        return inner.replace('\\"', '"').replace("\\\\", "\\")
    return value


def is_card(path: Path, meta: dict[str, str]) -> bool:
    if path.name.lower() in SKIP_NAMES:
        return False
    if meta.get("environment", "").strip() == "lorecard":
        return True
    return "-LORE_" in path.name and "crate" in meta


def card_body(title: str, klass: str, house: str, line: str, crate: str) -> str:
    return (
        "{{div:lorecard}}\n\n"
        "{{.kicker}}" + klass + "\n\n"
        "{{.house}}" + house + "\n\n"
        "# " + title + "\n\n"
        "{{.line}}" + line + "\n\n"
        "{{.id}}" + crate + "\n\n"
        "{{/div}}\n"
    )


def slug(title: str) -> str:
    words = re.findall(r"[A-Za-z0-9]+", title)[:8] or ["Card"]
    return "_".join(words)


def next_lore_name(folder: Path, title: str) -> str:
    nums = []
    for path in folder.rglob("*.md"):
        match = re.search(r"-LORE_(\d+)", path.name)
        if match:
            nums.append(int(match.group(1)))
    n = (max(nums) if nums else 0) + 1
    return f"{slug(title)}-LORE_{n}.md"


def load_card(path: Path, lib: Path, layout: dict) -> dict | None:
    try:
        text = path.read_text(encoding="utf-8")
    except OSError:
        return None
    fm, _body = split_fm(text)
    meta = parse_fm(fm)
    if not is_card(path, meta):
        return None
    crate = meta.get("crate", "")
    if not CRATE_RE.match(crate):
        crate = ""
    pos = layout.get("pos", {}).get(crate) or {}
    return {
        "crate": crate,
        "title": unquote(meta.get("title", path.stem)),
        "class": unquote(meta.get("class", "")),
        "line": unquote(meta.get("line", "")),
        "house": unquote(meta.get("house", "")),
        "strip": unquote(meta.get("strip", "")),
        "maker": unquote(meta.get("maker", "")),
        "from": unquote(meta.get("from", "")),
        "source_crate": meta.get("source_crate", "").strip(),
        "edges": parse_crate_list(meta.get("edges", "")),
        "found": parse_crate_list(meta.get("found", "")),
        "file": str(path.relative_to(lib)).replace("\\", "/"),
        "x": pos.get("x", None),
        "y": pos.get("y", None),
        "pile": layout.get("pile", {}).get(crate, ""),
    }


def list_cards(lib: Path) -> list[dict]:
    layout = board()
    cards = []
    for path in sorted(lib.rglob("*.md")):
        if any(part.startswith(".") for part in path.relative_to(lib).parts):
            continue
        card = load_card(path, lib, layout)
        if card and card["crate"]:
            cards.append(card)
    return cards


def find_card_file(lib: Path, crate: str) -> Path | None:
    for card in list_cards(lib):
        if card["crate"] == crate:
            return lib / card["file"]
    return None


def write_card_fields(path: Path, updates: dict, clear_birth: bool) -> None:
    text = path.read_text(encoding="utf-8")
    fm, body = split_fm(text)
    if clear_birth:
        fm = drop_fm_keys(fm, {"from", "source_crate", "from_href"})
    for key in ("title", "class", "line", "house", "maker", "strip"):
        if key in updates and updates[key] is not None:
            fm = set_fm_key(fm, key, yaml_scalar(str(updates[key])))
    for key in ("edges", "found"):
        if key in updates and updates[key] is not None:
            fm = set_fm_key(fm, key, fmt_list(list(updates[key])))
    path.write_text(join_fm(fm, body), encoding="utf-8")


def tray_cards() -> list[dict]:
    if not TRAYS.is_dir():
        return []
    return list_cards(TRAYS)


def import_cards(lib: Path, crates: list[str]) -> dict:
    if lib.resolve() == TRAYS.resolve():
        raise ValueError("this library is already the trays")
    wanted = [norm_crate(c) for c in crates]
    have = {c["crate"] for c in list_cards(lib)}
    by_crate = {c["crate"]: c for c in tray_cards()}
    imported = []
    skipped = []
    for crate in wanted:
        if crate in have:
            skipped.append(crate)
            continue
        src_card = by_crate.get(crate)
        if src_card is None:
            raise ValueError("not in the trays: " + crate)
        src = TRAYS / src_card["file"]
        dest = lib / src.name
        n = 2
        while dest.exists():
            dest = lib / f"{src.stem}-{n}{src.suffix}"
            n += 1
        dest.write_bytes(src.read_bytes())
        imported.append(crate)
        have.add(crate)
    return {"imported": imported, "skipped": skipped}


def mint_crate(lib: Path) -> str:
    have = {c["crate"] for c in list_cards(lib)}
    while True:
        crate = "crate." + secrets.token_hex(8).upper()
        if crate not in have:
            return crate


def norm_strip(value: str) -> str:
    match = re.fullmatch(r"#([0-9A-Fa-f]{6})", value.strip())
    if not match:
        raise ValueError("color needs a #hex")
    return "#" + match.group(1).lower()


def remember_color(value: str) -> None:
    color = norm_strip(value)
    data = read_json(COLORS_FILE, {"colors": []})
    colors = [c for c in data.get("colors") or [] if isinstance(c, str)]
    if color not in colors:
        colors.append(color)
    SAFE.mkdir(parents=True, exist_ok=True)
    COLORS_FILE.write_text(json.dumps({"colors": colors}, indent=2) + "\n", encoding="utf-8")


def known_colors(cards: list[dict]) -> list[str]:
    stored = read_json(COLORS_FILE, {"colors": []}).get("colors") or []
    out: list[str] = []
    for raw in list(stored) + [c.get("strip") or "" for c in cards]:
        try:
            color = norm_strip(str(raw))
        except ValueError:
            continue
        if color not in out:
            out.append(color)
    return out


def create_card(lib: Path, title: str, klass: str, line: str, house: str, strip: str) -> dict:
    title = title.strip()
    house = house.strip()
    if not title:
        raise ValueError("need a title")
    if not house:
        raise ValueError("need a house")
    if len(line) > 255:
        raise ValueError("line is longer than 255")
    color = norm_strip(strip) if strip.strip() else ""
    crate = mint_crate(lib)
    name = next_lore_name(lib, title)
    path = lib / name
    rows = [
        "crate: " + crate,
        "title: " + yaml_scalar(title),
        "class: " + yaml_scalar(klass.strip()),
        "line: " + yaml_scalar(line.strip()),
        "house: " + yaml_scalar(house),
    ]
    if color:
        rows.append("strip: " + yaml_scalar(color))
        remember_color(color)
    rows += ["edges: []", "found: []", "environment: lorecard"]
    fm = "\n".join(rows)
    path.write_text(
        join_fm(fm, card_body(title, klass.strip(), house.strip(), line.strip(), crate)),
        encoding="utf-8",
    )
    layout = board()
    layout["pos"][crate] = {"x": 48 + (len(layout["pos"]) % 6) * 28, "y": 48}
    save_board(layout)
    return load_card(path, lib, layout)


def norm_crate(value: str) -> str:
    match = re.fullmatch(r"crate\.([0-9A-Fa-f]{16})", value.strip(), re.I)
    if not match:
        raise ValueError("crate looks wrong")
    return "crate." + match.group(1).upper()


class Handler(BaseHTTPRequestHandler):
    def log_message(self, fmt: str, *args) -> None:
        return

    def send_json(self, code: int, payload: dict) -> None:
        raw = json.dumps(payload).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(raw)))
        self.end_headers()
        self.wfile.write(raw)

    def read_body(self) -> dict:
        n = int(self.headers.get("Content-Length") or 0)
        if n <= 0:
            return {}
        try:
            return json.loads(self.rfile.read(n).decode("utf-8"))
        except json.JSONDecodeError:
            return {}

    def do_GET(self) -> None:
        url = urlparse(self.path)
        if url.path == "/api/health":
            self.send_json(200, {"ok": True, "sku": "CO.KDE-003-MDB"})
            return
        if url.path == "/api/trays":
            cards = [
                {"crate": c["crate"], "title": c["title"], "class": c["class"], "house": c["house"]}
                for c in tray_cards()
            ]
            self.send_json(200, {"count": len(cards), "cards": cards})
            return
        if url.path == "/api/state":
            lib = library_path()
            cards = list_cards(lib)
            piles = sorted({c["pile"] for c in cards if c["pile"]})
            self.send_json(
                200,
                {
                    "library": str(lib),
                    "cards": cards,
                    "piles": piles,
                    "colors": known_colors(cards),
                },
            )
            return
        if url.path in {"/", "/index.html"}:
            self.file(SYS / "index.html", "text/html; charset=utf-8")
            return
        if url.path == "/app.js":
            self.file(SYS / "app.js", "text/javascript; charset=utf-8")
            return
        if url.path == "/app.css":
            self.file(SYS / "app.css", "text/css; charset=utf-8")
            return
        self.send_error(404)

    def file(self, path: Path, ctype: str) -> None:
        if not path.is_file():
            self.send_error(404)
            return
        raw = path.read_bytes()
        self.send_response(200)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(raw)))
        self.end_headers()
        self.wfile.write(raw)

    def do_POST(self) -> None:
        url = urlparse(self.path)
        body = self.read_body()
        try:
            if url.path == "/api/library":
                path = set_library(str(body.get("library") or ""))
                self.send_json(200, {"library": str(path)})
                return
            if url.path == "/api/import":
                with LOCK:
                    result = import_cards(library_path(), list(body.get("crates") or []))
                self.send_json(200, result)
                return
            if url.path == "/api/cards":
                with LOCK:
                    card = create_card(
                        library_path(),
                        str(body.get("title") or ""),
                        str(body.get("class") or ""),
                        str(body.get("line") or ""),
                        str(body.get("house") or ""),
                        str(body.get("strip") or ""),
                    )
                self.send_json(200, {"card": card})
                return
            if url.path == "/api/edge":
                self.edge(body, add=True)
                return
            self.send_error(404)
        except ValueError as exc:
            self.send_json(400, {"error": str(exc)})

    def do_PATCH(self) -> None:
        url = urlparse(self.path)
        m = re.fullmatch(r"/api/cards/(crate\.[0-9A-Fa-f]{16})", url.path)
        if not m:
            self.send_error(404)
            return
        crate = norm_crate(m.group(1))
        body = self.read_body()
        try:
            with LOCK:
                lib = library_path()
                path = find_card_file(lib, crate)
                if path is None:
                    self.send_json(404, {"error": "no such card"})
                    return
                updates: dict = {}
                for key in ("title", "class", "line", "house", "maker", "strip"):
                    if key in body:
                        updates[key] = str(body.get(key) or "")
                if "line" in updates and len(updates["line"]) > 255:
                    raise ValueError("line is longer than 255")
                if "house" in updates and not str(updates["house"]).strip():
                    raise ValueError("need a house")
                if "strip" in updates:
                    updates["strip"] = norm_strip(str(updates["strip"]))
                    remember_color(updates["strip"])
                if "found" in body:
                    updates["found"] = [norm_crate(c) for c in body.get("found") or []]
                if "edges" in body:
                    updates["edges"] = [norm_crate(c) for c in body.get("edges") or []]
                if updates or body.get("clear_birth"):
                    write_card_fields(path, updates, bool(body.get("clear_birth")))
                layout = board()
                if "x" in body or "y" in body:
                    pos = layout["pos"].get(crate) or {"x": 40, "y": 40}
                    if "x" in body:
                        pos["x"] = float(body["x"])
                    if "y" in body:
                        pos["y"] = float(body["y"])
                    layout["pos"][crate] = pos
                if "pile" in body:
                    pile = str(body.get("pile") or "").strip()
                    if pile:
                        layout["pile"][crate] = pile
                    else:
                        layout["pile"].pop(crate, None)
                save_board(layout)
                card = load_card(path, lib, layout)
            self.send_json(200, {"card": card})
        except ValueError as exc:
            self.send_json(400, {"error": str(exc)})

    def do_DELETE(self) -> None:
        url = urlparse(self.path)
        try:
            if url.path == "/api/edge":
                self.edge(self.read_body(), add=False)
                return
            self.send_error(404)
        except ValueError as exc:
            self.send_json(400, {"error": str(exc)})

    def edge(self, body: dict, add: bool) -> None:
        a = norm_crate(str(body.get("a") or ""))
        b = norm_crate(str(body.get("b") or ""))
        if a == b:
            raise ValueError("a card does not edge itself")
        with LOCK:
            lib = library_path()
            cards = {c["crate"]: c for c in list_cards(lib)}
            if a not in cards or b not in cards:
                self.send_json(404, {"error": "both ends have to be cards"})
                return
            for src, dst in ((a, b), (b, a)):
                edges = list(cards[src]["edges"])
                if add and dst not in edges:
                    edges.append(dst)
                if not add:
                    edges = [e for e in edges if e != dst]
                write_card_fields(lib / cards[src]["file"], {"edges": edges}, False)
                cards[src]["edges"] = edges
        self.send_json(200, {"ok": True})


def main() -> None:
    SAFE.mkdir(parents=True, exist_ok=True)
    DEFAULT_LIBRARY.mkdir(parents=True, exist_ok=True)
    httpd = ThreadingHTTPServer(("127.0.0.1", PORT), Handler)
    print(f"Memory Deck Builder · CO.KDE-003-MDB · http://127.0.0.1:{PORT}/")
    httpd.serve_forever()


if __name__ == "__main__":
    main()
