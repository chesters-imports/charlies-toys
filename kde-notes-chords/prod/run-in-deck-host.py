#!/usr/bin/env python3
"""KDE Notes & Chords → The Deck Host"""

from __future__ import annotations

import os
import subprocess
import sys
from pathlib import Path

PROD = Path(__file__).resolve().parent
KDE_SYS = PROD / "kde_sys"
# charlies-toys/kde-notes-chords/prod → parents[2] = ALICE_BOX
DECK_HOST_PY = PROD.parents[2] / "the-deck-host" / "shell" / "deck_host.py"

def main() -> int:
    if not (KDE_SYS / "server.py").is_file():
        print("server missing", file=sys.stderr)
        return 1
    if not DECK_HOST_PY.is_file():
        print(f"Deck Host missing: {DECK_HOST_PY}", file=sys.stderr)
        return 1
    # Pocket machine — not full desk glass. 800×600 fits the strip + case file.
    profile = os.environ.get("DECK_HOST_PROFILE", "desk").strip() or "desk"
    width = os.environ.get("KDE_WIDTH", "800")
    height = os.environ.get("KDE_HEIGHT", "600")
    # In-process ROM serve — same path the itch pack uses (no extra Python).
    cmd = [
        sys.executable,
        str(DECK_HOST_PY),
        "--title",
        "KDE Notes & Chords",
        "--profile",
        profile,
        "--width",
        str(width),
        "--height",
        str(height),
        "--rom-dir",
        str(KDE_SYS),
    ]
    print("KDE Notes & Chords · CO.KDE-001-INSTR · Deck Host 800×600")
    return subprocess.call(cmd)


if __name__ == "__main__":
    raise SystemExit(main())
