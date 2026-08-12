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
POCKET = os.environ.get(
    "POCKET_DESKTOP_URL",
    os.environ.get("SOPHIA_DESK_URL", "http://127.0.0.1:43167"),
).rstrip("/")
# TERMINALS (sdk-import-station) station wire
TERMINALS = os.environ.get("TERMINALS_URL", "http://127.0.0.1:43101").rstrip("/")
TERMINALS_TOKEN = os.environ.get("SDK_MAIL_INJECT_TOKEN", "wire-whisper")
SKU = "CO.TOY-005-HERMES"
APP = "Hermes"

# girl network · address global · each desk process is a home for one address
GIRL_DESKS: dict[str, str] = {
    "the.archivist": os.environ.get("SOPHIA_DESK_URL", "http://127.0.0.1:43167").rstrip(
        "/"
    ),
    "the.detective": os.environ.get(
        "CASSANDRA_DESK_URL", "http://127.0.0.1:43168"
    ).rstrip("/"),
    "the.lover": os.environ.get("AVA_DESK_URL", "http://127.0.0.1:43171").rstrip("/"),
}


def norm_addr(address: str) -> str:
    return (address or "").strip().lower().replace(" ", "").replace("-", ".")


def desk_for_address(address: str) -> str | None:
    return GIRL_DESKS.get(norm_addr(address))

# To field → station id (TERMINALS mail inject)
_STATION_ALIASES: dict[str, str] = {
    "io": "io",
    "iox": "io",
    "ab": "ab",
    "abx": "ab",
    "cu": "cu",
    "cux": "cu",
    "icu": "cu",
    "dr": "dr",
    "drx": "dr",
    "dc": "dc",
    "dco": "dc",
    "osx": "osx",
    "rx": "osx",
    "oriel": "osx",
    "oriole": "osx",
    "olb": "osx",
}


def jsend(handler: SimpleHTTPRequestHandler, code: int, obj: dict[str, Any]) -> None:
    raw = json.dumps(obj, ensure_ascii=False).encode("utf-8")
    handler.send_response(code)
    handler.send_header("Content-Type", "application/json; charset=utf-8")
    handler.send_header("Content-Length", str(len(raw)))
    handler.send_header("Access-Control-Allow-Origin", "*")
    handler.end_headers()
    handler.wfile.write(raw)


def http_json(
    base: str,
    method: str,
    path: str,
    body: dict[str, Any] | None = None,
    *,
    timeout: float = 12,
    unreachable: str = "unreachable",
) -> dict[str, Any]:
    url = base.rstrip("/") + path
    data = None
    headers = {"Accept": "application/json"}
    if body is not None:
        data = json.dumps(body).encode("utf-8")
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
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
        return {"ok": False, "error": f"{unreachable} · {e} · {url}"}


def pocket_json(method: str, path: str, body: dict[str, Any] | None = None) -> dict[str, Any]:
    return http_json(
        POCKET,
        method,
        path,
        body,
        unreachable=f"SophiaDesk offline · start desk on {POCKET}",
    )


def resolve_station(address: str) -> str | None:
    """If To is a TERMINALS station (io/drx/osx…), return station id."""
    key = (address or "").strip().lower()
    if not key:
        return None
    # strip chip decorations
    key = key.replace("terminal ", "").replace("station ", "").strip()
    if key in _STATION_ALIASES:
        return _STATION_ALIASES[key]
    # chip codes only
    for alias, sid in _STATION_ALIASES.items():
        if key == alias:
            return sid
    return None


