#!/usr/bin/env python3
"""
ineffable-box → The Deck Host (companion profile — small, on top)
"""

from __future__ import annotations

import os
import subprocess
import sys
from pathlib import Path

PROD = Path(__file__).resolve().parent
BOX_SYS = PROD / "box_sys"
DECK_HOST_PY = PROD.parents[2] / "the-deck-host" / "shell" / "deck_host.py"

PORT = os.environ.get("INEFFABLE_PORT", "43120")
URL = os.environ.get("INEFFABLE_URL", f"http://127.0.0.1:{PORT}/")
HEALTH = os.environ.get("INEFFABLE_HEALTH", f"http://127.0.0.1:{PORT}/api/health")


def main() -> int:
    if not (BOX_SYS / "server.py").is_file():
        print(f"server missing: {BOX_SYS}", file=sys.stderr)
        return 1
    if not DECK_HOST_PY.is_file():
        print(f"Deck Host not found: {DECK_HOST_PY}", file=sys.stderr)
        return 1

    profile = os.environ.get("DECK_HOST_PROFILE", "companion").strip() or "companion"
    server_cmd = f"{sys.executable} server.py"
    cmd = [
        sys.executable,
        str(DECK_HOST_PY),
        "--title",
        "ineffable-box",
        "--profile",
        profile,
        "--url",
        URL,
        "--health",
        HEALTH,
        "--health-timeout",
        "20",
        "--spawn",
        server_cmd,
        "--spawn-cwd",
        str(BOX_SYS),
    ]
    print("ineffable-box · companion (always-on report slot)")
    print(f"  url:     {URL}")
    print(f"  profile: {profile}")
    print(f"  paper:   {PROD / 'paper'}")
    return subprocess.call(cmd)


if __name__ == "__main__":
    raise SystemExit(main())
