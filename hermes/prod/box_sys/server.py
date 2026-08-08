#!/usr/bin/env python3
"""Hermes · Charlie's Toys mailer · CO.TOY-005-HERMES · posts into Pocket Desktop inboxes."""

from __future__ import annotations

import json
import os
import urllib.error
import urllib.request
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from typing import Any

BOX_SYS = Path(__file__).resolve().parent
PROD = BOX_SYS.parent
HOST = "127.0.0.1"
PORT = int(os.environ.get("HERMES_PORT", "43169"))
POCKET = os.environ.get("POCKET_DESKTOP_URL", "http://127.0.0.1:43167").rstrip("/")
SKU = "CO.TOY-005-HERMES"
APP = "Hermes"


def jsend(handler: SimpleHTTPRequestHandler, code: int, obj: dict[str, Any]) -> None:
    raw = json.dumps(obj, ensure_ascii=False).encode("utf-8")
    handler.send_response(code)
    handler.send_header("Content-Type", "application/json; charset=utf-8")
    handler.send_header("Content-Length", str(len(raw)))
    handler.send_header("Access-Control-Allow-Origin", "*")
    handler.end_headers()
    handler.wfile.write(raw)


def pocket_json(method: str, path: str, body: dict[str, Any] | None = None) -> dict[str, Any]:
    url = POCKET + path
    data = None
    headers = {"Accept": "application/json"}
    if body is not None:
        data = json.dumps(body).encode("utf-8")
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=12) as resp:
            return json.loads(resp.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        try:
            err = json.loads(e.read().decode("utf-8"))
        except Exception:
            err = {"ok": False, "error": str(e)}
        err.setdefault("ok", False)
        err.setdefault("error", str(e))
        err["http"] = e.code
        return err
    except Exception as e:
        return {"ok": False, "error": f"pocket unreachable · {e} · is Pocket Desktop running on {POCKET}?"}


class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(BOX_SYS), **kwargs)

    def log_message(self, fmt: str, *args) -> None:
        pass

    def do_OPTIONS(self) -> None:
        self.send_response(204)
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")
        self.end_headers()

    def do_GET(self) -> None:
        path = self.path.split("?", 1)[0]
        if path == "/api/health":
            pocket = pocket_json("GET", "/api/health") if False else None
            # pocket may not expose /api/health — probe inboxes
            inboxes = pocket_json("GET", "/api/mail/inboxes")
            return jsend(
                self,
                200,
                {
                    "ok": True,
                    "app": APP,
                    "sku": SKU,
                    "port": PORT,
                    "pocket": POCKET,
                    "pocket_ok": bool(inboxes.get("ok")),
                    "inboxes": inboxes.get("inboxes") if inboxes.get("ok") else [],
                    "pocket_error": None if inboxes.get("ok") else inboxes.get("error"),
                },
            )
        if path == "/api/inboxes":
            r = pocket_json("GET", "/api/mail/inboxes")
            return jsend(self, 200 if r.get("ok") else 502, r)
        return super().do_GET()

    def do_POST(self) -> None:
        path = self.path.split("?", 1)[0]
        n = int(self.headers.get("Content-Length") or 0)
        raw = self.rfile.read(n) if n else b"{}"
        try:
            body = json.loads(raw.decode("utf-8") or "{}")
        except json.JSONDecodeError:
            return jsend(self, 400, {"ok": False, "error": "bad json"})

        if path == "/api/send":
            address = str(body.get("address") or body.get("to") or "").strip()
            title = str(body.get("title") or body.get("subject") or "letter").strip()
            payload = body.get("body") or body.get("payload") or ""
            if not isinstance(payload, str):
                payload = str(payload)
            from_auth = str(body.get("from") or body.get("auth") or "Hermes").strip()
            if not address:
                return jsend(self, 400, {"ok": False, "error": "address required"})
            r = pocket_json(
                "POST",
                "/api/mail/send",
                {
                    "address": address,
                    "title": title or "letter",
                    "body": payload,
                    "from": from_auth or "Hermes",
                },
            )
            code = 200 if r.get("ok") else (r.get("http") or 502)
            return jsend(self, int(code), r)

        return jsend(self, 404, {"ok": False, "error": "no route"})


def main() -> None:
    httpd = ThreadingHTTPServer((HOST, PORT), Handler)
    # ASCII-only: Windows launcher log is often cp1252
    print(f"{APP} | {SKU} | http://{HOST}:{PORT}/ | pocket {POCKET}")
    httpd.serve_forever()


if __name__ == "__main__":
    main()
