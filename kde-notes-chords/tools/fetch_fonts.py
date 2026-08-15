#!/usr/bin/env python3
"""Pull latin woff2 faces used by Notes & Chords into prod/kde_sys/fonts/."""

from __future__ import annotations

import re
import urllib.request
from pathlib import Path

FONTS = Path(__file__).resolve().parents[1] / "prod" / "kde_sys" / "fonts"
UA = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
)
CSS_URL = (
    "https://fonts.googleapis.com/css2"
    "?family=Amatic+SC:wght@400;700"
    "&family=Permanent+Marker"
    "&family=IBM+Plex+Mono:wght@400;600"
    "&family=Special+Elite"
    "&display=swap"
)


def main() -> int:
    FONTS.mkdir(parents=True, exist_ok=True)
    req = urllib.request.Request(CSS_URL, headers={"User-Agent": UA})
    css = urllib.request.urlopen(req, timeout=30).read().decode("utf-8")
    parts = re.split(r"/\* ([^*]+) \*/", css)
    out: list[str] = []
    i = 1
    while i < len(parts) - 1:
        label = parts[i].strip()
        block = parts[i + 1]
        i += 2
        if label != "latin":
            continue
        m = re.search(r"font-family:\s*'([^']+)'", block)
        w = re.search(r"font-weight:\s*(\d+)", block)
        u = re.search(r"url\((https://[^)]+)\)", block)
        if not (m and w and u):
            continue
        fam = m.group(1)
        weight = w.group(1)
        src = u.group(1)
        slug = re.sub(r"[^a-z0-9]+", "-", fam.lower()).strip("-")
        fname = f"{slug}-{weight}.woff2"
        dest = FONTS / fname
        urllib.request.urlretrieve(src, dest)
        print(fname, dest.stat().st_size)
        out.append("/* latin */" + block.replace(src, f"/fonts/{fname}"))
    (FONTS / "faces.css").write_text("".join(out), encoding="utf-8")
    print("wrote", FONTS / "faces.css")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
