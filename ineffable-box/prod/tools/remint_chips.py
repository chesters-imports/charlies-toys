#!/usr/bin/env python3
"""Remint narrative chip codes for existing ineffable-box drops."""

from __future__ import annotations

import json
import re
import secrets
import sys
import time
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PAPER = ROOT / "paper"
sys.path.insert(0, str(ROOT / "box_sys"))

from server import SERVICE_PREFIX, SERVICES, mint_chip  # noqa: E402


def looks_narrative(chip: str) -> bool:
    """Already new-style OMN-slug-INIT-date-salt?"""
    if not chip:
        return False
    if re.match(r"^(OMN|HYM|SCR|INF)-.+-[A-Z0-9]{1,8}-\d{6}-[a-f0-9]{4}", chip):
        return True
    # old opaque: 20260726-135544-4168b1
    if re.match(r"^\d{8}-\d{6}-[a-f0-9]+$", chip):
        return False
    return False


def main() -> int:
    if not PAPER.is_dir():
        print("no paper dir", PAPER)
        return 1
    n_ok = 0
    n_skip = 0
    for folder_meta in SERVICES.values():
        d = PAPER / folder_meta["folder"]
        if not d.is_dir():
            continue
        for path in sorted(d.glob("*.ineff.json")):
            try:
                doc = json.loads(path.read_text(encoding="utf-8"))
            except (OSError, json.JSONDecodeError) as e:
                print("skip bad", path.name, e)
                continue
            old_id = str(doc.get("chip") or doc.get("id") or path.stem)
            service = str(doc.get("service") or folder_meta["id"])
            body = str(doc.get("body") or "")
            ev = int(doc.get("event_unix") or doc.get("saved_at") or time.time())
            if looks_narrative(old_id) and doc.get("version", 0) >= 2 and doc.get("source") == "ineffable-box":
                # still remint if missing slug structure from body? force if user wants all fixed
                # Only skip if already has service prefix + slug + initials pattern
                if re.match(
                    r"^(OMN|HYM|SCR)-[a-z0-9].+-[A-Z0-9]+-\d{6}-[a-f0-9]{4}",
                    old_id,
                ):
                    print("keep", old_id)
                    n_skip += 1
                    continue

            new_chip = mint_chip(service, body, ev)
            base = new_chip
            n = 0
            new_path = d / f"{new_chip}.ineff.json"
            while new_path.is_file() and new_path.resolve() != path.resolve():
                n += 1
                new_chip = f"{base}-{n}"
                new_path = d / f"{new_chip}.ineff.json"

            meta = SERVICES.get(service, folder_meta)
            doc["id"] = new_chip
            doc["chip"] = new_chip
            doc["chip_face"] = meta.get("chip", doc.get("chip_face", ""))
            doc["service"] = service
            doc["service_prefix"] = SERVICE_PREFIX.get(service, "INF")
            doc["whom"] = meta.get("whom", doc.get("whom", ""))
            doc["source"] = "ineffable-box"
            doc["tool"] = "ineffable-box"
            doc["version"] = 2
            doc["legacy_id"] = old_id

            new_path.write_text(
                json.dumps(doc, indent=2, ensure_ascii=False) + "\n",
                encoding="utf-8",
            )
            if new_path.resolve() != path.resolve():
                path.unlink(missing_ok=True)
            print(f"{old_id}  →  {new_chip}")
            n_ok += 1

    print(f"\ndone reminted={n_ok} kept={n_skip}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
