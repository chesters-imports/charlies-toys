#!/usr/bin/env python3
"""KDE Notes & Chords · static toy + health for Deck Host."""

from __future__ import annotations

import json
import sys
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import urlparse

ROOT = Path(__file__).resolve().parent
HOST = "127.0.0.1"
PORT = 43140


class Handler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(ROOT), **kwargs)

    def log_message(self, fmt, *args):
        sys.stderr.write("%s - %s\n" % (self.address_string(), fmt % args))

    def do_GET(self):
        path = urlparse(self.path).path
        if path == "/api/health":
            body = json.dumps(
                {"ok": True, "sku": "CO.KDE-001-INSTR", "service": "kde-notes-chords"}
            ).encode("utf-8")
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self.send_header("Content-Length", str(len(body)))
            self.end_headers()
            self.wfile.write(body)
            return
        if path in ("/", ""):
            self.path = "/index.html"
        return SimpleHTTPRequestHandler.do_GET(self)


def main():
    httpd = ThreadingHTTPServer((HOST, PORT), Handler)
    print(f"KDE Notes & Chords  http://{HOST}:{PORT}/")
    try:
        httpd.serve_forever()
    except KeyboardInterrupt:
        print("\nreels stopped")


if __name__ == "__main__":
    main()
