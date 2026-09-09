#!/usr/bin/env python3
"""
Build Christmas Catalog page backgrounds (1280x896):
- Aged cream paper with 1950s print grain (from realistic AI paper refs)
- Unique empty item frames per page (exact hotspot coords)
- Clear bottom nav band that stays part of the page (not a broken overlay)
- Strictly no text
"""

from __future__ import annotations

from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageEnhance, ImageFilter

ROOT = Path("/workspace")
OUT_DIR = ROOT / "images/backgrounds/realistic"
ARTIFACT_DIR = Path("/opt/cursor/artifacts/assets")
GEN_DIR = Path("/workspace/.local/state/catalog-gen")
W, H = 1280, 896
NAV_TOP = 720  # frames must stay above this

LAYOUTS: dict[int, list[tuple[int, int, int, int]]] = {
    1: [(160, 70, 960, 430), (80, 520, 520, 170), (680, 520, 520, 170)],
    2: [
        (50, 60, 280, 300), (350, 60, 280, 300), (650, 60, 280, 300), (950, 60, 280, 300),
        (50, 380, 280, 300), (350, 380, 280, 300), (650, 380, 280, 300), (950, 380, 280, 300),
    ],
    3: [
        (50, 60, 520, 620),
        (600, 60, 300, 300), (930, 60, 300, 300),
        (600, 380, 300, 300), (930, 380, 300, 300),
    ],
    4: [
        (60, 60, 360, 420), (460, 250, 360, 420), (860, 60, 360, 420),
        (60, 500, 360, 180), (460, 60, 360, 170), (860, 500, 360, 180),
    ],
    5: [
        (50, 60, 570, 300), (660, 60, 570, 300),
        (50, 380, 570, 300), (660, 380, 570, 300),
    ],
    6: [
        (50, 60, 380, 320), (450, 60, 380, 320), (850, 60, 380, 320),
        (40, 410, 280, 270), (340, 410, 280, 270), (640, 410, 280, 270), (940, 410, 280, 270),
    ],
    7: [
        (340, 180, 600, 360),
        (50, 50, 260, 240), (970, 50, 260, 240),
        (50, 440, 260, 240), (970, 440, 260, 240),
    ],
    8: [
        (50, 50, 380, 200), (450, 50, 380, 200), (850, 50, 380, 200),
        (50, 270, 380, 200), (450, 270, 380, 200), (850, 270, 380, 200),
        (50, 490, 380, 190), (450, 490, 380, 190), (850, 490, 380, 190),
    ],
    9: [
        (80, 50, 520, 200), (80, 270, 520, 200), (80, 490, 520, 190),
        (680, 50, 520, 200), (680, 270, 520, 200), (680, 490, 520, 190),
    ],
    10: [
        (50, 50, 480, 630),
        (560, 50, 660, 200),
        (560, 270, 320, 200), (900, 270, 320, 200),
        (560, 490, 660, 190),
    ],
    11: [
        (40, 50, 400, 280), (460, 50, 360, 200), (840, 50, 400, 280),
        (460, 270, 360, 180),
        (40, 350, 400, 330), (460, 470, 780, 210),
    ],
    12: [
        (180, 50, 920, 420),
        (50, 490, 380, 190), (450, 490, 380, 190), (850, 490, 380, 190),
    ],
}

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

AI_SOURCES = {
    1: "catalog-p01-cover-v2.png",
    2: "catalog-p02-ornaments-v2.png",
    3: "catalog-p03-tree-v2.png",
    4: "catalog-p04-lights-v2.png",
    5: "catalog-p05-mantel-v2.png",
    6: "catalog-p06-gifts-v2.png",
    7: "catalog-p07-table-v2.png",
    8: "catalog-p08-kitchen-v2.png",
    9: "catalog-p09-kids-v2.png",
    10: "catalog-p10-apparel-v2.png",
    11: "catalog-p11-outdoor-v2.png",
    12: "catalog-p12-finale-v2.png",
}

RED = (132, 34, 34)
GREEN = (46, 88, 52)
CREAM = (238, 226, 200)


