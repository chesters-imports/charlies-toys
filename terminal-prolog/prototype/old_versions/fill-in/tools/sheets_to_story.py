#!/usr/bin/env python3
"""
Read fill-in/sheets/ and print a CONCEPTS / COMBINES snippet for story.py

  cd fill-in
  python tools/sheets_to_story.py

Does not overwrite story.py by default (safe). Copy-paste what you want.
"""
from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SHEETS = ROOT / "sheets"
CONCEPTS_DIR = SHEETS / "concepts"


def load_simple_concept_yaml(text: str) -> dict:
    """Minimal yaml-ish: id: x and key: | blocks."""
    data: dict = {}
    key = None
    buf: list[str] = []
    def flush():
        nonlocal key, buf
        if key is not None:
            data[key] = "\n".join(buf).rstrip("\n") if buf else data.get(key, "")
        key, buf = None, []

    for raw in text.splitlines():
        if raw.strip().startswith("#"):
            continue
        if raw.startswith("  ") and key is not None:
            # continuation of | block (we strip the 2-space from template)
            line = raw[2:] if raw.startswith("  ") else raw
            buf.append(line)
            continue
        if ":" in raw and not raw.startswith(" "):
            flush()
            k, v = raw.split(":", 1)
            k, v = k.strip(), v.strip()
            if v == "|" or v == ">":
                key = k
                buf = []
            else:
                data[k] = v.strip('"').strip("'")
                key = None
            continue
        if key is not None:
            buf.append(raw)
    flush()
    return data


def py_str(s: str) -> str:
    return '"""\n' + s.replace('\\', '\\\\') + '\n"""'


def main() -> int:
    print("# --- generated from sheets/ (paste into story.py as needed) ---\n")
    concepts = {}
    if CONCEPTS_DIR.is_dir():
        for path in sorted(CONCEPTS_DIR.glob("*.yaml")):
            d = load_simple_concept_yaml(path.read_text(encoding="utf-8"))
            cid = d.get("id") or path.stem
            concepts[cid] = {k: v for k, v in d.items() if k != "id" and v}
    if concepts:
        print("CONCEPTS = {")
        for cid, fields in concepts.items():
            print(f'    "{cid}": {{')
            for k, v in fields.items():
                print(f'        "{k}": {py_str(str(v))},')
            print("    },")
        print("}\n")
    else:
        print("# (no sheets/concepts/*.yaml yet — use concept_form.html)\n")

    combines_path = SHEETS / "combines.yaml"
    if combines_path.is_file():
        print("# COMBINES: edit sheets/combines.yaml then mirror into story.py COMBINES")
        print("# (full yaml parse optional; story.py already has live COMBINES)\n")
        print(combines_path.read_text(encoding="utf-8"))

    thoughts = SHEETS / "thoughts-void.txt"
    if thoughts.is_file():
        lines = [ln for ln in thoughts.read_text(encoding="utf-8").splitlines() if ln.strip()]
        print("\n# void thoughts sample:")
        print("thoughts = [")
        for ln in lines:
            print(f'    {ln!r},')
        print("]")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
