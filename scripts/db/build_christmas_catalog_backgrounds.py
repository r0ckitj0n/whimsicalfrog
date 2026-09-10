#!/usr/bin/env python3
"""
Build Christmas Catalog page backgrounds (1280x896).

- Aged cream paper with 1950s print grain
- Cover (page 1): clickable table-of-contents index of all 12 pages
- Pages 2-12: unique empty item frames (exact hotspot coords)
- Realistic Christmas decorations outside product/index boxes
- Clear bottom nav band for previous/next plaques
"""

from __future__ import annotations

from pathlib import Path

import numpy as np
from PIL import (
    Image,
    ImageDraw,
    ImageEnhance,
    ImageFilter,
    ImageFont,
    ImageOps,
)

ROOT = Path("/workspace")
OUT_DIR = ROOT / "images/backgrounds/realistic"
DECOR_DIR = ROOT / "images/decorations/realistic"
ARTIFACT_DIR = Path("/opt/cursor/artifacts/assets")
GEN_DIR = Path("/workspace/.local/state/catalog-gen")

W, H = 1280, 896
NAV_TOP = 720

LAYOUTS: dict[int, list[tuple[int, int, int, int]]] = {
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

INDEX_ENTRIES: list[tuple[int, str]] = [
    (1, "Cover & Index"),
    (2, "Ornaments"),
    (3, "Tree Trimmings"),
    (4, "Lights & Sparkle"),
    (5, "Mantel & Stockings"),
    (6, "Gifts Under the Tree"),
    (7, "Table & Centerpieces"),
    (8, "Kitchen Cheer"),
    (9, "Kids & Toys"),
    (10, "Cozy Apparel"),
    (11, "Outdoor Yard"),
    (12, "Wishlist Finale"),
]

INDEX_ROW_H = 78
INDEX_ROW_GAP = 6
INDEX_START_Y = 168
INDEX_COL_W = 540
INDEX_LEFT_X = 70
INDEX_RIGHT_X = 670

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

RED = (132, 34, 34)
GREEN = (46, 88, 52)
GOLD = (168, 132, 64)
INK = (72, 48, 36)
CREAM = (238, 226, 200)

DECOR_FILES = {
    "holly": "realistic-catalog-holly-sprig.png",
    "pine": "realistic-catalog-pine-cone.png",
    "bow": "realistic-catalog-velvet-bow.png",
    "ornament": "realistic-catalog-gold-ornament.png",
    "candle": "realistic-catalog-candle.png",
    "garland": "realistic-catalog-pine-garland.png",
}


def index_rects() -> list[tuple[int, int, int, int, int, str]]:
    out: list[tuple[int, int, int, int, int, str]] = []
    for i, (page, label) in enumerate(INDEX_ENTRIES):
        col = 0 if i < 6 else 1
        row = i % 6
        left = INDEX_LEFT_X if col == 0 else INDEX_RIGHT_X
        top = INDEX_START_Y + row * (INDEX_ROW_H + INDEX_ROW_GAP)
        out.append((page, left, top, INDEX_COL_W, INDEX_ROW_H, label))
    return out


def load_font(size: int, bold: bool = False):
    candidates = [
        "/usr/share/fonts/truetype/noto/NotoSerifDisplay-Bold.ttf" if bold else "/usr/share/fonts/truetype/noto/NotoSerifDisplay-Regular.ttf",
        "/usr/share/fonts/truetype/noto/NotoSerif-Bold.ttf" if bold else "/usr/share/fonts/truetype/noto/NotoSerif-Regular.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf" if bold else "/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf",
    ]
    for path in candidates:
        try:
            return ImageFont.truetype(path, size)
        except OSError:
            continue
    return ImageFont.load_default()


def synthesize_paper(seed: int) -> Image.Image:
    rng = np.random.default_rng(2000 + seed)
    base = np.zeros((H, W, 3), dtype=np.float32)
    base[:] = CREAM
    noise = rng.normal(0, 6.5, (H, W, 3))
    blotch = rng.normal(0, 1, (H // 12, W // 12))
    blotch_img = Image.fromarray(
        (((blotch - blotch.min()) / (blotch.ptp() + 1e-6)) * 255).astype(np.uint8)
    ).resize((W, H), Image.Resampling.BICUBIC)
    blotch = (np.asarray(blotch_img).astype(np.float32) - 128.0) * 0.1
    yy, xx = np.mgrid[0:H, 0:W].astype(np.float32)
    age = (yy / H) * 8 + (np.abs(xx - W / 2) / (W / 2)) * 4
    img = np.clip(base + noise + blotch[..., None] + age[..., None] * np.array([0.4, 0.25, 0.05]), 0, 255)
    return Image.fromarray(img.astype(np.uint8), "RGB")


def load_paper(page: int) -> Image.Image:
    rich_path = GEN_DIR / "rich-paper.png"
    if not rich_path.exists():
        refs: list[Image.Image] = []
        for name in (
            "catalog-p02-ornaments-v2.png",
            "catalog-p04-lights-v2.png",
            "catalog-p01-cover-v2.png",
            "catalog-p08-kitchen-v2.png",
        ):
            for base in (ARTIFACT_DIR, GEN_DIR):
                path = base / name
                if path.exists():
                    refs.append(Image.open(path).convert("RGB").resize((W, H), Image.Resampling.LANCZOS))
                    break
        if refs:
            stack = np.stack([np.asarray(r).astype(np.float32) for r in refs], axis=0).mean(0)
            paper = np.array(CREAM, dtype=np.float32)
            lum = stack.mean(2)
            blur = np.asarray(
                Image.fromarray(np.clip(stack, 0, 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(10))
            ).astype(np.float32)
            grain = stack - blur
            clean = paper + np.clip(grain, -14, 14) * 0.7
            dark = np.clip((185 - lum) / 90.0, 0, 1)[..., None]
            clean = clean * (1 - dark * 0.95) + paper * (dark * 0.95)
            Image.fromarray(np.clip(clean, 0, 255).astype(np.uint8)).save(rich_path)
        else:
            synthesize_paper(page).save(rich_path)
    return Image.open(rich_path).convert("RGB").resize((W, H), Image.Resampling.LANCZOS)


def paper_color_from(im: Image.Image) -> np.ndarray:
    arr = np.asarray(im).astype(np.float32)
    lum = arr.mean(axis=2)
    mask = lum > np.percentile(lum, 70)
    if mask.sum() < 500:
        mask = lum > np.percentile(lum, 50)
    return arr[mask].mean(axis=0)


def erase_print_keep_grain(im: Image.Image) -> Image.Image:
    arr = np.asarray(im).astype(np.float32)
    paper = paper_color_from(im)
    lum = arr.mean(axis=2)
    darkness = np.clip((200 - lum) / 120.0, 0.0, 1.0)
    blurred = np.asarray(im.filter(ImageFilter.GaussianBlur(radius=1.2))).astype(np.float32)
    grain = arr - blurred
    washed = paper + grain * 0.85
    out = arr * (1.0 - darkness[..., None] * 0.88) + washed * (darkness[..., None] * 0.88)
    return Image.fromarray(np.clip(out, 0, 255).astype(np.uint8), "RGB")


def fill_content_with_paper(im: Image.Image) -> Image.Image:
    arr = np.asarray(im).astype(np.float32)
    paper = paper_color_from(im)
    patch = arr[NAV_TOP + 20 : NAV_TOP + 80, 40:140, :]
    if patch.size:
        paper = patch.mean(axis=(0, 1))
    x0, y0, x1, y1 = 28, 28, W - 28, NAV_TOP
    region = arr[y0:y1, x0:x1]
    blur = np.asarray(
        Image.fromarray(np.clip(region, 0, 255).astype(np.uint8)).filter(ImageFilter.GaussianBlur(8))
    ).astype(np.float32)
    grain = region - blur
    filled = paper + np.clip(grain, -18, 18) * 0.55
    arr[y0:y1, x0:x1] = filled * 0.82 + region * 0.18
    return Image.fromarray(np.clip(arr, 0, 255).astype(np.uint8), "RGB")


def draw_outer_border(draw: ImageDraw.ImageDraw) -> None:
    for inset, color, width in [(8, RED, 5), (16, GREEN, 3), (22, RED, 2)]:
        draw.rectangle([inset, inset, W - inset - 1, H - inset - 1], outline=color, width=width)


def draw_center_fold(draw: ImageDraw.ImageDraw) -> None:
    x = W // 2
    draw.line([(x, 26), (x, H - 26)], fill=(176, 158, 128), width=2)
    draw.line([(x + 2, 26), (x + 2, H - 26)], fill=(220, 206, 178), width=1)


def draw_frame(
    draw: ImageDraw.ImageDraw,
    box: tuple[int, int, int, int],
    idx: int,
    paper_rgb: tuple[int, int, int],
) -> None:
    left, top, width, height = box
    right, bottom = left + width, top + height
    if bottom > NAV_TOP - 10:
        bottom = NAV_TOP - 10
        height = bottom - top
        if height < 48:
            return
    color = RED if idx % 2 == 0 else GREEN
    fill = tuple(min(255, c + 4) for c in paper_rgb)
    draw.rectangle([left, top, right, bottom], fill=fill, outline=color, width=4)
    draw.rectangle([left + 6, top + 6, right - 6, bottom - 6], outline=color, width=1)
    for i in range(1, 5):
        y = top + int(height * i / 5)
        draw.line([(left + 14, y), (right - 14, y)], fill=(224, 212, 188), width=1)


_DECOR_CACHE: dict[str, Image.Image] = {}


def get_decor(name: str) -> Image.Image:
    if name not in _DECOR_CACHE:
        path = DECOR_DIR / DECOR_FILES[name]
        _DECOR_CACHE[name] = Image.open(path).convert("RGBA")
    return _DECOR_CACHE[name]


def rects_overlap(a: tuple[int, int, int, int], b: tuple[int, int, int, int]) -> bool:
    ax0, ay0, ax1, ay1 = a
    bx0, by0, bx1, by1 = b
    return not (ax1 <= bx0 or ax0 >= bx1 or ay1 <= by0 or ay0 >= by1)


def paste_decor(
    base: Image.Image,
    name: str,
    cx: int,
    cy: int,
    scale: float = 0.22,
    angle: float = 0.0,
    opacity: float = 0.92,
    flip: bool = False,
    forbidden: list[tuple[int, int, int, int]] | None = None,
) -> bool:
    src = get_decor(name)
    w = max(24, int(src.width * scale))
    h = max(24, int(src.height * scale))
    sprite = src.resize((w, h), Image.Resampling.LANCZOS)
    if flip:
        sprite = ImageOps.mirror(sprite)
    if abs(angle) > 0.1:
        sprite = sprite.rotate(angle, resample=Image.Resampling.BICUBIC, expand=True)
    if opacity < 0.999:
        r, g, b, a = sprite.split()
        a = a.point(lambda v, o=opacity: int(v * o))
        sprite = Image.merge("RGBA", (r, g, b, a))

    left = int(cx - sprite.width / 2)
    top = int(cy - sprite.height / 2)
    right = left + sprite.width
    bottom = top + sprite.height

    if bottom > NAV_TOP - 8:
        top = NAV_TOP - 8 - sprite.height
        bottom = top + sprite.height
    if top < 28:
        top = 28
        bottom = top + sprite.height
    if left < 28:
        left = 28
        right = left + sprite.width
    if right > W - 28:
        left = W - 28 - sprite.width
        right = left + sprite.width

    box = (left, top, right, bottom)
    if forbidden:
        for fx, fy, fw, fh in forbidden:
            fr = (fx + 4, fy + 4, fx + fw - 4, fy + fh - 4)
            if rects_overlap(box, fr):
                return False

    base.paste(sprite, (left, top), sprite)
    return True


def gutter_points(frames: list[tuple[int, int, int, int]]) -> list[tuple[int, int]]:
    pts: list[tuple[int, int]] = [
        (55, 55), (W - 55, 55), (55, NAV_TOP - 40), (W - 55, NAV_TOP - 40),
        (W // 2, 48), (W // 2, NAV_TOP - 36),
        (40, H // 3), (W - 40, H // 3), (40, H // 2), (W - 40, H // 2),
        (90, 220), (W - 90, 220), (90, 480), (W - 90, 480),
        (200, NAV_TOP - 50), (W - 200, NAV_TOP - 50),
    ]
    for i, (x1, y1, w1, h1) in enumerate(frames):
        cx1, cy1 = x1 + w1 // 2, y1 + h1 // 2
        pts.extend([
            (x1 - 28, cy1), (x1 + w1 + 28, cy1),
            (cx1, y1 - 28), (cx1, y1 + h1 + 28),
            (x1 - 18, y1 - 18), (x1 + w1 + 18, y1 - 18),
            (x1 - 18, y1 + h1 + 18), (x1 + w1 + 18, y1 + h1 + 18),
        ])
        for j, (x2, y2, w2, h2) in enumerate(frames):
            if j <= i:
                continue
            pts.append(((cx1 + x2 + w2 // 2) // 2, (cy1 + y2 + h2 // 2) // 2))

    cleaned: list[tuple[int, int]] = []
    for x, y in pts:
        if 36 <= x <= W - 36 and 36 <= y <= NAV_TOP - 24:
            cleaned.append((x, y))
    return cleaned


def decorate_page(base: Image.Image, page: int, frames: list[tuple[int, int, int, int]]) -> None:
    rng = np.random.default_rng(4000 + page * 17)
    forbidden = list(frames)
    forbidden.append((20, NAV_TOP - 5, W - 40, H - NAV_TOP + 5))

    kinds = ["holly", "pine", "bow", "ornament", "candle", "garland"]
    candidates = gutter_points(frames)
    rng.shuffle(candidates)
    count = 16 if page == 1 else 11 + (page % 4)

    placed = 0
    for cx, cy in candidates:
        if placed >= count:
            break
        inside = any(
            fx + 4 <= cx <= fx + fw - 4 and fy + 4 <= cy <= fy + fh - 4
            for fx, fy, fw, fh in frames
        )
        if inside:
            continue
        kind = kinds[(placed + page) % len(kinds)]
        scale_map = {
            "holly": 0.17, "pine": 0.15, "bow": 0.14,
            "ornament": 0.13, "candle": 0.16, "garland": 0.19,
        }
        scale = scale_map[kind] * float(rng.uniform(0.85, 1.2))
        angle = float(rng.uniform(-28, 28))
        flip = bool(rng.integers(0, 2))
        opacity = float(rng.uniform(0.88, 0.98))
        if paste_decor(
            base, kind, cx, cy,
            scale=scale, angle=angle, opacity=opacity, flip=flip, forbidden=forbidden,
        ):
            placed += 1

    anchors = [
        ("garland", 110, 48, 0.22, -8, False),
        ("garland", W - 110, 48, 0.22, 8, True),
        ("holly", 58, 150, 0.18, -20, False),
        ("holly", W - 58, 150, 0.18, 20, True),
        ("bow", W // 2, 42, 0.13, 0, False),
        ("pine", 70, NAV_TOP - 70, 0.16, 12, False),
        ("ornament", W - 70, NAV_TOP - 70, 0.14, -10, False),
        ("candle", 48, 320, 0.17, 0, False),
        ("candle", W - 48, 320, 0.17, 0, True),
        ("holly", 200, NAV_TOP - 55, 0.14, 15, False),
        ("holly", W - 200, NAV_TOP - 55, 0.14, -15, True),
        ("ornament", W // 2 - 80, NAV_TOP - 48, 0.11, 8, False),
        ("bow", W // 2 + 90, NAV_TOP - 52, 0.11, -6, True),
    ]
    for kind, cx, cy, scale, angle, flip in anchors:
        blocked = any(fx <= cx <= fx + fw and fy <= cy <= fy + fh for fx, fy, fw, fh in frames)
        if blocked:
            continue
        paste_decor(
            base, kind, cx, cy,
            scale=scale, angle=angle, opacity=0.94, flip=flip, forbidden=forbidden,
        )


def draw_cover_index(base: Image.Image, paper_rgb: tuple[int, int, int]) -> list[tuple[int, int, int, int]]:
    draw = ImageDraw.Draw(base)
    title_font = load_font(46, bold=True)
    sub_font = load_font(20, bold=False)
    row_font = load_font(26, bold=True)
    page_font = load_font(22, bold=True)

    draw.text((W // 2, 58), "Whimsical Frog", font=title_font, fill=RED, anchor="mt")
    draw.text((W // 2, 108), "Christmas Catalog  ·  Page Index", font=sub_font, fill=GREEN, anchor="mt")
    draw.line([(180, 132), (W - 180, 132)], fill=GOLD, width=2)
    draw.line([(200, 138), (W - 200, 138)], fill=RED, width=1)

    panel = (50, 148, W - 50, 700)
    wash = tuple(min(255, c + 6) for c in paper_rgb)
    draw.rectangle(panel, fill=wash, outline=RED, width=3)
    draw.rectangle([panel[0] + 6, panel[1] + 6, panel[2] - 6, panel[3] - 6], outline=GREEN, width=1)

    frames: list[tuple[int, int, int, int]] = []
    for page, left, top, width, height, label in index_rects():
        right, bottom = left + width, top + height
        frames.append((left, top, width, height))
        tint = (246, 236, 214) if page % 2 else (242, 230, 204)
        outline = GREEN if page % 2 else RED
        draw.rounded_rectangle([left, top, right, bottom], radius=8, fill=tint, outline=outline, width=2)
        mx, my = left + 36, top + height // 2
        draw.ellipse([mx - 22, my - 22, mx + 22, my + 22], fill=RED if page % 2 else GREEN, outline=GOLD, width=2)
        draw.text((mx, my), f"{page}", font=page_font, fill=(255, 250, 240), anchor="mm")
        draw.text((left + 72, top + height // 2 - 2), label, font=row_font, fill=INK, anchor="lm")
        draw.text((right - 18, top + height // 2), "open ›", font=sub_font, fill=GOLD, anchor="rm")
    return frames


def vintage_print_look(im: Image.Image) -> Image.Image:
    im = ImageEnhance.Color(im).enhance(0.92)
    im = ImageEnhance.Contrast(im).enhance(1.06)
    im = ImageEnhance.Brightness(im).enhance(0.99)
    arr = np.asarray(im).astype(np.float32)
    yy, xx = np.mgrid[0:H, 0:W]
    screen = (np.sin(xx * 1.15) * np.sin(yy * 1.05)) * 2.4
    arr = np.clip(arr + screen[..., None], 0, 255)
    cx, cy = W / 2, H / 2
    dist = np.sqrt(((xx - cx) / cx) ** 2 + ((yy - cy) / cy) ** 2)
    arr = arr * (1 - 0.05 * np.clip(dist, 0, 1))[..., None]
    out = Image.fromarray(arr.astype(np.uint8), "RGB")
    return out.filter(ImageFilter.UnsharpMask(radius=1.1, percent=55, threshold=4))


def soften_nav_corners(base: Image.Image, paper: tuple[int, int, int]) -> Image.Image:
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
    return Image.fromarray(np.clip(arr, 0, 255).astype(np.uint8), "RGB")


def build_page(page: int) -> Image.Image:
    base = load_paper(page)
    base = erase_print_keep_grain(base)
    base = fill_content_with_paper(base)

    paper = tuple(int(x) for x in paper_color_from(base))
    draw = ImageDraw.Draw(base)
    draw_outer_border(draw)
    draw_center_fold(draw)

    if page == 1:
        frames = draw_cover_index(base, paper)
    else:
        frames = []
        for idx, box in enumerate(LAYOUTS[page]):
            draw_frame(draw, box, idx, paper)
            frames.append(box)

    decorate_page(base, page, frames)
    base = soften_nav_corners(base, paper)
    return vintage_print_look(base)


def save_page(page: int, im: Image.Image) -> None:
    stem = PAGE_FILES[page]
    png = OUT_DIR / f"{stem}.png"
    webp = OUT_DIR / f"{stem}.webp"
    im.save(png, optimize=True)
    im.save(webp, "WEBP", quality=90, method=6)
    GEN_DIR.mkdir(parents=True, exist_ok=True)
    im.save(GEN_DIR / f"preview-p{page:02d}.png")
    ARTIFACT_DIR.mkdir(parents=True, exist_ok=True)
    im.save(ARTIFACT_DIR / f"catalog-built-p{page:02d}.webp", "WEBP", quality=85, method=4)
    print(f"OK page {page}: {png.name} ({png.stat().st_size} bytes)")


def main() -> None:
    GEN_DIR.mkdir(parents=True, exist_ok=True)
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    missing = [n for n, f in DECOR_FILES.items() if not (DECOR_DIR / f).exists()]
    if missing:
        raise SystemExit(f"Missing decoration sprites: {missing}")
    for page in range(1, 13):
        save_page(page, build_page(page))
    print("done")


if __name__ == "__main__":
    main()