def load_ai(page: int) -> Image.Image | None:
    name = AI_SOURCES.get(page)
    ai = None
    if name:
        for path in (ARTIFACT_DIR / name, GEN_DIR / name):
            if path.exists():
                ai = Image.open(path).convert("RGB").resize((W, H), Image.Resampling.LANCZOS)
                break
    rich_path = GEN_DIR / "rich-paper.png"
    if not rich_path.exists():
        sears = ROOT / ".local/state/catalog-refs/sears-style-ref-p02.png"
        if sears.exists():
            src = Image.open(sears).convert("RGB").resize((W, H), Image.Resampling.LANCZOS)
            arr = np.asarray(src).astype(np.float32)
            gray = arr.mean(2, keepdims=True)
            paper = np.array(CREAM, dtype=np.float32)
            span = float(gray.max() - gray.min()) or 1.0
            norm = (gray - gray.min()) / span
            grain = paper * (0.82 + 0.28 * norm) + (arr - gray) * 0.15
            Image.fromarray(np.clip(grain, 0, 255).astype(np.uint8)).save(rich_path)
    if rich_path.exists():
        rich = Image.open(rich_path).convert("RGB").resize((W, H), Image.Resampling.LANCZOS)
        if ai is None:
            return rich
        a = np.asarray(ai).astype(np.float32)
        r = np.asarray(rich).astype(np.float32)
        return Image.fromarray(np.clip(a * 0.35 + r * 0.65, 0, 255).astype(np.uint8), "RGB")
    return ai


def paper_color_from(im: Image.Image) -> np.ndarray:
    arr = np.asarray(im).astype(np.float32)
    lum = arr.mean(axis=2)
    mask = lum > np.percentile(lum, 70)
    if mask.sum() < 500:
        mask = lum > np.percentile(lum, 50)
    return arr[mask].mean(axis=0)


def erase_print_keep_grain(im: Image.Image) -> Image.Image:
    """Remove dark printed content (text/products/old frames) while keeping paper grain."""
    arr = np.asarray(im).astype(np.float32)
    paper = paper_color_from(im)
    lum = arr.mean(axis=2)
    # Stronger erase on darker print; light paper stays
    darkness = np.clip((200 - lum) / 120.0, 0.0, 1.0)
    # Preserve high-frequency grain by only shifting toward paper mean
    local = arr.copy()
    # Mild blur of source as grain carrier
    blurred = np.asarray(im.filter(ImageFilter.GaussianBlur(radius=1.2))).astype(np.float32)
    grain = arr - blurred
    washed = paper + grain * 0.85
    out = arr * (1.0 - darkness[..., None] * 0.88) + washed * (darkness[..., None] * 0.88)
    # Keep outer margins slightly more paper-like
    return Image.fromarray(np.clip(out, 0, 255).astype(np.uint8), "RGB")


