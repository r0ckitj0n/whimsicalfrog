#!/usr/bin/env python3
"""
Build dense 1950s Christmas catalog page backgrounds (1280×896).

- Warm cream aged paper, no text
- Festive decorations (holly, ribbons, baubles, pine) unique per page
- Empty item frames aligned to layout coordinates from christmas_catalog_layouts.php
- Clear bottom nav band for previous/next plaques
"""

from __future__ import annotations

import json
import math
import random
import subprocess
from pathlib import Path

from PIL import Image, ImageDraw, ImageEnhance, ImageFilter

ROOT = Path("/workspace")
OUT_DIR = ROOT / "images/backgrounds/realistic"
W, H = 1280, 896
NAV_TOP = 720  # frames stay above this; nav plaques live below

PAGE_FILES = {
    1: "realistic-room20-christmas-catalog-p01",
    2: "realistic-room21-christmas-catalog-p02",
    3: "realistic-room22-christmas-catalog-p03",
    4: "realistic-room23-christmas-catalog-p04",
    5: "realistic-room24-christmas-catalog-p05",
    6: "realistic-room25-christmas-catalog-p06",
    7: "realistic-room26-christmas-catalog-p07",
    8: "realistic-room27-christmas-catalog-p08",
    9: "realistic-room28-christmas-catalog-p09",
    10: "realistic-room29-christmas-catalog-p10",
    11: "realistic-room30-christmas-catalog-p11",
    12: "realistic-room31-christmas-catalog-p12",
}

# Soft holiday palettes unique per page (paper, border, accent)
PAGE_THEMES = {
    1: ((250, 240, 220), (140, 28, 28), (20, 90, 45), (190, 150, 60)),
    2: ((248, 236, 214), (120, 24, 24), (16, 80, 40), (170, 130, 50)),
    3: ((252, 244, 228), (150, 40, 35), (30, 100, 55), (200, 160, 70)),
    4: ((246, 234, 210), (110, 20, 30), (25, 85, 50), (180, 140, 55)),
    5: ((249, 238, 218), (130, 32, 28), (18, 75, 42), (195, 155, 65)),
    6: ((251, 242, 224), (145, 35, 40), (22, 95, 48), (185, 145, 58)),
    7: ((247, 235, 212), (125, 26, 32), (28, 88, 52), (175, 135, 52)),
    8: ((253, 245, 230), (135, 30, 30), (15, 70, 38), (205, 165, 75)),
    9: ((245, 232, 208), (115, 22, 26), (20, 82, 44), (168, 128, 48)),
    10: ((250, 239, 221), (142, 34, 36), (26, 92, 50), (188, 148, 62)),
    11: ((248, 237, 216), (128, 28, 34), (17, 78, 41), (178, 138, 55)),
    12: ((252, 243, 226), (155, 38, 32), (24, 98, 54), (210, 170, 80)),
}


def load_layouts() -> dict[int, list[dict]]:
    """Ask PHP for slot rectangles so frames match room_maps exactly."""
    php = r"""
    require '/workspace/scripts/db/christmas_catalog_layouts.php';
    $out = [];
    for ($p = 1; $p <= 12; $p++) {
        $slots = wf_christmas_catalog_item_slots($p);
        $out[$p] = array_map(static function ($s) {
            return [
                'top' => $s['top'],
                'left' => $s['left'],
                'width' => $s['width'],
                'height' => $s['height'],
            ];
        }, $slots);
    }
    echo json_encode($out);
    """
    raw = subprocess.check_output(["php", "-r", php], text=True)
    data = json.loads(raw)
    return {int(k): v for k, v in data.items()}


def paper_base(paper: tuple[int, int, int], seed: int) -> Image.Image:
    rng = random.Random(seed)
    img = Image.new("RGB", (W, H), paper)
    px = img.load()
    # Fiber grain
    for _ in range(18000):
        x, y = rng.randrange(W), rng.randrange(H)
        d = rng.randint(-12, 8)
        r, g, b = px[x, y]
        px[x, y] = (
            max(0, min(255, r + d)),
            max(0, min(255, g + d)),
            max(0, min(255, b + d)),
        )
    # Soft center fold
    draw = ImageDraw.Draw(img, "RGBA")
    for i, alpha in enumerate((18, 12, 8)):
        x = W // 2 - 1 + i
        draw.line([(x, 20), (x, NAV_TOP - 10)], fill=(90, 70, 40, alpha), width=1)
    # Vintage print screening
    for y in range(0, H, 3):
        draw.line([(0, y), (W, y)], fill=(paper[0] - 8, paper[1] - 8, paper[2] - 10, 10), width=1)
    return img


def draw_border(draw: ImageDraw.ImageDraw, red, green, gold) -> None:
    for i, color in enumerate((red, green, red, gold)):
        inset = 10 + i * 3
        draw.rectangle([inset, inset, W - 1 - inset, H - 1 - inset], outline=color + (220,), width=2)
    # Inner thin rule above nav
    draw.line([(28, NAV_TOP - 8), (W - 28, NAV_TOP - 8)], fill=gold + (160,), width=2)
    draw.line([(28, NAV_TOP - 5), (W - 28, NAV_TOP - 5)], fill=red + (120,), width=1)


def holly_cluster(draw: ImageDraw.ImageDraw, x: int, y: int, scale: float, green, red, rng: random.Random) -> None:
    for angle in (0, 55, 110, 180, 235, 300):
        a = math.radians(angle + rng.uniform(-8, 8))
        length = 18 * scale
        tip = (x + math.cos(a) * length, y + math.sin(a) * length)
        left = (x + math.cos(a + 0.5) * length * 0.45, y + math.sin(a + 0.5) * length * 0.45)
        right = (x + math.cos(a - 0.5) * length * 0.45, y + math.sin(a - 0.5) * length * 0.45)
        draw.polygon([left, tip, right, (x, y)], fill=green + (210,))
    for _ in range(5):
        bx = x + rng.randint(-6, 6)
        by = y + rng.randint(-6, 6)
        draw.ellipse([bx - 3, by - 3, bx + 3, by + 3], fill=red + (230,))


