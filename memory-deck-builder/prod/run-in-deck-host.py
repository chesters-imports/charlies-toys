#!/usr/bin/env python3
"""Memory Deck Builder → The Deck Host · CO.KDE-003-MDB"""

from __future__ import annotations

import os
import subprocess
import sys
from pathlib import Path

PROD = Path(__file__).resolve().parent
DECK_SYS = PROD / "deck_sys"
DECK_HOST_PY = PROD.parents[2] / "the-deck-host" / "shell" / "deck_host.py"

PORT = os.environ.get("MDB_PORT", "43173")
URL = f"http://127.0.0.1:{PORT}/"
HEALTH = f"http://127.0.0.1:{PORT}/api/health"


def main() -> int:
    if not (DECK_SYS / "server.py").is_file():
        print("server missing", file=sys.stderr)
        return 1
    if not DECK_HOST_PY.is_file():
        print(f"Deck Host missing: {DECK_HOST_PY}", file=sys.stderr)
        return 1
    width = os.environ.get("MDB_WIDTH", "1280")
    height = os.environ.get("MDB_HEIGHT", "800")
    cmd = [
        sys.executable,
        str(DECK_HOST_PY),
        "--title",
        "Memory Deck Builder",
        "--profile",
        os.environ.get("DECK_HOST_PROFILE", "desk").strip() or "desk",
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
        str(DECK_SYS),
    ]
    print(f"Memory Deck Builder · CO.KDE-003-MDB · Deck Host {width}×{height}")
    return subprocess.call(cmd)


if __name__ == "__main__":
    raise SystemExit(main())