def fill_content_with_paper(im: Image.Image) -> Image.Image:
    """Clear the content band to continuous paper before redrawing exact frames."""
    arr = np.asarray(im).astype(np.float32)
    paper = paper_color_from(im)
    # Sample a clean paper patch from bottom nav corner
    patch = arr[NAV_TOP + 20 : NAV_TOP + 80, 40:140, :]
    if patch.size:
        paper = patch.mean(axis=(0, 1))
    # Content interior (inside outer border)
    x0, y0, x1, y1 = 28, 28, W - 28, NAV_TOP
    region = arr[y0:y1, x0:x1]
    # Keep subtle grain from region
    blur = np.asarray(
        Image.fromarray(np.clip(region, 0, 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(8))
    ).astype(np.float32)
    grain = region - blur
    filled = paper + np.clip(grain, -18, 18) * 0.55
    # Soft edge so it doesn't look cut out
    arr[y0:y1, x0:x1] = filled * 0.82 + region * 0.18
    return Image.fromarray(np.clip(arr, 0, 255).astype(np.uint8), "RGB")


def synthesize_paper(seed: int) -> Image.Image:
    rng = np.random.default_rng(2000 + seed)
    base = np.zeros((H, W, 3), dtype=np.float32)
    base[:] = CREAM
    noise = rng.normal(0, 6.5, (H, W, 3))
    blotch = rng.normal(0, 1, (H // 12, W // 12))
    blotch = np.array(
        Image.fromarray(
            (((blotch - blotch.min()) / (blotch.ptp() + 1e-6)) * 255).astype(np.uint8)
        ).resize((W, H), Image.Resampling.BICUBIC)
    ).astype(np.float32)
    blotch = (blotch - 128.0) * 0.1
    # Warm aging toward bottom corners
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    age = ((yy / H) * 0.35 + ((xx - W / 2).abs() / (W / 2)) * 0.15) if False else (
        (yy / H) * 8 + (np.abs(xx - W / 2) / (W / 2)) * 4
    )
    img = np.clip(base + noise + blotch[..., None] + age[..., None] * np.array([0.4, 0.25, 0.05]), 0, 255)
    return Image.fromarray(img.astype(np.uint8), "RGB")


def draw_outer_border(draw: ImageDraw.ImageDraw) -> None:
    for inset, color, width in [(8, RED, 5), (16, GREEN, 3), (22, RED, 2)]:
        draw.rectangle([inset, inset, W - inset - 1, H - inset - 1], outline=color, width=width)


def draw_center_fold(draw: ImageDraw.ImageDraw) -> None:
    x = W // 2
    draw.line([(x, 26), (x, H - 26)], fill=(176, 158, 128), width=2)
    draw.line([(x + 2, 26), (x + 2, H - 26)], fill=(220, 206, 178), width=1)


def draw_frame(draw: ImageDraw.ImageDraw, box: tuple[int, int, int, int], idx: int, paper_rgb: tuple[int, int, int]) -> None:
    left, top, width, height = box
    right, bottom = left + width, top + height
    if bottom > NAV_TOP - 10:
        bottom = NAV_TOP - 10
        height = bottom - top
        if height < 48:
            return
    color = RED if idx % 2 == 0 else GREEN
    # Slightly warmer empty fill than page paper
    fill = tuple(min(255, c + 4) for c in paper_rgb)
    draw.rectangle([left, top, right, bottom], fill=fill, outline=color, width=4)
    draw.rectangle([left + 6, top + 6, right - 6, bottom - 6], outline=color, width=1)
    # Soft empty-slot ruling (not text)
    for i in range(1, 5):
        y = top + int(height * i / 5)
        draw.line([(left + 14, y), (right - 14, y)], fill=(224, 212, 188), width=1)


def vintage_print_look(im: Image.Image) -> Image.Image:
    im = ImageEnhance.Color(im).enhance(0.9)
    im = ImageEnhance.Contrast(im).enhance(1.08)
    im = ImageEnhance.Brightness(im).enhance(0.98)
    arr = np.asarray(im).astype(np.float32)
    yy, xx = np.mgrid[0:H, 0:W]
    screen = (np.sin(xx * 1.15) * np.sin(yy * 1.05)) * 2.8
    arr = np.clip(arr + screen[..., None], 0, 255)
    # Soft vignette
    cx, cy = W / 2, H / 2
    dist = np.sqrt(((xx - cx) / cx) ** 2 + ((yy - cy) / cy) ** 2)
    arr = arr * (1 - 0.06 * np.clip(dist, 0, 1))[..., None]
    out = Image.fromarray(arr.astype(np.uint8), "RGB")
    return out.filter(ImageFilter.UnsharpMask(radius=1.2, percent=60, threshold=4))


def build_page(page: int) -> Image.Image:
    ai = load_ai(page)
    if ai is not None:
        base = erase_print_keep_grain(ai)
        base = fill_content_with_paper(base)
    else:
        base = synthesize_paper(page)

    paper = tuple(int(x) for x in paper_color_from(base))
    draw = ImageDraw.Draw(base)
    draw_outer_border(draw)
    draw_center_fold(draw)
    for idx, box in enumerate(LAYOUTS[page]):
        draw_frame(draw, box, idx, paper)

    # Nav band remains continuous paper (border already surrounds full page).
    # Soften any leftover print only in the button corners.
    arr = np.asarray(base).astype(np.float32)
    paper_arr = np.array(paper, dtype=np.float32)
    for y0, y1, x0, x1 in [
        (NAV_TOP, H - 24, 24, 260),
        (NAV_TOP, H - 24, W - 260, W - 24),
    ]:
        region = arr[y0:y1, x0:x1]
        lum = region.mean(axis=2)
        dark = np.clip((190 - lum) / 100.0, 0, 1)[..., None]
        arr[y0:y1, x0:x1] = region * (1 - dark * 0.75) + paper_arr * (dark * 0.75)
    base = Image.fromarray(np.clip(arr, 0, 255).astype(np.uint8), "RGB")
    return vintage_print_look(base)


def save_page(page: int, im: Image.Image) -> None:
    stem = PAGE_FILES[page]
    png = OUT_DIR / f"{stem}.png"
    webp = OUT_DIR / f"{stem}.webp"
    im.save(png, optimize=True)
    im.save(webp, "WEBP", quality=90, method=6)
    im.save(GEN_DIR / f"preview-p{page:02d}.png")
    print(f"OK page {page}: {png.name} ({png.stat().st_size} bytes)")


def main() -> None:
    GEN_DIR.mkdir(parents=True, exist_ok=True)
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    for page in range(1, 13):
        save_page(page, build_page(page))
    print("done")


if __name__ == "__main__":
    main()