def terminals_inject(
    to_station: str,
    *,
    subject: str,
    body: str,
    from_label: str,
) -> dict[str, Any]:
    return http_json(
        TERMINALS,
        "POST",
        "/api/mail/inject",
        {
            "token": TERMINALS_TOKEN,
            "to": to_station,
            "from": from_label or "Hermes",
            "from_label": from_label or "Hermes",
            "from_key": "hermes",
            "subject": subject or "letter",
            "body": body or "",
        },
        unreachable=f"TERMINALS offline · start sdk-import on {TERMINALS}",
    )


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
            # pocket may not expose /api/health — probe inboxes
            inboxes = pocket_json("GET", "/api/mail/inboxes")
            term = http_json(
                TERMINALS,
                "GET",
                "/api/health",
                unreachable=f"TERMINALS offline · {TERMINALS}",
            )
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
                    "terminals": TERMINALS,
                    "terminals_ok": bool(term.get("ok")),
                    "stations": sorted(set(_STATION_ALIASES.values())),
                },
            )
        if path == "/api/inboxes":
            r = pocket_json("GET", "/api/mail/inboxes")
            stations = [
                {"id": sid, "address": sid, "label": f"TERMINALS · {sid.upper()}"}
                for sid in sorted(set(_STATION_ALIASES.values()))
            ]
            # desk may be offline; still list stations for TERMINALS routing
            if not r.get("ok"):
                r = {
                    "ok": True,
                    "inboxes": [],
                    "desk_ok": False,
                    "desk_error": r.get("error"),
                }
            else:
                r["desk_ok"] = True
            r["stations"] = stations
            return jsend(self, 200, r)

        # desk shipping catalog (leaf · card · envelope) — parcels not for TERMINALS
        if path == "/api/catalog" or path == "/api/parcels":
            r = pocket_json("GET", "/api/mail/catalog")
            # some desks only implement POST — try POST empty
            if not r.get("ok") and "error" in r:
                r = pocket_json("POST", "/api/mail/catalog", {})
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

        if path == "/api/catalog" or path == "/api/parcels":
            r = pocket_json("POST", "/api/mail/catalog", body if body else {})
            if not r.get("ok"):
                r = pocket_json("GET", "/api/mail/catalog")
            return jsend(self, 200 if r.get("ok") else 502, r)

        if path == "/api/send":
            address = str(body.get("address") or body.get("to") or "").strip()
            title = str(body.get("title") or body.get("subject") or "letter").strip()
            payload = body.get("body") or body.get("payload") or ""
            if not isinstance(payload, str):
                payload = str(payload)
            from_auth = str(body.get("from") or body.get("auth") or "Hermes").strip()
            # parcel: ship existing leaf / card / deck envelope to a DESK inbox
            item_id = str(
                body.get("item_id")
                or body.get("parcel")
                or body.get("leaf_id")
                or body.get("deck_id")
                or body.get("card_id")
                or ""
            ).strip()
            if not address:
                return jsend(self, 400, {"ok": False, "error": "address required"})

            # Parcels only go to desk inboxes — not TERMINALS station wire
            if item_id:
                station = resolve_station(address)
                if station:
                    return jsend(
                        self,
                        400,
                        {
                            "ok": False,
                            "error": "parcels are desk-only · cannot ship leaf/card/envelope to TERMINALS (text wire only)",
                            "via": "terminals",
                        },
                    )
                # ship from the desk that holds the item: try girl homes + default pocket
                targets: list[str] = []
                home = desk_for_address(address)
                for u in (POCKET, *GIRL_DESKS.values()):
                    if u and u not in targets:
                        targets.append(u)
                # prefer posting ship on each until one owns item_id
                last: dict[str, Any] = {"ok": False, "error": "no desk had item"}
                for base in targets:
                    r = http_json(
                        base,
                        "POST",
                        "/api/mail/ship",
                        {
                            "item_id": item_id,
                            "address": address,
                            "from": from_auth or "Hermes",
                        },
                        unreachable=f"desk offline · {base}",
                    )
                    last = r
                    if r.get("ok"):
                        r["via"] = "girl-network" if home else "desk-parcel"
                        r["mode"] = "parcel"
                        r["hermes_from"] = base
                        return jsend(self, 200, r)
                code = 200 if last.get("ok") else (last.get("http") or 502)
                return jsend(self, int(code), last)

            # Route A: TERMINALS station wire (text letter only)
            station = resolve_station(address)
            if station:
                r = terminals_inject(
                    station,
                    subject=title or "letter",
                    body=payload,
                    from_label=from_auth or "Hermes",
                )
                if r.get("ok"):
                    mail = r.get("mail") if isinstance(r.get("mail"), dict) else {}
                    return jsend(
                        self,
                        200,
                        {
                            "ok": True,
                            "via": "terminals",
                            "mode": "letter",
                            "station": station,
                            "mail_id": mail.get("id"),
                            "address": station,
                            "title": title or "letter",
                            "from": from_auth or "Hermes",
                        },
                    )
                code = int(r.get("http") or 502)
                r.setdefault("via", "terminals")
                return jsend(self, code, r)

            # Route B: desk letter — mint on the home desk for that address
            dest = desk_for_address(address) or POCKET
            r = http_json(
                dest,
                "POST",
                "/api/mail/send",
                {
                    "address": address,
                    "title": title or "letter",
                    "body": payload,
                    "from": from_auth or "Hermes",
                },
                unreachable=f"desk offline · {dest}",
            )
            if r.get("ok"):
                r["via"] = "girl-network" if desk_for_address(address) else "desk"
                r["mode"] = "letter"
                r["desk"] = dest
            code = 200 if r.get("ok") else (r.get("http") or 502)
            return jsend(self, int(code), r)

        return jsend(self, 404, {"ok": False, "error": "no route"})


def main() -> None:
    httpd = ThreadingHTTPServer((HOST, PORT), Handler)
    # ASCII-only: Windows launcher log is often cp1252
    print(
        f"{APP} | {SKU} | http://{HOST}:{PORT}/ | desk {POCKET} | terminals {TERMINALS}"
    )
    httpd.serve_forever()


if __name__ == "__main__":
    main()
