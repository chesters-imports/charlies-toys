#!/usr/bin/env python3
"""
ROM side-process for The Deck Host.

Starts PHP built-in server with a real argv list (no shell quoting).
Host spawns:  python serve_rom.py
"""

from __future__ import annotations

import os
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
PUBLIC = ROOT / "public"
ROUTER = PUBLIC / "router.php"

PORT = os.environ.get("SDK_IMPORT_PORT", "43101")
PHP = os.environ.get("SDK_IMPORT_PHP", r"C:\xampp\php\php.exe")


def main() -> int:
    if not Path(PHP).is_file():
        print(f"PHP not found: {PHP}", file=sys.stderr)
        return 1
    if not ROUTER.is_file():
        print(f"router missing: {ROUTER}", file=sys.stderr)
        return 1

    # inherit GLASS_LOGS_ROOT etc. from parent
    argv = [
        PHP,
        "-S",
        f"127.0.0.1:{PORT}",
        "-t",
        str(PUBLIC),
        str(ROUTER),
    ]
    print(f"[serve_rom] {' '.join(argv)}", flush=True)
    # replace this process with php so host kill tree hits php
    if sys.platform == "win32":
        # Windows has no execvp for different image cleanly — run as child and wait
        p = subprocess.Popen(argv, cwd=str(ROOT))
        return int(p.wait() or 0)
    os.execv(PHP, argv)
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
