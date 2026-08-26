"""Render one opaque token as a QR PNG without logging the token."""

from __future__ import annotations

import os
import sys

from PIL import Image, ImageDraw
from reportlab.graphics.barcode import qr


def main() -> int:
    if len(sys.argv) != 2:
        return 2

    output_path = sys.argv[1]
    token = sys.stdin.read()
    if not token or len(token) > 512 or any(character.isspace() for character in token):
        return 2

    try:
        widget = qr.QrCodeWidget(token)
        widget.qr.make()
        modules = widget.qr.modules
        quiet_zone = 4
        scale = 8
        module_count = len(modules)
        image_size = (module_count + quiet_zone * 2) * scale
        image = Image.new("RGB", (image_size, image_size), "white")
        drawing = ImageDraw.Draw(image)
        for row_index, row in enumerate(modules):
            for column_index, enabled in enumerate(row):
                if enabled:
                    left = (column_index + quiet_zone) * scale
                    top = (row_index + quiet_zone) * scale
                    drawing.rectangle(
                        (left, top, left + scale - 1, top + scale - 1),
                        fill="black",
                    )
        image.save(output_path, format="PNG", optimize=True)
        return 0
    except Exception:
        try:
            if os.path.exists(output_path):
                os.remove(output_path)
        except OSError:
            pass
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