def bauble(draw: ImageDraw.ImageDraw, x: int, y: int, r: int, color, gold) -> None:
    draw.ellipse([x - r, y - r, x + r, y + r], fill=color + (230,), outline=gold + (200,))
    draw.rectangle([x - 4, y - r - 8, x + 4, y - r], fill=gold + (230,))
    # Highlight
    draw.ellipse([x - r // 2, y - r // 2, x - r // 6, y - r // 6], fill=(255, 255, 255, 90))


def ribbon_bow(draw: ImageDraw.ImageDraw, x: int, y: int, red, gold) -> None:
    draw.ellipse([x - 22, y - 12, x - 2, y + 12], fill=red + (210,), outline=gold + (160,))
    draw.ellipse([x + 2, y - 12, x + 22, y + 12], fill=red + (210,), outline=gold + (160,))
    draw.ellipse([x - 8, y - 8, x + 8, y + 8], fill=red + (230,))
    draw.polygon([(x - 4, y + 6), (x - 16, y + 28), (x + 2, y + 10)], fill=red + (200,))
    draw.polygon([(x + 4, y + 6), (x + 16, y + 28), (x - 2, y + 10)], fill=red + (200,))


def pine_sprig(draw: ImageDraw.ImageDraw, x: int, y: int, green, rng: random.Random) -> None:
    for i in range(10):
        a = math.radians(-90 + rng.uniform(-25, 25))
        length = 12 + i * 2
        x2 = x + math.cos(a) * length + rng.uniform(-4, 4)
        y2 = y + math.sin(a) * length
        draw.line([(x, y), (x2, y2)], fill=green + (180,), width=2)


def decorate(page: int, img: Image.Image, red, green, gold) -> None:
    rng = random.Random(1000 + page)
    overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay, "RGBA")
    draw_border(draw, red, green, gold)

    # Corner holly
    for cx, cy in ((50, 50), (W - 50, 50), (50, NAV_TOP - 40), (W - 50, NAV_TOP - 40)):
        holly_cluster(draw, cx, cy, 1.2 + (page % 3) * 0.15, green, red, rng)

    # Page-unique decoration motifs in margins / nav band (not over frames)
    if page % 3 == 1:
        for i in range(5):
            bauble(draw, 70 + i * 40, NAV_TOP + 40 + (i % 2) * 18, 10 + (i % 3), red if i % 2 else green, gold)
    if page % 3 == 2:
        ribbon_bow(draw, W // 2, 28, red, gold)
        pine_sprig(draw, 90, NAV_TOP + 55, green, rng)
        pine_sprig(draw, W - 90, NAV_TOP + 55, green, rng)
    if page % 3 == 0:
        for i in range(4):
            bauble(draw, W // 2 - 60 + i * 40, 34, 9, gold if i % 2 else red, gold)

    # Side garland dots
    for y in range(120, NAV_TOP - 60, 55 + page):
        holly_cluster(draw, 22, y, 0.7, green, red, rng)
        holly_cluster(draw, W - 22, y, 0.7, green, red, rng)

    # Soft vignette in nav band
    draw.rectangle([20, NAV_TOP, W - 20, H - 16], fill=(red[0], green[1], 30, 18))

    img = Image.alpha_composite(img.convert("RGBA"), overlay).convert("RGB")
    return img


def draw_frames(img: Image.Image, slots: list[dict], red, green, gold, page: int) -> Image.Image:
    overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay, "RGBA")
    cream = (255, 248, 235, 235)
    for i, s in enumerate(slots):
        t, l, w, h = s["top"], s["left"], s["width"], s["height"]
        # Skip if spills into nav
        if t + h > NAV_TOP - 4:
            h = max(20, NAV_TOP - 4 - t)
        color = red if (i + page) % 2 == 0 else green
        # Mat
        draw.rectangle([l - 2, t - 2, l + w + 2, t + h + 2], fill=(60, 40, 25, 40))
        draw.rectangle([l, t, l + w, t + h], fill=cream, outline=color + (230,), width=2)
        # Inner gold rule
        draw.rectangle([l + 3, t + 3, l + w - 3, t + h - 3], outline=gold + (140,), width=1)
        # Soft shadow bottom-right
        draw.rectangle([l + 3, t + h + 1, l + w + 2, t + h + 3], fill=(40, 25, 15, 35))
    return Image.alpha_composite(img.convert("RGBA"), overlay).convert("RGB")


def build_page(page: int, slots: list[dict]) -> Image.Image:
    paper, red, green, gold = PAGE_THEMES[page]
    img = paper_base(paper, seed=page * 97)
    img = decorate(page, img, red, green, gold)
    img = draw_frames(img, slots, red, green, gold, page)
    # Slight warmth
    img = ImageEnhance.Color(img).enhance(1.05)
    img = ImageEnhance.Contrast(img).enhance(1.04)
    img = img.filter(ImageFilter.SMOOTH_MORE)
    return img


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    layouts = load_layouts()
    for page, stem in PAGE_FILES.items():
        slots = layouts[page]
        print(f"page {page}: {len(slots)} frames -> {stem}")
        img = build_page(page, slots)
        webp = OUT_DIR / f"{stem}.webp"
        png = OUT_DIR / f"{stem}.png"
        img.save(webp, "WEBP", quality=90, method=6)
        img.save(png, "PNG", optimize=True)
        print(f"  wrote {webp.name} {img.size}")


if __name__ == "__main__":
    main()
