#!/usr/bin/env python3
"""My PI · Kitten Detective Chapter I → The Deck Host · CO.KDE-002-KD1"""

from __future__ import annotations

import os
import subprocess
import sys
from pathlib import Path

PROD = Path(__file__).resolve().parent
KD1_SYS = PROD / "kd1_sys"
# charlies-toys/kde-kitten-detective/prod → parents[2] = ALICE_BOX
DECK_HOST_PY = PROD.parents[2] / "the-deck-host" / "shell" / "deck_host.py"

PORT = os.environ.get("KD1_PORT", "43142")
URL = f"http://127.0.0.1:{PORT}/"
HEALTH = f"http://127.0.0.1:{PORT}/api/health"


def main() -> int:
    if not (KD1_SYS / "server.py").is_file():
        print("server missing", file=sys.stderr)
        return 1
    if not DECK_HOST_PY.is_file():
        print(f"Deck Host missing: {DECK_HOST_PY}", file=sys.stderr)
        return 1
    profile = os.environ.get("DECK_HOST_PROFILE", "desk").strip() or "desk"
    # Pocket host (nostalgia) — artboard is smaller and scale-to-fills this frame
    width = os.environ.get("KD1_WIDTH", "800")
    height = os.environ.get("KD1_HEIGHT", "600")
    cmd = [
        sys.executable,
        str(DECK_HOST_PY),
        "--title",
        "Detective K · Kitten Noir",
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
        str(KD1_SYS),
    ]
    print(
        f"My PI · Kitten Detective Chapter I · CO.KDE-002-KD1 · Deck Host {width}×{height}"
    )
    return subprocess.call(cmd)


if __name__ == "__main__":
    raise SystemExit(main())
