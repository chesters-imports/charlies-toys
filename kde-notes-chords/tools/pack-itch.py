#!/usr/bin/env python3
"""Pack KDE Notes & Chords as a one-folder itch drop (Deck Host + ROM).

Writes:
  ship/KDE-Notes-and-Chords/
    KDE Notes and Chords.exe   (if PyInstaller is installed)
    rom/                       (the toy)
    rom.manifest
    README.txt
    THIRD_PARTY.txt

Without PyInstaller: still writes rom + run-dev.bat (needs local Python).
"""

from __future__ import annotations

import shutil
import subprocess
import sys
from pathlib import Path

TOY = Path(__file__).resolve().parents[1]
KDE_SYS = TOY / "prod" / "kde_sys"
SHIP_SRC = TOY / "ship"
OUT = SHIP_SRC / "KDE-Notes-and-Chords"
ALICE = TOY.parents[1]
SHELL = ALICE / "the-deck-host" / "shell"
SKIP = {
    "__pycache__",
    "desk-server.log",
    "_fetch_fonts.py",
}


def copy_rom(dest: Path) -> None:
    dest.mkdir(parents=True, exist_ok=True)
    for src in KDE_SYS.iterdir():
        if src.name in SKIP or src.name.endswith(".pyc"):
            continue
        target = dest / src.name
        if src.is_dir():
            if target.exists():
                shutil.rmtree(target)
            shutil.copytree(src, target, ignore=shutil.ignore_patterns("__pycache__"))
        else:
            shutil.copy2(src, target)


def write_readme(dest: Path) -> None:
    (dest / "README.txt").write_text(
        "\n".join(
            [
                "KDE Notes & Chords",
                "Charlie's Toys · KDE Fugue Inc. · CO.KDE-001-INSTR",
                "",
                "A pocket machine. Fifteen keys. Cases walk in.",
                "",
                "Windows 10/11. Uses the Edge WebView2 runtime (already on most PCs).",
                "Double-click  KDE Notes and Chords.exe",
                "",
                "If the window is a blank pane: install the WebView2 Evergreen runtime",
                "from Microsoft, then try again.",
                "",
                "Sound starts after you click PLAY (browser rule).",
                "",
            ]
        ),
        encoding="utf-8",
    )
    (dest / "THIRD_PARTY.txt").write_text(
        "\n".join(
            [
                "Tone.js 15.1.22 — MIT — https://tonejs.github.io/",
                "Amatic SC, Permanent Marker, Special Elite — SIL OFL",
                "  (Google Fonts)",
                "IBM Plex Mono — SIL OFL — IBM",
                "The Deck Host — runtime in this folder — ALICE_BOX / Charlie's Toys",
                "",
            ]
        ),
        encoding="utf-8",
    )


def build_host(dest: Path) -> bool:
    try:
        import PyInstaller  # noqa: F401
    except ImportError:
        print("PyInstaller not installed — writing run-dev.bat only")
        print("  pip install pyinstaller")
        return False
    work = SHIP_SRC / "_build"
    dist = SHIP_SRC / "_dist"
    if work.exists():
        shutil.rmtree(work)
    if dist.exists():
        shutil.rmtree(dist)
    name = "KDE Notes and Chords"
    sep = ";" if sys.platform == "win32" else ":"
    cmd = [
        sys.executable,
        "-m",
        "PyInstaller",
        "--noconfirm",
        "--onedir",
        "--windowed",
        "--name",
        name,
        "--distpath",
        str(dist),
        "--workpath",
        str(work),
        "--specpath",
        str(work),
        "--add-data",
        f"{SHELL / 'caption.js'}{sep}.",
        "--add-data",
        f"{SHELL / 'launcher.html'}{sep}.",
        "--hidden-import",
        "webview",
        "--hidden-import",
        "webview.platforms.edgechromium",
        str(SHELL / "deck_host.py"),
    ]
    print(" ".join(cmd))
    rc = subprocess.call(cmd, cwd=str(SHELL))
    if rc != 0:
        print("pyinstaller failed", rc)
        return False
    built = dist / name
    if not built.is_dir():
        print("missing", built)
        return False
    for item in built.iterdir():
        target = dest / item.name
        if item.is_dir():
            if target.exists():
                shutil.rmtree(target)
            shutil.copytree(item, target)
        else:
            shutil.copy2(item, target)
    return True


def write_dev_bat(dest: Path) -> None:
    (dest / "run-dev.bat").write_text(
        "\n".join(
            [
                "@echo off",
                "cd /d \"%~dp0\"",
                f'"{sys.executable}" "{SHELL / "deck_host.py"}" --manifest "%~dp0rom.manifest"',
                "if errorlevel 1 pause",
                "",
            ]
        ),
        encoding="utf-8",
    )


def main() -> int:
    if not (KDE_SYS / "index.html").is_file():
        print("kde_sys missing", file=sys.stderr)
        return 1
    if OUT.exists():
        shutil.rmtree(OUT)
    OUT.mkdir(parents=True)
    shutil.copy2(SHIP_SRC / "rom.manifest", OUT / "rom.manifest")
    copy_rom(OUT / "rom")
    write_readme(OUT)
    write_dev_bat(OUT)
    frozen = build_host(OUT)
    print("packed", OUT)
    print("frozen" if frozen else "dev-only (install pyinstaller for the exe)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
