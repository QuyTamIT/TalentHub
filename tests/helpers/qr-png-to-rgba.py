"""Convert a private QR PNG to raw RGBA bytes for the vendored decoder."""

from __future__ import annotations

import json
import os
import sys

from PIL import Image


def main() -> int:
    if len(sys.argv) != 4:
        return 2

    source_path, rgba_path, metadata_path = sys.argv[1:]
    try:
        with Image.open(source_path) as source:
            source.verify()
        with Image.open(source_path) as source:
            image = source.convert("RGBA")
            width, height = image.size
            if width <= 0 or height <= 0 or width > 4096 or height > 4096:
                return 2
            with open(rgba_path, "wb") as rgba_file:
                rgba_file.write(image.tobytes())
            with open(metadata_path, "w", encoding="utf-8") as metadata_file:
                json.dump({"width": width, "height": height}, metadata_file)
        return 0
    except Exception:
        for path in (rgba_path, metadata_path):
            try:
                if os.path.exists(path):
                    os.remove(path)
            except OSError:
                pass
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
