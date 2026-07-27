#!/usr/bin/env python3
"""
ineffable-box — thin always-on reporter for omens / hymns / secrets.
Not a terminal. Not invent-ory. Ship at the moment of notice.
"""

from __future__ import annotations

import json
import re
import secrets
import sys
import time
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any
from urllib.parse import parse_qs, urlparse

BOX_SYS = Path(__file__).resolve().parent
PROD = BOX_SYS.parent
PAPER = PROD / "paper"
HOST = "127.0.0.1"
PORT = 43120

# service id → disk folder + face meta (UI reads /api/services too)
SERVICES: dict[str, dict[str, str]] = {
    "omens": {
        "id": "omens",
        "chip": "OMAN",
        "title": "Oman’s Omens",
        "whom": "Oman O’mente",
        "hint": "signs · animals · patterns · attention yanked",
        "folder": "omens",
    },
    "hymns": {
        "id": "hymns",
        "chip": "JASMINE",
        "title": "Song of Songs",
        "whom": "Princess Jasmine",
        "hint": "hymn · song · live voice · mythic charge",
        "folder": "hymns",
    },
    "secrets": {
        "id": "secrets",
        "chip": "TEEHEE",
        "title": "Lil’ Secrets",
        "whom": "Lil’ Secret Keeper",
        "hint": "known · unproven · whispered",
        "folder": "secrets",
    },
}


def ensure_paper() -> None:
    for s in SERVICES.values():
        d = PAPER / s["folder"]
        d.mkdir(parents=True, exist_ok=True)


# Short house prefixes so a chip on a later card still says what pipe it came from
SERVICE_PREFIX = {
    "omens": "OMN",
    "hymns": "HYM",
    "secrets": "SCR",
}


def _slug_words(text: str, max_words: int = 4, max_len: int = 36) -> str:
    """Readable slug from notice body — not opaque hex."""
    words = re.findall(r"[a-zA-Z0-9]+", (text or "").lower())
    if not words:
        return "note"
    # drop ultra-common glue so chips stay meaningful
    skip = {
        "a",
        "an",
        "the",
        "and",
        "or",
        "of",
        "to",
        "in",
        "on",
        "at",
        "is",
        "it",
        "my",
        "i",
    }
    core = [w for w in words if w not in skip] or words
    slug = "-".join(core[:max_words])
    if len(slug) > max_len:
        slug = slug[:max_len].rstrip("-")
    return slug or "note"


def _type_a_initials(text: str, max_letters: int = 6) -> str:
    """DATBOX-ish TYPE A: first letter of each word."""
    parts: list[str] = []
    for word in re.findall(r"[a-zA-Z0-9]+", text or ""):
        if not word:
            continue
        ch = word[0]
        parts.append(ch.upper() if ch.isalpha() else ch)
        if len(parts) >= max_letters:
            break
    return "".join(parts) or "X"


def mint_chip(service: str, body: str, event_unix: int) -> str:
    """
    Narrative chip code for later bag/card attach.
    e.g. OMN-yellow-swallowtail-YSW-260726-a3f2
         HYM-that-song-again-TSA-260726-91c0
    """
    pref = SERVICE_PREFIX.get(service, "INF")
    slug = _slug_words(body)
    initials = _type_a_initials(body)
    day = time.strftime("%y%m%d", time.localtime(event_unix))
    salt = secrets.token_hex(2)
    # file-safe
    chip = f"{pref}-{slug}-{initials}-{day}-{salt}"
    chip = re.sub(r"[^\w.\-]+", "-", chip)
    chip = re.sub(r"-{2,}", "-", chip).strip("-")
    return chip


