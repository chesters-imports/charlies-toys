#!/usr/bin/env python3
"""
Time Machina ROM → The Deck Host

Starts this ROM's server, opens The Deck Host on it, stops when the window closes.

  double-click run-machina.bat
  or:  python run-in-deck-host.py
"""

from __future__ import annotations

import os
import subprocess
import sys
from pathlib import Path

PROD = Path(__file__).resolve().parent
MACHINA_SYS = PROD / "machina_sys"
# ALICE_BOX/charlies-toys/meta-time-machine/prod → ../../../the-deck-host/shell
DECK_SHELL = PROD.parents[2] / "the-deck-host" / "shell"
DECK_HOST_PY = DECK_SHELL / "deck_host.py"

URL = os.environ.get("MACHINA_URL", "http://127.0.0.1:43111/")
HEALTH = os.environ.get("MACHINA_HEALTH", "http://127.0.0.1:43111/api/health")


def main() -> int:
    if not MACHINA_SYS.is_dir():
        print(f"machina_sys missing: {MACHINA_SYS}", file=sys.stderr)
        return 1
    if not DECK_HOST_PY.is_file():
        print(f"The Deck Host not found: {DECK_HOST_PY}", file=sys.stderr)
        print("Expected sibling island: ALICE_BOX/the-deck-host/shell/", file=sys.stderr)
        return 1

    server_cmd = f"{sys.executable} server.py"
    # Companion profile: narrow rail, always-on-top — not a classic 1024 desk ROM.
    # Override with DECK_HOST_PROFILE=desk if you want the old full window.
    profile = os.environ.get("DECK_HOST_PROFILE", "companion").strip() or "companion"
    cmd = [
        sys.executable,
        str(DECK_HOST_PY),
        "--title",
        "Time Machina",
        "--profile",
        profile,
        "--url",
        URL,
        "--health",
        HEALTH,
        "--spawn",
        server_cmd,
        "--spawn-cwd",
        str(MACHINA_SYS),
    ]
    print("Time Machina · Companion rail (Deck Host)")
    print(f"  ROM server: {MACHINA_SYS}")
    print(f"  host:       {DECK_HOST_PY}")
    print(f"  profile:    {profile}")
    print(f"  url:        {URL}")
    return subprocess.call(cmd)


if __name__ == "__main__":
    raise SystemExit(main())
