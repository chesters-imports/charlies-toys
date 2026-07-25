#!/usr/bin/env python3
"""
sdk-import-station → The Deck Host

Spawns serve_rom.py (PHP on :43101), opens Deck Host, stops with the window.
"""

from __future__ import annotations

import os
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
DECK_HOST_PY = ROOT.parents[1] / "the-deck-host" / "shell" / "deck_host.py"

PORT = os.environ.get("SDK_IMPORT_PORT", "43101")
URL = os.environ.get("SDK_IMPORT_URL", f"http://127.0.0.1:{PORT}/")
HEALTH = os.environ.get("SDK_IMPORT_HEALTH", f"http://127.0.0.1:{PORT}/api/health")
GLASS = os.environ.get("GLASS_LOGS_ROOT", r"C:\ALICE_BOX\.glass-logs")
PHP = os.environ.get("SDK_IMPORT_PHP", r"C:\xampp\php\php.exe")


def main() -> int:
    if not (ROOT / "serve_rom.py").is_file():
        print(f"serve_rom.py missing under {ROOT}", file=sys.stderr)
        return 1
    if not DECK_HOST_PY.is_file():
        print(f"The Deck Host not found: {DECK_HOST_PY}", file=sys.stderr)
        return 1
    if not Path(PHP).is_file():
        print(f"PHP not found: {PHP}", file=sys.stderr)
        print("Set SDK_IMPORT_PHP to your php.exe", file=sys.stderr)
        return 1

    if not (ROOT / "work" / "catalog.json").is_file():
        print("[sdk-import] no work/catalog.json yet — run run-catalog.bat if desk is empty")

    env = os.environ.copy()
    env["GLASS_LOGS_ROOT"] = GLASS
    env["SDK_IMPORT_PORT"] = PORT
    env["SDK_IMPORT_PHP"] = PHP

    # Same shape as loreBOX: python + one script (no path-in-quotes spawn hell)
    server_cmd = f"{sys.executable} serve_rom.py"

    cmd = [
        sys.executable,
        str(DECK_HOST_PY),
        "--title",
        "sdk-import-station",
        "--url",
        URL,
        "--health",
        HEALTH,
        "--health-timeout",
        "25",
        "--spawn",
        server_cmd,
        "--spawn-cwd",
        str(ROOT),
    ]
    print("sdk-import-station · The Deck Host")
    print(f"  url:   {URL}")
    print(f"  glass: {GLASS}")
    print(f"  php:   {PHP}")
    print(f"  spawn: {server_cmd}")
    return subprocess.call(cmd, env=env)


if __name__ == "__main__":
    raise SystemExit(main())
