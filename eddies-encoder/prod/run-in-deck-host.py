#!/usr/bin/env python3
"""Eddy's Encoder Envelope → The Deck Host (ALWAYS — never Chrome chrome)"""

from __future__ import annotations

import os
import subprocess
import sys
from pathlib import Path

PROD = Path(__file__).resolve().parent
BOX_SYS = PROD / "box_sys"
DECK_HOST_PY = PROD.parents[2] / "the-deck-host" / "shell" / "deck_host.py"

PORT = os.environ.get("EDDIE_PORT", "43145")
URL = f"http://127.0.0.1:{PORT}/"
HEALTH = f"http://127.0.0.1:{PORT}/api/health"


def main() -> int:
    if not (BOX_SYS / "server.py").is_file():
        print("server missing", file=sys.stderr)
        return 1
    if not DECK_HOST_PY.is_file():
        print(f"Deck Host missing: {DECK_HOST_PY}", file=sys.stderr)
        return 1
    # Toy size — companion-ish desk, not GRM office
    profile = os.environ.get("DECK_HOST_PROFILE", "desk").strip() or "desk"
    width = os.environ.get("EDDIE_WIDTH", "800")
    height = os.environ.get("EDDIE_HEIGHT", "600")
    cmd = [
        sys.executable,
        str(DECK_HOST_PY),
        "--title",
        "Eddy's Encoder",
        "--profile",
        profile,
        "--width",
        str(width),
        "--height",
        str(height),
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
    print("Eddy's Encoder · Charlie's Toys · The Deck Host")
    print(f"  url: {URL}  size: {width}x{height}  profile: {profile}")
    return subprocess.call(cmd)


if __name__ == "__main__":
    raise SystemExit(main())
