#!/usr/bin/env python3
"""Generate Whimsical Frog Christmas Catalog page backgrounds (rooms 20–31).

Item-slot rectangles are always complete closed frames. Previous/Next page
signs are separate overlay assets positioned by room maps — never cut blank
pads into these backgrounds for navigation buttons.

Usage:
  python3 scripts/dev/generate_christmas_catalog_pages.py
"""
from __future__ import annotations

import os
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFont

W, H = 1280, 896
ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'images' / 'backgrounds' / 'realistic'
RED = (137, 22, 22)
GREEN = (30, 87, 44)
CREAM = (250, 243, 225)

PAGES = [
    (20, 1, 'Holiday Edition — Cover'),
    (21, 2, 'Holiday Edition — Ornaments'),
    (22, 3, 'Holiday Edition — Tree Trimmings'),
    (23, 4, 'Holiday Edition — Lights & Sparkle'),
    (24, 5, 'Holiday Edition — Mantel & Stockings'),
    (25, 6, 'Holiday Edition — Gifts Under the Tree'),
    (26, 7, 'Holiday Edition — Table & Centerpieces'),
    (27, 8, 'Holiday Edition — Kitchen Cheer'),
    (28, 9, 'Holiday Edition — Kids & Toys'),
    (29, 10, 'Holiday Edition — Cozy Apparel'),
    (30, 11, 'Holiday Edition — Outdoor Yard'),
    (31, 12, 'Holiday Edition — Wishlist Finale'),
]


def try_font(size: int, bold: bool = False) -> ImageFont.ImageFont:
    candidates = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf' if bold else '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf' if bold else '/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf',
    ]
    for path in candidates:
        if os.path.exists(path):
            return ImageFont.truetype(path, size)
    return ImageFont.load_default()


def stroke_rect(draw: ImageDraw.ImageDraw, box: tuple[int, int, int, int], color: tuple[int, int, int], width: int = 3) -> None:
    """Draw a rectangle with joined corners (filled edge strips)."""
    x0, y0, x1, y1 = box
    draw.rectangle([x0, y0, x1, y0 + width - 1], fill=color)
    draw.rectangle([x0, y1 - width + 1, x1, y1], fill=color)
    draw.rectangle([x0, y0, x0 + width - 1, y1], fill=color)
    draw.rectangle([x1 - width + 1, y0, x1, y1], fill=color)


def make_page(page: int, subtitle: str) -> Image.Image:
    rng = np.random.default_rng(2000 + page)
    base = np.zeros((H, W, 3), dtype=np.uint8)
    base[:, :] = CREAM
    noise = rng.normal(0, 4.0, (H, W, 3))
    arr = np.clip(base.astype(np.float32) + noise, 0, 255).astype(np.uint8)
    img = Image.fromarray(arr)
    draw = ImageDraw.Draw(img)

    # Full outer frames — never cut corners for buttons
    stroke_rect(draw, (14, 14, W - 15, H - 15), RED, 4)
    stroke_rect(draw, (22, 22, W - 23, H - 23), GREEN, 3)

    draw.rectangle([36, 36, W - 37, 78], fill=RED)
    font_brand = try_font(26, True)
    brand = 'WHIMSICAL FROG'
    bb = draw.textbbox((0, 0), brand, font=font_brand)
    draw.text(((W - (bb[2] - bb[0])) // 2, 46), brand, fill=(255, 255, 255), font=font_brand)

    font_title = try_font(48, True)
    title = 'CHRISTMAS CATALOG'
    bb = draw.textbbox((0, 0), title, font=font_title)
    draw.text(((W - (bb[2] - bb[0])) // 2, 92), title, fill=RED, font=font_title)

    font_sub = try_font(20)
    bb = draw.textbbox((0, 0), subtitle, font=font_sub)
    draw.text(((W - (bb[2] - bb[0])) // 2, 148), subtitle, fill=GREEN, font=font_sub)
    draw.rectangle([48, 178, W - 49, 181], fill=RED)

    # Slots fill nearly to the frame — no reserved blank pads for prev/next
    margin_x = 36
    frame_inner = 28
    top = 196
    gap = 16
    bottom_limit = H - frame_inner - 8
    usable_w = W - 2 * margin_x
    usable_h = bottom_limit - top
    slot_w = (usable_w - 2 * gap) // 3
    slot_h = (usable_h - gap) // 2

    font_num = try_font(22, True)
    n = 1
    for row in range(2):
        for col in range(3):
            x0 = margin_x + col * (slot_w + gap)
            y0 = top + row * (slot_h + gap)
            x1 = x0 + slot_w
            y1 = y0 + slot_h
            color = RED if n % 2 == 1 else GREEN
            draw.rectangle([x0 + 4, y0 + 4, x1 - 4, y1 - 4], fill=(252, 248, 236))
            for x in range(x0 + 10, x1 - 6, 14):
                draw.line([(x, y0 + 6), (x, y1 - 6)], fill=(230, 215, 189), width=1)
            stroke_rect(draw, (x0, y0, x1, y1), color, 4)
            stroke_rect(draw, (x0 + 5, y0 + 5, x1 - 5, y1 - 5), color, 1)
            cx, cy = x0 + 22, y0 + 22
            draw.ellipse([cx - 16, cy - 16, cx + 16, cy + 16], fill=color)
            num = str(n)
            nb = draw.textbbox((0, 0), num, font=font_num)
            draw.text((cx - (nb[2] - nb[0]) // 2, cy - (nb[3] - nb[1]) // 2 - 1), num, fill=(255, 255, 255), font=font_num)
            n += 1

    font_foot = try_font(15)
    page_txt = f'Page {page}'
    bb = draw.textbbox((0, 0), page_txt, font=font_foot)
    draw.text(((W - (bb[2] - bb[0])) // 2, H - 48), page_txt, fill=RED, font=font_foot)

    for y in range(190, H - 40):
        if y % 3 == 0:
            img.putpixel((W // 2, y), (230, 215, 189))
    return img


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    for room, page, subtitle in PAGES:
        img = make_page(page, subtitle)
        base = f'realistic-room{room}-christmas-catalog-p{page:02d}'
        img.save(OUT / f'{base}.png', 'PNG', optimize=True)
        img.save(OUT / f'{base}.webp', 'WEBP', quality=82, method=6)
        print(f'wrote {base}')


if __name__ == '__main__':
    main()
