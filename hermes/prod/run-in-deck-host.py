#!/usr/bin/env python3
"""Hermes mailer → The Deck Host"""

from __future__ import annotations

import os
import subprocess
import sys
from pathlib import Path

PROD = Path(__file__).resolve().parent
BOX_SYS = PROD / "box_sys"
DECK_HOST_PY = PROD.parents[2] / "the-deck-host" / "shell" / "deck_host.py"

PORT = os.environ.get("HERMES_PORT", "43169")
URL = f"http://127.0.0.1:{PORT}/"
HEALTH = f"http://127.0.0.1:{PORT}/api/health"


def main() -> int:
    if not (BOX_SYS / "server.py").is_file():
        print("server missing", file=sys.stderr)
        return 1
    if not DECK_HOST_PY.is_file():
        print(f"Deck Host missing: {DECK_HOST_PY}", file=sys.stderr)
        return 1
    cmd = [
        sys.executable,
        str(DECK_HOST_PY),
        "--title",
        "Hermes",
        "--profile",
        "desk",
        "--width",
        "520",
        "--height",
        "720",
        "--url",
        URL,
        "--health",
        HEALTH,
        "--health-timeout",
        "20",
        "--spawn",
        f"{sys.executable} server.py",
        "--spawn-cwd",
        str(BOX_SYS),
    ]
    # ASCII-only logs: launcher redirects stdout to a file under Windows cp1252
    # and dies on arrows/middots (felt like "Hermes never launches").
    pocket = os.environ.get("POCKET_DESKTOP_URL", "http://127.0.0.1:43167")
    print("Hermes | Charlie's Toys | The Deck Host")
    print(f"  url: {URL}  -> pocket {pocket}")
    return subprocess.call(cmd)


if __name__ == "__main__":
    raise SystemExit(main())