def list_drops(service: str, limit: int = 40) -> list[dict[str, Any]]:
    meta = SERVICES.get(service)
    if not meta:
        return []
    d = PAPER / meta["folder"]
    if not d.is_dir():
        return []
    rows: list[dict[str, Any]] = []
    for f in d.glob("*.ineff.json"):
        try:
            data = json.loads(f.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            continue
        if not isinstance(data, dict):
            continue
        data["_file"] = f.name
        rows.append(data)
    rows.sort(key=lambda r: int(r.get("event_unix") or r.get("saved_at") or 0), reverse=True)
    return rows[:limit]


def save_drop(
    service: str,
    body: str,
    tags_raw: str = "",
    event_unix: int | None = None,
) -> dict[str, Any]:
    meta = SERVICES.get(service)
    if not meta:
        raise ValueError("unknown service")
    body = (body or "").strip()
    if not body:
        raise ValueError("body required")
    tags_raw = (tags_raw or "").strip()
    now = int(time.time())
    ev = int(event_unix) if event_unix and int(event_unix) > 0 else now
    chip = mint_chip(service, body, ev)
    # collision-proof on disk
    base = chip
    n = 0
    path = PAPER / meta["folder"] / f"{chip}.ineff.json"
    while path.is_file():
        n += 1
        chip = f"{base}-{n}"
        path = PAPER / meta["folder"] / f"{chip}.ineff.json"
    doc = {
        "id": chip,
        "chip": chip,
        "chip_face": meta["chip"],
        "service": service,
        "service_prefix": SERVICE_PREFIX.get(service, "INF"),
        "whom": meta["whom"],
        "body": body,
        "tags_raw": tags_raw,
        "event_unix": ev,
        "saved_at": now,
        "tool": "ineffable-box",
        "source": "ineffable-box",
        "version": 2,
    }
    path.write_text(
        json.dumps(doc, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )
    return doc


class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args: Any, **kwargs: Any) -> None:
        super().__init__(*args, directory=str(BOX_SYS), **kwargs)

    def log_message(self, fmt: str, *args: Any) -> None:
        sys.stderr.write("%s - %s\n" % (self.address_string(), fmt % args))

    def _send(self, code: int, body: Any, content_type: str = "application/json") -> None:
        if content_type == "application/json":
            raw = json.dumps(body, ensure_ascii=False).encode("utf-8")
        else:
            raw = body if isinstance(body, bytes) else str(body).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", f"{content_type}; charset=utf-8")
        self.send_header("Content-Length", str(len(raw)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(raw)

    def _read_json(self) -> Any:
        n = int(self.headers.get("Content-Length") or 0)
        if n <= 0:
            return {}
        return json.loads(self.rfile.read(n).decode("utf-8"))

    def do_GET(self) -> None:  # noqa: N802
        parsed = urlparse(self.path)
        path = parsed.path
        qs = parse_qs(parsed.query)

        if path in ("/api/health", "/health"):
            return self._send(
                200,
                {
                    "ok": True,
                    "app": "ineffable-box",
                    "port": PORT,
                    "paper": str(PAPER),
                    "services": list(SERVICES.keys()),
                },
            )

        if path == "/api/services":
            return self._send(200, {"ok": True, "services": list(SERVICES.values())})

        if path == "/api/drops":
            svc = (qs.get("service") or ["omens"])[0].strip().lower()
            if svc not in SERVICES:
                return self._send(400, {"error": "unknown service"})
            return self._send(200, {"ok": True, "service": svc, "drops": list_drops(svc)})

        return super().do_GET()

    def do_POST(self) -> None:  # noqa: N802
        parsed = urlparse(self.path)
        path = parsed.path
        try:
            body = self._read_json()
        except json.JSONDecodeError:
            return self._send(400, {"error": "bad json"})

        if path == "/api/drop":
            svc = str(body.get("service") or "omens").strip().lower()
            text = str(body.get("body") or "")
            tags = str(body.get("tags_raw") or body.get("tags") or "")
            ev = body.get("event_unix")
            try:
                ev_i = int(ev) if ev is not None and str(ev).strip() != "" else None
            except (TypeError, ValueError):
                ev_i = None
            try:
                doc = save_drop(svc, text, tags_raw=tags, event_unix=ev_i)
            except ValueError as e:
                return self._send(400, {"error": str(e)})
            return self._send(200, {"ok": True, "drop": doc})

        return self._send(404, {"error": "not found"})


def main() -> None:
    ensure_paper()
    httpd = ThreadingHTTPServer((HOST, PORT), Handler)
    print(f"ineffable-box · http://{HOST}:{PORT}/", flush=True)
    print(f"paper         {PAPER}", flush=True)
    print(f"services      {', '.join(SERVICES)}", flush=True)
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nbye", flush=True)


if __name__ == "__main__":
    main()
